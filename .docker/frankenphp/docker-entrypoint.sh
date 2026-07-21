#!/bin/sh
set -e

# =============================================================================
# FrankenPHP mode switch (classic ↔ worker)
# =============================================================================
# Inputs (compose / .env):
#   FRANKENPHP_MODE          classic | worker   (default: classic)
#   FRANKENPHP_WORKER_NUM    worker process count (optional; default = 2×CPU)
#   FRANKENPHP_LOOP_MAX      requests per worker before restart (Symfony Runtime)
#   FRANKENPHP_RESET_KERNEL  true|false — clone Kernel between requests
#
# Output:
#   FRANKENPHP_CONFIG  → injected into the `frankenphp { }` Caddyfile block.
#
# Worker flow with Symfony 8.1 (native, no runtime/frankenphp-symfony):
#   1) FrankenPHP boots public/index.php as a worker and sets FRANKENPHP_WORKER=1
#   2) Symfony\Component\Runtime\SymfonyRuntime detects FRANKENPHP_WORKER
#   3) Uses FrankenPhpWorkerRunner → frankenphp_handle_request() loop
# =============================================================================
configure_frankenphp_mode() {
	mode="$(printf '%s' "${FRANKENPHP_MODE:-classic}" | tr '[:upper:]' '[:lower:]')"

	# Front controller relative to WORKDIR (/app). Must match php_server.
	worker_file="./public/index.php"

	case "$mode" in
		worker)
			# Full Caddy worker block: path + optional num.
			workers="${FRANKENPHP_WORKER_NUM:-}"
			if [ -n "$workers" ]; then
				export FRANKENPHP_CONFIG="worker {
	file ${worker_file}
	num ${workers}
}"
			else
				export FRANKENPHP_CONFIG="worker {
	file ${worker_file}
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
	if [ -f composer.json ] && [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	# Install JS deps when node_modules/ is missing (Pentatrion Vite / pnpm).
	if [ -f package.json ] && [ -z "$(ls -A 'node_modules/' 2>/dev/null)" ]; then
		pnpm install --frozen-lockfile || pnpm install
	fi

	if [ -f bin/console ] && { [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; }; then
		php bin/console -V || true
	fi

	# Wait for MySQL when Doctrine is available and DATABASE_URL is set (HTTP / console only).
	if { [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; } \
		&& [ -f bin/console ] && [ -n "${DATABASE_URL:-}" ] \
		&& php bin/console list 2>/dev/null | grep -q 'dbal:run-sql'; then
		echo 'Waiting for database to be ready...'
		ATTEMPTS=60
		until [ "$ATTEMPTS" -eq 0 ] || php bin/console dbal:run-sql -q "SELECT 1" >/dev/null 2>&1; do
			ATTEMPTS=$((ATTEMPTS - 1))
			echo "Still waiting for database... ${ATTEMPTS} attempts left."
			sleep 1
		done
		if [ "$ATTEMPTS" -eq 0 ]; then
			echo 'WARNING: database not reachable yet.' >&2
		else
			echo 'The database is now ready and reachable.'
			if [ -d migrations ] && [ "$(find migrations -iname '*.php' -print -quit 2>/dev/null)" ]; then
				php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing || true
			fi
			# Create messenger_messages tables when Doctrine transport is configured
			if php bin/console list 2>/dev/null | grep -q 'messenger:setup-transports'; then
				php bin/console messenger:setup-transports --no-interaction || true
			fi
		fi
	fi

	if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
		echo 'PHP app ready!'
	fi
fi

exec docker-php-entrypoint "$@"
