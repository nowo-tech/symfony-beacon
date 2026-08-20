.PHONY: ensure-env  help up up-infra up-prod up-shared down down-infra down-shared build build-prod logs shell console beacon-test beacon-suite seed seed-platform seed-sample dogfood reclaim-demo-client-env bootstrap ready migrate classic worker restart mysql messenger-logs vite vite-hmr vite-build vite-watch pnpm mailpit mailpit-logs specify-check \
	cs cs-fix twig-cs twig-cs-fix phpstan rector rector-fix test test-coverage test-unit-js test-unit-js-coverage test-e2e test-e2e-isolated up-e2e down-e2e ensure-e2e-env ensure-e2e-db ensure-e2e-up ready-e2e seed-e2e kit-smoke qa qa-fix secrets-scan composer-outdated update-deps \
	setup-hooks check-no-cursor-coauthor check-module-boundaries strip-cursor-coauthor-from-history check-envelope-goldens ensure-up ensure-halite-secrets print-urls bootstrap-shared-db

# App Compose (dev). Infra is a separate project (`shared-infra` via compose.infra.yaml).
# REQ-ENV-003: operator working file is `.env.local`
# Quiet recursive $(MAKE) noise (Entering/Leaving directory).
MAKEFLAGS += --no-print-directory
ENV_FILE := .env.local
E2E_ENV_FILE := .env.e2e.local
export COMPOSE_ENV_FILES := $(ENV_FILE)
DC := docker compose --env-file $(ENV_FILE)
DC_INFRA := docker compose -p shared-infra -f compose.infra.yaml --env-file $(ENV_FILE)
DC_PROD := $(DC) -f compose.prod.yaml --env-file $(ENV_FILE)
# Override for print-urls when using the prod compose file (`make up-prod`).
COMPOSE ?= $(DC)
COVERAGE_MIN ?= 100
# Shared stack (developer.local.server/server) when this repo lives under repositories/other/
SHARED_SERVER_DIR := $(abspath $(CURDIR)/../../../server)
# Isolated E2E defaults (override: E2E_HTTPS_PORT=9450 make up-e2e).
# Must be defined before DC_E2E (immediate := expansion).
E2E_HTTP_PORT ?= 9085
E2E_HTTPS_PORT ?= 9460
E2E_MYSQL_DATABASE ?= app_e2e
E2E_REDIS_DB ?= 1
# BeaconBundle on the E2E stack: self (app_e2e issues @ :9460) | dogfood (report into :9447 project) | off
E2E_BEACON_TARGET ?= self
PLAYWRIGHT_E2E_BASE_URL ?= https://localhost:$(E2E_HTTPS_PORT)
# Isolated Playwright stack (app_e2e + Redis DB 1 + ports 9085/9460 by default).
# Always pass -p: COMPOSE_PROJECT_NAME inside --env-file is NOT used as the project name
# (Compose reads the process env / directory name). Without -p, `up` recreates dogfood.
# Force HTTP(S)_PORT (+ Redis DSNs for compose.e2e interpolation) on the process env so a
# sourced .env.local / empty shell var cannot override --env-file (Compose prefers the
# process environment). Messenger uses ?dbindex= — path would steal stream names.
DC_E2E := HTTP_PORT=$(E2E_HTTP_PORT) HTTPS_PORT=$(E2E_HTTPS_PORT) HTTP3_PORT=$(E2E_HTTPS_PORT) \
	DEFAULT_URI=https://localhost:$(E2E_HTTPS_PORT) \
	MYSQL_DATABASE=$(E2E_MYSQL_DATABASE) \
	REDIS_URL=redis://$${REDIS_HOST:-redis-8.10.0}:$${REDIS_PORT:-6379}/$(E2E_REDIS_DB) \
	MESSENGER_TRANSPORT_DSN=redis://$${REDIS_HOST:-redis-8.10.0}:$${REDIS_PORT:-6379}?dbindex=$(E2E_REDIS_DB) \
	COMPOSE_PROJECT_NAME=symfony-beacon-e2e COMPOSE_ENV_FILES=$(E2E_ENV_FILE) \
	docker compose -p symfony-beacon-e2e --env-file $(E2E_ENV_FILE) \
	-f compose.yaml -f compose.override.yaml -f compose.e2e.yaml

ensure-env:
	@./.scripts/ensure-env-local.sh

