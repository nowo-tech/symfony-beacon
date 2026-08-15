#!/bin/sh
set -e

# =============================================================================
# FrankenPHP mode switch (classic ↔ worker)
# =============================================================================
# Inputs (compose / .env):
#   FRANKENPHP_MODE            classic | worker   (default: classic)
#   FRANKENPHP_WORKER_NUM      worker process count (optional; default = 2×CPU)
#   FRANKENPHP_LOOP_MAX        requests per worker before restart (Symfony Runtime)
#   FRANKENPHP_RESET_KERNEL    true|false — clone Kernel between requests
#   FRANKENPHP_WORKER_CONFIG   optional Caddy worker subdirectives (dev: "watch")
#
# Output:
#   FRANKENPHP_CONFIG  → injected into the `frankenphp { }` Caddyfile block.
#
# Worker flow with Symfony 8.1 (native, no runtime/frankenphp-symfony):
#   1) FrankenPHP boots public/index.php as a worker and sets FRANKENPHP_WORKER=1
#   2) Symfony\Component\Runtime\SymfonyRuntime detects FRANKENPHP_WORKER
#   3) Uses FrankenPhpWorkerRunner → frankenphp_handle_request() loop
# Hot reload (dev): pair worker "watch" with php_server hot_reload — docs/ops/FRANKENPHP-HOT-RELOAD.md
# =============================================================================
configure_frankenphp_mode() {
	mode="$(printf '%s' "${FRANKENPHP_MODE:-classic}" | tr '[:upper:]' '[:lower:]')"

	# Front controller relative to WORKDIR (/app). Must match php_server.
	worker_file="./public/index.php"

	# Optional worker.watch lines (dev hot_reload). Compose sets FRANKENPHP_WORKER_CONFIG=watch.
	# Prod must leave FRANKENPHP_WORKER_CONFIG empty — watching the filesystem is a DX-only cost.
	worker_extra="$(printf '%s' "${FRANKENPHP_WORKER_CONFIG:-}" | tr -d '\r')"

	case "$mode" in
		worker)
			# Full Caddy worker block: path + optional num + optional watch.
			workers="${FRANKENPHP_WORKER_NUM:-}"
			if [ -n "$workers" ]; then
				export FRANKENPHP_CONFIG="worker {
	file ${worker_file}
	num ${workers}
	${worker_extra}
}"
			else
				export FRANKENPHP_CONFIG="worker {
	file ${worker_file}
	${worker_extra}
}"
			fi
			;;
		classic)
			# No worker: full bootstrap per request (PHP-FPM-like).
			export FRANKENPHP_CONFIG=""
			;;
		*)
			echo "Invalid FRANKENPHP_MODE: '${FRANKENPHP_MODE}'. Use 'classic' or 'worker'." >&2
			exit 1
			;;
	esac

	echo "FrankenPHP mode: ${mode}"
	if [ -n "$FRANKENPHP_CONFIG" ]; then
		echo "FRANKENPHP_CONFIG:"
		echo "$FRANKENPHP_CONFIG"
	else
		echo "FRANKENPHP_CONFIG: (empty — classic)"
	fi
}

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ] || [ "$1" = 'pnpm' ] || [ "$1" = 'node' ]; then
	# FrankenPHP mode only applies to the HTTP app server.
	if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
		configure_frankenphp_mode
	fi

	# Bind-mount ./:/app is owned by the host UID; the container often runs as root.
	# Git 2.35.2+ refuses that mismatch unless /app is marked safe.
	if command -v git >/dev/null 2>&1; then
		git config --global --add safe.directory /app 2>/dev/null || true
	fi

	# Ensure the Symfony front controller exists (not the image welcome page).
	if [ "$1" = 'frankenphp' ] && [ ! -f public/index.php ]; then
		echo "ERROR: missing public/index.php. Is the code mounted? (compose.yaml volumes: ./:/app)" >&2
		exit 1
	fi

	# Install Composer deps when vendor/ is missing (bind-mounted app without a prior install).
	# Use flock: php + messenger + messenger-notify share ./:/app and would race otherwise.
	if [ -f composer.json ] && [ ! -f vendor/autoload_runtime.php ]; then
		mkdir -p var
		(
			flock 9
			if [ ! -f vendor/autoload_runtime.php ]; then
				composer install --prefer-dist --no-progress --no-interaction
			fi
		) 9>var/.composer-install.lock
	fi

	# Install JS deps when node_modules/ is missing (Pentatrion Vite / pnpm).
	if [ -f package.json ] && [ -z "$(ls -A 'node_modules/' 2>/dev/null)" ]; then
		mkdir -p var
		(
			flock 9
			if [ -z "$(ls -A 'node_modules/' 2>/dev/null)" ]; then
				pnpm install --frozen-lockfile || pnpm install
			fi
		) 9>var/.pnpm-install.lock
	fi

	# Halite field-encryption key parent dir (doctrine-encrypt-bundle does not mkdir).
	mkdir -p var/secrets

	if [ -f bin/console ] && { [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; }; then
		php bin/console -V || true
	fi

	# Wait for MySQL when Doctrine is available and DATABASE_URL is set (HTTP / console only).
	# Do NOT create the schema or run migrations here — that belongs to SiteBackup /setup
	# (`database_create` + `migrations`) or the CLI (`make bootstrap` / `make migrate`).
	if { [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; } \
		&& [ -f bin/console ] && [ -n "${DATABASE_URL:-}" ] \
		&& php bin/console list 2>/dev/null | grep -q 'dbal:run-sql'; then
		# Server probe (no schema): "Unknown database" must not look like MySQL is down.
		mysql_server_ready() {
			php -r '
$h = getenv("MYSQL_HOST") ?: "database";
$p = getenv("MYSQL_PORT") ?: "3306";
$u = getenv("MYSQL_USER") ?: "root";
$w = getenv("MYSQL_PASSWORD") ?: "";
try {
	new PDO("mysql:host={$h};port={$p};charset=utf8mb4", $u, $w, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_TIMEOUT => 3,
	]);
	exit(0);
} catch (Throwable $e) {
	exit(1);
}
'
		}
		mysql_schema_ready() {
			php bin/console dbal:run-sql -q "SELECT 1" >/dev/null 2>&1
		}

		echo 'Waiting for database server to be ready...'
		ATTEMPTS=60
		until [ "$ATTEMPTS" -eq 0 ] || mysql_server_ready; do
			ATTEMPTS=$((ATTEMPTS - 1))
			echo "Still waiting for database... ${ATTEMPTS} attempts left."
			sleep 1
		done
		if [ "$ATTEMPTS" -eq 0 ]; then
			echo 'WARNING: database server not reachable yet.' >&2
		elif mysql_schema_ready; then
			echo 'Database server and schema are reachable (migrate via /setup or make migrate).'
		else
			# Missing schema is expected for SiteBackup cold start — HTTP must still boot for /setup.
			echo 'Database schema missing — open /setup (SiteBackup: database_create + migrations).'
			if [ "$1" != 'frankenphp' ]; then
				echo 'Waiting for schema (workers need it; complete /setup or make bootstrap)...'
				ATTEMPTS=60
				until [ "$ATTEMPTS" -eq 0 ] || mysql_schema_ready; do
					ATTEMPTS=$((ATTEMPTS - 1))
					echo "Still waiting for database schema... ${ATTEMPTS} attempts left."
					sleep 1
				done
				if [ "$ATTEMPTS" -eq 0 ]; then
					echo 'WARNING: database schema not ready yet.' >&2
				else
					echo 'Database schema is now reachable.'
				fi
			fi
		fi
	fi

	if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
		echo 'PHP app ready!'
	fi
fi

exec docker-php-entrypoint "$@"