help:
	@echo "symfony-beacon — self-hosted error tracking (Symfony 8.1 + FrankenPHP + MySQL 9.7)"
	@echo ""
	@echo "  make up              Ensure shared infra + start app (php/messenger/messenger-notify) + vite-build"
	@echo "  make up-infra        Start shared MySQL + Redis (compose.infra.yaml); see docs/ops/SHARED-SERVER.md"
	@echo "  make up-prod         Ensure infra + start compose.prod.yaml app stack"
	@echo "  make up-shared       Alias of make up (legacy name)"
	@echo "  make ensure-up       Start infra + app if php is not running (no rebuild / no vite)"
	@echo "  make classic         FrankenPHP HTTP in classic mode (one-shot; does not rewrite .env)"
	@echo "  make worker          FrankenPHP HTTP in worker mode (one-shot; does not rewrite .env)"
	@echo "  make down            Stop app containers (infra stays for sibling projects)"
	@echo "  make down-infra      Stop shared infra (MySQL/Redis); warn if other apps use it"
	@echo "  make down-shared     Alias of make down (legacy name)"
	@echo "  make bootstrap-shared-db  Create schema/user on shared MySQL primary"
	@echo "  make build           Rebuild the php image (dev)"
	@echo "  make build-prod      Build frankenphp_prod image (see docs/PRODUCTION.md)"
	@echo "  make logs            Follow php service logs"
	@echo "  make vite-hmr        Start Vite HMR (compose profile hmr)"
	@echo "  make vite            Follow Vite HMR logs (run make vite-hmr first)"
	@echo "  make vite-build      pnpm run build (one-shot → public/build/)"
	@echo "  make vite-watch      pnpm run watch (vite build --watch, no HMR)"
	@echo "  make pnpm            pnpm in php container (ARGS='install' / 'add -D …')"
	@echo "  make mailpit         Start Mailpit (prefers shared server/; else Compose profile mail)"
	@echo "  make mailpit-logs    Follow Mailpit logs"
	@echo "  make messenger-logs  Follow messenger + messenger-notify logs"
	@echo "  make mysql           mysql CLI shell (mysql-9.7-primary)"
	@echo "  make shell           Shell in the php container"
	@echo "  make console         bin/console (ARGS='...')"
	@echo "  make beacon-test     Probe BEACON_DSN via app:beacon:test (ARGS='--check-only' optional)"
	@echo "  make beacon-suite    Multi-event dogfood suite (message/exception/console/HTTP/…)"
	@echo "  make seed-platform   Upsert menus/breadcrumbs/cookie consent (safe after upgrades)"
	@echo "  make seed            Platform seed + demo user/project + .demo-client.env + server BEACON_DSN"
	@echo "  make seed-sample     Sample telemetry (PROFILE=dev|load|huge)"
	@echo "  make dogfood         Symfony Beacon project + ROLE_ADMIN access + sync BEACON_DSN (no new user)"
	@echo "  make bootstrap       Migrate DB + platform seed (after make up)"
	@echo "  make migrate         doctrine:migrations:migrate -n (no seed)"
	@echo "  make ready           bootstrap + seed (recommended first local run / dogfooding)"
	@echo "  make restart         Restart php + messenger + messenger-notify (+ vite-build)"
	@echo "  make specify-check   Verify Specify CLI"
	@echo ""
	@echo "Quality:"
	@echo "  make cs              PHP-CS-Fixer (check)"
	@echo "  make cs-fix          PHP-CS-Fixer (fix)"
	@echo "  make twig-cs         Twig-CS-Fixer (lint)"
	@echo "  make twig-cs-fix     Twig-CS-Fixer (fix)"
	@echo "  make phpstan         PHPStan analyse"
	@echo "  make rector          Rector dry-run"
	@echo "  make rector-fix      Rector apply, then CS Fixer (Rector can leave spacing diffs)"
	@echo "  make test            PHPUnit"
	@echo "  make test-coverage   PHPUnit + Clover/HTML (var/coverage*); defaults to COVERAGE_MIN=100"
	@echo "  make test-unit-js    Vitest unit tests for assets/"
	@echo "  make test-unit-js-coverage  Vitest + V8 coverage → var/coverage-js/"
	@echo "  make test-e2e        Playwright E2E against dogfood stack (make up + seed[+sample]; mutates MYSQL_DATABASE)"
	@echo "  make test-e2e-isolated  Playwright against isolated stack (app_e2e / :$(E2E_HTTPS_PORT); needs make ready-e2e)"
	@echo "  make up-e2e          Start isolated E2E Compose project (does not stop dogfood stack)"
	@echo "  make ready-e2e       Migrate + seed + seed-sample on app_e2e"
	@echo "  make down-e2e        Stop isolated E2E Compose project (keeps app_e2e schema)"
	@echo "  make kit-smoke       AuthKit smoke (login, magic login, password reset, throttle)"
	@echo "  make secrets-scan    Gitleaks secret scan (same gate as CI)"
	@echo "  make qa              cs + twig-cs + phpstan + rector + check-module-boundaries + test"
	@echo "  make qa-fix          cs-fix + twig-cs-fix + phpstan + rector-fix + test"
	@echo "  make update-deps     bump pinned Composer deps (helper --run) + composer update + pnpm update"
	@echo "  make composer-outdated  Suggest composer require pins (nowo-tech/composer-update-helper)"
	@echo ""
	@echo "Git hygiene:"
	@echo "  make setup-hooks                    Install .githooks (strips Cursor co-authors)"
	@echo "  make check-no-cursor-coauthor       Fail if Cursor trailers exist in history"
	@echo "  make check-module-boundaries         Fail if AdminProjectController leaves Project"
	@echo "  make check-envelope-goldens         Diff Envelope fixtures vs sibling BeaconBundle (__HTTPS_PORT__)"
	@echo "  make strip-cursor-coauthor-from-history  Rewrite local history to remove them"

setup-hooks:
	@chmod +x .githooks/commit-msg .githooks/prepare-commit-msg .scripts/check-no-cursor-coauthor.sh .scripts/strip-cursor-coauthor-from-history.sh .scripts/check-module-boundaries.sh
	@git config core.hooksPath .githooks
	@echo "✅ Git hooks installed (.githooks — strips Cursor Co-authored-by / Made-with trailers)."

check-no-cursor-coauthor:
	@chmod +x .scripts/check-no-cursor-coauthor.sh
	@./.scripts/check-no-cursor-coauthor.sh HEAD

check-module-boundaries:
	@chmod +x .scripts/check-module-boundaries.sh
	@./.scripts/check-module-boundaries.sh

check-envelope-goldens:
	@chmod +x .scripts/check-envelope-goldens.sh
	@./.scripts/check-envelope-goldens.sh

strip-cursor-coauthor-from-history:
	@chmod +x .scripts/strip-cursor-coauthor-from-history.sh
	@./.scripts/strip-cursor-coauthor-from-history.sh main

# Bring shared infra + app up if the php service cannot exec (no --build, no vite).
# Used as a prerequisite by targets that need a running container. Does not call `make up`
# (avoids recursion when `up` itself runs vite-build).
ensure-up:
	@$(MAKE) ensure-env
	@$(MAKE) up-infra
	@$(DC) exec -T php true >/dev/null 2>&1 || { \
		echo "App stack is down — starting compose up -d…"; \
		$(DC) up -d; \
	}

# Print published app ports (from running compose, else .env / defaults).
# Use COMPOSE="$(DC_PROD)" after make up-prod so ports come from the prod stack.
print-urls:
	@HTTP_PUB=$$($(COMPOSE) port php 80 2>/dev/null | head -1 | sed 's/.*://'); \
	HTTPS_PUB=$$($(COMPOSE) port php 443 2>/dev/null | head -1 | sed 's/.*://'); \
	if [ -z "$$HTTP_PUB" ]; then HTTP_PUB=$$(grep -E '^HTTP_PORT=' .env.local 2>/dev/null | cut -d= -f2-); fi; \
	if [ -z "$$HTTPS_PUB" ]; then HTTPS_PUB=$$(grep -E '^HTTPS_PORT=' .env.local 2>/dev/null | cut -d= -f2-); fi; \
	HTTP_PUB=$${HTTP_PUB:-9084}; \
	HTTPS_PUB=$${HTTPS_PUB:-9447}; \
	echo ""; \
	echo "Beacon is up:"; \
	echo "  HTTP:  http://localhost:$${HTTP_PUB}"; \
	echo "  HTTPS: https://localhost:$${HTTPS_PUB}"; \
	MAILPIT_UI=""; \
	MAILPIT_SMTP_HINT="smtp://mailpit:1025"; \
	if docker inspect mailpit >/dev/null 2>&1; then \
		MAILPIT_UI=$$(docker port mailpit 8025/tcp 2>/dev/null | head -1 | sed 's/.*://'); \
	fi; \
	if [ -z "$$MAILPIT_UI" ]; then \
		MAILPIT_UI=$$($(DC) --profile mail port mailer 8025 2>/dev/null | head -1 | sed 's/.*://'); \
		MAILPIT_SMTP_HINT="smtp://mailer:1025"; \
	fi; \
	if [ -n "$$MAILPIT_UI" ]; then \
		echo "  Mailpit UI: http://localhost:$${MAILPIT_UI}  (SMTP from PHP: $${MAILPIT_SMTP_HINT})"; \
	fi

# Shared MySQL + Redis (compose.infra.yaml, project shared-infra).
# Coexistence: if mysql-9.7-primary already exists (e.g. developer.local.server/server), skip create.
# MYSQL_TOPOLOGY=simple|replica (from .env.local or env) — replica adds profile mysql-replica.
up-infra:
	@$(MAKE) ensure-env
	@set -a; . ./.env.local; set +a; \
	TOPOLOGY="$${MYSQL_TOPOLOGY:-simple}"; \
	PROFILE_ARGS=""; \
	if [ "$$TOPOLOGY" = "replica" ]; then PROFILE_ARGS="--profile mysql-replica"; fi; \
	if docker inspect mysql-9.7-primary >/dev/null 2>&1; then \
		echo "Shared infra: mysql-9.7-primary already exists — not recreating (coexistence)."; \
		docker start mysql-9.7-primary >/dev/null 2>&1 || true; \
		docker start redis-8.10.0 >/dev/null 2>&1 || true; \
		if [ "$$TOPOLOGY" = "replica" ]; then \
			if ! docker inspect mysql-9.7-replica >/dev/null 2>&1; then \
				echo "MYSQL_TOPOLOGY=replica but mysql-9.7-replica is missing."; \
				echo "Start it from the same infra that owns the primary, or remove the primary and run make up-infra."; \
				exit 1; \
			fi; \
			docker start mysql-9.7-replica >/dev/null 2>&1 || true; \
		fi; \
		docker inspect redis-8.10.0 >/dev/null 2>&1 || { \
			echo "redis-8.10.0 is missing while MySQL exists. Start Redis from the same infra owner."; \
			exit 1; \
		}; \
		NET="$${SHARED_DOCKER_NETWORK:-server_network}"; \
		docker network inspect "$$NET" >/dev/null 2>&1 || { \
			echo "Docker network '$$NET' not found."; \
			exit 1; \
		}; \
	else \
		echo "Starting shared infra (MYSQL_TOPOLOGY=$$TOPOLOGY)…"; \
		$(DC_INFRA) $$PROFILE_ARGS up -d; \
	fi

down-infra:
	@echo "Stopping shared-infra (MySQL/Redis). Sibling projects on server_network may break."
	-$(DC_INFRA) --profile mysql-replica down

up:
	@$(MAKE) ensure-env
	@$(MAKE) up-infra
	$(DC) up --build -d
	@echo "Building frontend assets (static public/build/)…"
	@$(MAKE) vite-build
	@$(MAKE) print-urls
	@echo "Optional local SMTP: make mailpit  (see docs/ops/MAILPIT.md)"

up-prod:
	@$(MAKE) ensure-env
	@$(MAKE) up-infra
	$(DC_PROD) up --build -d
	@$(MAKE) print-urls COMPOSE="$(DC_PROD)"

# Legacy alias — shared mode is the default (`make up`).
up-shared: up

# Mode is injected via compose.yaml `environment: FRANKENPHP_MODE` (overrides env_file).
# One-shot for this up/recreate — persist by setting FRANKENPHP_MODE in .env.
classic:
	@$(MAKE) ensure-env
	@$(MAKE) up-infra
	FRANKENPHP_MODE=classic $(DC) up --build -d --force-recreate php
	@$(MAKE) vite-build
	@$(MAKE) print-urls

worker:
	@$(MAKE) ensure-env
	@$(MAKE) up-infra
	FRANKENPHP_MODE=worker $(DC) up --build -d --force-recreate php
	@$(MAKE) vite-build
	@$(MAKE) print-urls

down:
	$(DC) --profile hmr --profile mail down

down-shared: down

bootstrap-shared-db:
	@chmod +x .scripts/bootstrap-shared-db.sh
	@./.scripts/bootstrap-shared-db.sh

build:
	$(DC) build --no-cache

build-prod:
	docker build --target frankenphp_prod -t ${IMAGES_PREFIX:-}symfony-beacon:prod .

logs:
	$(DC) logs -f php

vite-hmr: ensure-up
	$(DC) --profile hmr up -d vite
	@echo "Vite HMR is on — entrypoints use viteServer (browser shows a pending HMR WebSocket)."
	@echo "For stable UI without HMR: $(DC) --profile hmr stop vite && make vite-build"

vite:
	$(DC) logs -f vite

vite-build: ensure-up
	$(DC) exec -T php pnpm run build

vite-watch: ensure-up
	$(DC) exec php pnpm run watch

pnpm: ensure-up
	$(DC) exec -T php pnpm $(ARGS)

# Local SMTP catcher (Mailpit). Dev only — not started by `make up`; not in compose.prod.yaml.
# Prefers developer.local.server/server mailpit on server_network; falls back to Compose profile mail.
# Docs: docs/ops/MAILPIT.md — save smtp://mailpit:1025 (shared) or smtp://mailer:1025 (app-local).
mailpit:
	@$(MAKE) ensure-env
	@if [ -f "$(SHARED_SERVER_DIR)/docker-compose.yml" ]; then \
		$(DC) --profile mail stop mailer >/dev/null 2>&1 || true; \
		$(MAKE) -C "$(SHARED_SERVER_DIR)" up-mailpit; \
		echo "  Save DSN under Administration → Mailer: smtp://mailpit:1025"; \
		echo "  Docs: docs/ops/MAILPIT.md"; \
	else \
		$(DC) --profile mail up -d mailer; \
		UI_PUB=$$($(DC) --profile mail port mailer 8025 2>/dev/null | head -1 | sed 's/.*://'); \
		UI_PUB=$${UI_PUB:-18026}; \
		echo ""; \
		echo "Mailpit is up (app-local profile mail — shared server/ not found):"; \
		echo "  UI:   http://localhost:$${UI_PUB}"; \
		echo "  SMTP (from PHP container): smtp://mailer:1025"; \
		echo "  Save that DSN under Administration → Mailer, then use Send sample email."; \
		echo "  Docs: docs/ops/MAILPIT.md"; \
	fi

mailpit-logs:
	@if docker inspect mailpit >/dev/null 2>&1; then \
		docker logs -f mailpit; \
	else \
		$(DC) --profile mail logs -f mailer; \
	fi

messenger-logs:
	$(DC) logs -f messenger messenger-notify

shell: ensure-up
	$(DC) exec php sh

console: ensure-up
	$(DC) exec php bin/console $(ARGS)

# Dogfood: probe BEACON_DSN + local hints (issue novelty / Web Push readiness). Thin client-only: bin/console nowo:beacon:test
# Examples: make beacon-test   |   make beacon-test ARGS='--check-only'   |   make beacon-test ARGS='--message=ci --wait=15'
# Suite:    make beacon-suite  |   make beacon-test ARGS='--suite --run-token=demo'
beacon-test: ensure-up
	$(DC) exec php bin/console app:beacon:test $(ARGS)

beacon-suite: ensure-up
	$(DC) exec php bin/console app:beacon:test --suite $(ARGS)

# Halite key file lives under var/secrets/; the encrypt bundle does not mkdir for you.
# Harden key files to 0600 (world-writable keys would decrypt all #[Encrypted] columns).
ensure-halite-secrets: ensure-up
	$(DC) exec -T php sh -c 'mkdir -p var/secrets && chmod 770 var/secrets && find var/secrets -maxdepth 1 -type f -name ".Halite*.key" -exec chmod 600 {} +'

seed-platform: ensure-halite-secrets
	$(DC) exec -T php bin/console app:seed-platform

seed: seed-platform
	$(DC) exec -T php bin/console app:seed-demo
	@$(MAKE) reclaim-demo-client-env
	@echo "Client env: .demo-client.env (mode 600) — in BeaconBundle/demo/symfony8 run: make sync-beacon"
	@echo "Server dogfood: BEACON_DSN set in .env when empty (loopback 127.0.0.1)"
	@echo "Optional samples: make seed-sample   (or PROFILE=load / PROFILE=huge)"

seed-sample: ensure-halite-secrets
	$(DC) exec -T php bin/console app:seed-sample --size=$${PROFILE:-dev}

# Ensure demo project + API key exist, grant every ROLE_ADMIN membership (first registered = preferred owner),
# write .demo-client.env, and sync server BEACON_DSN (loopback). No admin@… demo user.
dogfood: ensure-halite-secrets
	$(DC) exec -T php bin/console app:seed-demo --skip-demo-user --sync-server-dsn
	@$(MAKE) reclaim-demo-client-env
	@echo "Dogfood: BEACON_DSN synced to loopback self DSN. If it changed, run: make restart"

# PHP container often writes .demo-client.env as root:600; host Playwright/E2E must be able to read it.
reclaim-demo-client-env:
	@if [ -f .demo-client.env ]; then \
		$(DC) exec -u root -T php chown $(shell id -u):$(shell id -g) /app/.demo-client.env 2>/dev/null \
			|| sudo chown "$(shell id -u):$(shell id -g)" .demo-client.env 2>/dev/null \
			|| chown "$(shell id -u):$(shell id -g)" .demo-client.env 2>/dev/null \
			|| true; \
		chmod 600 .demo-client.env 2>/dev/null || true; \
	fi

migrate: ensure-halite-secrets
	$(DC) exec -T php bin/console doctrine:migrations:migrate -n
	$(DC) exec -T php bin/console messenger:setup-transports --no-interaction

bootstrap: migrate
	@$(MAKE) seed-platform
	@$(DC) exec -T php sh -c 'mkdir -p var/site-backup && touch var/site-backup/setup.done'
	@echo "Next: make seed (or make ready) for demo user + dogfood DSN — or open /setup / register"

ready: bootstrap seed
	@echo "Ready: demo project seeded; restart php if BEACON_DSN was just written (make restart)"
	@echo "Ops panel: /_site_backup  |  Setup wizard: /setup"

# Recreate (not plain restart) so Compose reloads .env into the container
# (e.g. BEACON_DSN after make dogfood / make seed). Plain `restart` keeps stale env.
restart: ensure-up
	$(DC) up -d --force-recreate --no-deps php messenger messenger-notify
	@$(MAKE) vite-build

mysql: ensure-up
	@set -a; . ./.env 2>/dev/null; set +a; \
	docker exec -it mysql-9.7-primary mysql -u$${MYSQL_USER:-app} -p$${MYSQL_PASSWORD:-!ChangeMe!} $${MYSQL_DATABASE:-app}

specify-check:
	@command -v specify >/dev/null || { echo "Install Specify: uv tool install specify-cli"; exit 1; }
	specify check
	@echo "Constitution: .specify/memory/constitution.md"
	@echo "Cursor skills: .cursor/skills/"
	@echo "Specs: specs/"

cs: ensure-up
	$(DC) exec -T php vendor/bin/php-cs-fixer check --diff

cs-fix: ensure-up
	$(DC) exec -T php vendor/bin/php-cs-fixer fix

twig-cs: ensure-up
	$(DC) exec -T php vendor/bin/twig-cs-fixer lint --config=.twig-cs-fixer.dist.php

twig-cs-fix: ensure-up
	$(DC) exec -T php vendor/bin/twig-cs-fixer fix --config=.twig-cs-fixer.dist.php

phpstan: ensure-up
	$(DC) exec -T php bin/console cache:warmup --env=dev
	$(DC) exec -T php vendor/bin/phpstan analyse --memory-limit=512M -c phpstan.neon.dist

rector: ensure-up
	$(DC) exec -T php vendor/bin/rector process --dry-run

# Always re-run CS Fixer after Rector: rules like blank_line_before_statement leave diffs
# that fail `php-cs-fixer check` in CI (qa-fix previously ran CS before Rector only).
rector-fix: ensure-up
	$(DC) exec -T php vendor/bin/rector process
	@$(MAKE) cs-fix

test: ensure-up
	$(DC) exec -T php sh -c 'rm -rf var/cache/test/* && vendor/bin/phpunit $(ARGS)'

test-unit-js: ensure-up
	$(DC) exec -T php pnpm run test:unit $(ARGS)

test-unit-js-coverage: ensure-up
	$(DC) exec -T php pnpm run test:unit:coverage $(ARGS)

# Browser E2E via official Playwright image (WSL-friendly Chromium deps).
# Dogfood stack (mutates MYSQL_DATABASE from .env.local): make up && make seed[+sample] && make test-e2e
# Isolated stack (app_e2e): make up-e2e && make ready-e2e && make test-e2e-isolated
# Override: PLAYWRIGHT_BASE_URL=https://localhost:9447 make test-e2e
# Filter:  make test-e2e ARGS='e2e/smoke/public.spec.ts'
# Host run (needs `pnpm exec playwright install-deps`): PLAYWRIGHT_ON_HOST=1 make test-e2e
# CI sets PLAYWRIGHT_REQUIRE_SAMPLE=1 so issue-dependent tests fail instead of skip.
PLAYWRIGHT_IMAGE ?= mcr.microsoft.com/playwright:v1.62.1-jammy
PLAYWRIGHT_BASE_URL ?= https://localhost:9447
# Mailpit UI (make mailpit) — used by UC-AUTH-18/20; skip when unreachable unless REQUIRE_MAILPIT=1
PLAYWRIGHT_MAILPIT_URL ?= http://127.0.0.1:18026
PLAYWRIGHT_REQUIRE_MAILPIT ?=
test-e2e: ensure-up
	@$(DC) exec -T php bin/console dbal:run-sql "DELETE FROM login_attempts" >/dev/null 2>&1 || true
	@# UC-NOTIF-17: console/cron flush — capture for Playwright assertion (no browser surface)
	@$(DC) exec -T php sh -c 'mkdir -p var/e2e && bin/console app:notifications:flush-digests --force > var/e2e/flush-digests.last 2>&1'
ifeq ($(PLAYWRIGHT_ON_HOST),1)
	PLAYWRIGHT_MAILPIT_URL="$(PLAYWRIGHT_MAILPIT_URL)" PLAYWRIGHT_REQUIRE_MAILPIT="$(PLAYWRIGHT_REQUIRE_MAILPIT)" pnpm exec playwright test $(ARGS)
else
	docker run --rm --network=host \
		--user "$(shell id -u):$(shell id -g)" \
		-v "$(CURDIR):/work" -w /work \
		-e PLAYWRIGHT_BASE_URL="$(PLAYWRIGHT_BASE_URL)" \
		-e PLAYWRIGHT_MAILPIT_URL="$(PLAYWRIGHT_MAILPIT_URL)" \
		-e PLAYWRIGHT_REQUIRE_SAMPLE="$(PLAYWRIGHT_REQUIRE_SAMPLE)" \
		-e PLAYWRIGHT_REQUIRE_MAILPIT="$(PLAYWRIGHT_REQUIRE_MAILPIT)" \
		-e CI="$(CI)" \
		-e HOME=/tmp \
		-e XDG_CACHE_HOME=/tmp/.cache \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'mkdir -p /tmp/.cache && ./node_modules/.bin/playwright test $(ARGS)'
endif

# --- Isolated E2E stack (schema app_e2e; does not mutate dogfood MYSQL_DATABASE) ---
ensure-e2e-env: ensure-env
	@chmod +x .scripts/ensure-e2e-env.sh
	@E2E_HTTP_PORT="$(E2E_HTTP_PORT)" E2E_HTTPS_PORT="$(E2E_HTTPS_PORT)" \
		E2E_MYSQL_DATABASE="$(E2E_MYSQL_DATABASE)" E2E_REDIS_DB="$(E2E_REDIS_DB)" \
		E2E_BEACON_TARGET="$(E2E_BEACON_TARGET)" \
		E2E_ENV_FILE="$(E2E_ENV_FILE)" ./.scripts/ensure-e2e-env.sh

ensure-e2e-db: up-infra ensure-e2e-env
	@set -a; . ./$(ENV_FILE); set +a; \
	DB="$(E2E_MYSQL_DATABASE)"; \
	echo "Ensuring MySQL schema \`$$DB\` on mysql-9.7-primary…"; \
	docker exec -i -e MYSQL_PWD="$$MYSQL_ROOT_PASSWORD" mysql-9.7-primary \
		mysql -uroot -e "CREATE DATABASE IF NOT EXISTS \`$$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
GRANT ALL PRIVILEGES ON \`$$DB\`.* TO '$${MYSQL_USER}'@'%'; FLUSH PRIVILEGES;"

up-e2e: ensure-e2e-db
	$(DC_E2E) up -d php messenger messenger-notify
	@$(MAKE) print-urls COMPOSE="$(DC_E2E)"
	@echo "Isolated E2E stack: DB=$(E2E_MYSQL_DATABASE)  HTTPS=$(PLAYWRIGHT_E2E_BASE_URL)"
	@echo "Next: make ready-e2e   then   make test-e2e-isolated"

ensure-e2e-up: ensure-e2e-env
	@$(MAKE) up-infra
	@$(DC_E2E) exec -T php true >/dev/null 2>&1 || { \
		echo "E2E stack is down — starting…"; \
		$(MAKE) up-e2e; \
	}

down-e2e:
	@if [ -f "$(E2E_ENV_FILE)" ]; then \
		$(DC_E2E) down; \
	else \
		echo "No $(E2E_ENV_FILE) — nothing to stop (run make up-e2e first)."; \
	fi

seed-e2e: ensure-e2e-up
	@$(DC_E2E) exec -T php sh -c 'mkdir -p var/secrets && chmod 770 var/secrets'
	$(DC_E2E) exec -T php bin/console app:seed-platform
	$(DC_E2E) exec -T php bin/console app:seed-demo \
		--server-env-file=.env.e2e.local \
		--write-client-env=.demo-client.e2e.env \
		--base-url="$(PLAYWRIGHT_E2E_BASE_URL)" \
		--ingest-base-url="http://host.docker.internal:$(E2E_HTTP_PORT)"
	@if [ -f .demo-client.e2e.env ]; then \
		$(DC_E2E) exec -u root -T php chown $(shell id -u):$(shell id -g) /app/.demo-client.e2e.env 2>/dev/null || true; \
		chmod 600 .demo-client.e2e.env 2>/dev/null || true; \
	fi
	@# Re-apply E2E_BEACON_TARGET (self/dogfood/off) and recreate so BeaconBundle picks up BEACON_DSN.
	@$(MAKE) ensure-e2e-env
	$(DC_E2E) up -d --force-recreate php messenger messenger-notify
	@echo "E2E BeaconBundle target=$(E2E_BEACON_TARGET) — errors during tests report via BEACON_DSN in $(E2E_ENV_FILE)"

ready-e2e: ensure-e2e-up
	@$(DC_E2E) exec -T php sh -c 'mkdir -p var/secrets && chmod 770 var/secrets'
	$(DC_E2E) exec -T php bin/console doctrine:migrations:migrate -n
	$(DC_E2E) exec -T php bin/console messenger:setup-transports --no-interaction
	@$(MAKE) seed-e2e
	$(DC_E2E) exec -T php bin/console app:seed-sample --size=$${PROFILE:-dev}
	@echo "E2E DB ready. Run: make test-e2e-isolated"

test-e2e-isolated: ensure-e2e-up
	@$(DC_E2E) exec -T php bin/console dbal:run-sql "DELETE FROM login_attempts" >/dev/null 2>&1 || true
	@$(DC_E2E) exec -T php sh -c 'mkdir -p var/e2e && bin/console app:notifications:flush-digests --force > var/e2e/flush-digests.last 2>&1'
ifeq ($(PLAYWRIGHT_ON_HOST),1)
	PLAYWRIGHT_ISOLATED=1 \
	PLAYWRIGHT_BASE_URL="$(PLAYWRIGHT_E2E_BASE_URL)" \
	PLAYWRIGHT_MAILPIT_URL="$(PLAYWRIGHT_MAILPIT_URL)" \
	PLAYWRIGHT_REQUIRE_MAILPIT="$(PLAYWRIGHT_REQUIRE_MAILPIT)" \
	PLAYWRIGHT_REQUIRE_SAMPLE="$(PLAYWRIGHT_REQUIRE_SAMPLE)" \
	pnpm exec playwright test $(ARGS)
else
	docker run --rm --network=host \
		--user "$(shell id -u):$(shell id -g)" \
		-v "$(CURDIR):/work" -w /work \
		-e PLAYWRIGHT_ISOLATED=1 \
		-e PLAYWRIGHT_BASE_URL="$(PLAYWRIGHT_E2E_BASE_URL)" \
		-e PLAYWRIGHT_MAILPIT_URL="$(PLAYWRIGHT_MAILPIT_URL)" \
		-e PLAYWRIGHT_REQUIRE_SAMPLE="$(PLAYWRIGHT_REQUIRE_SAMPLE)" \
		-e PLAYWRIGHT_REQUIRE_MAILPIT="$(PLAYWRIGHT_REQUIRE_MAILPIT)" \
		-e CI="$(CI)" \
		-e HOME=/tmp \
		-e XDG_CACHE_HOME=/tmp/.cache \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'mkdir -p /tmp/.cache && ./node_modules/.bin/playwright test $(ARGS)'
endif

# Fast AuthKit / identity smoke after kit bumps (see docs/CONTRIBUTING.md).
kit-smoke: ensure-up
	$(DC) exec -T php vendor/bin/phpunit \
		tests/Functional/Identity/AuthKitBootstrapTest.php \
		tests/Functional/Identity/MagicLoginTest.php \
		tests/Functional/Identity/PasswordResetTest.php \
		tests/Functional/Identity/LoginThrottleTest.php

# Hard gate: includable PHPUnit source must stay at COVERAGE_MIN=100; override locally only for diagnosis.
test-coverage: ensure-up
	$(DC) exec -T php sh -c 'rm -rf var/cache/test/* && mkdir -p var/coverage var/coverage-html'
	$(DC) exec -T -e XDEBUG_MODE=coverage php vendor/bin/phpunit \
		--coverage-text \
		--coverage-clover var/coverage/clover.xml \
		--coverage-html var/coverage-html
	$(DC) exec -T -e COVERAGE_MIN="$(COVERAGE_MIN)" php sh .scripts/check-coverage-threshold.sh var/coverage/clover.xml

# Same gate as CI job "Secret scan (Gitleaks)". Requires Docker; pins the CLI version used in .github/workflows/ci.yml.
GITLEAKS_VERSION ?= 8.28.0
secrets-scan:
	docker run --rm -v "$(CURDIR):/repo:ro" -w /repo "zricethezav/gitleaks:v$(GITLEAKS_VERSION)" \
		detect --source . --verbose --redact --exit-code 1

qa: cs twig-cs phpstan rector check-module-boundaries test

# rector-fix re-applies CS Fixer so the tree matches CI `php-cs-fixer check`.
qa-fix: cs-fix twig-cs-fix phpstan rector-fix test

# Bump exact pins via composer-update-helper, then refresh lockfiles (Composer + pnpm).
update-deps: ensure-up
	-$(DC) exec -T php bash ./generate-composer-require.sh --run
	$(DC) exec -T php composer update
	$(DC) exec -T php pnpm update
	@echo "Dependencies updated. Consider: make vite-build && make qa"

# Suggest pinned composer require commands for outdated direct deps (runs in php container).
# The helper may exit non-zero when outdated packages are found; still print suggestions.
composer-outdated: ensure-up
	-$(DC) exec -T php bash ./generate-composer-require.sh
