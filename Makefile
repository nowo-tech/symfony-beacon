.PHONY: help up down build build-prod logs shell console seed seed-platform seed-sample dogfood bootstrap ready classic worker restart mysql messenger-logs messenger-ping vite vite-hmr vite-build vite-watch pnpm mailpit mailpit-logs specify-check \
	cs cs-fix twig-cs twig-cs-fix phpstan rector rector-fix test test-coverage kit-smoke qa qa-fix secrets-scan composer-outdated update-deps \
	setup-hooks check-no-cursor-coauthor strip-cursor-coauthor-from-history check-envelope-goldens ensure-halite-secrets print-urls

help:
	@echo "symfony-beacon — self-hosted error tracking (Symfony 8.1 + FrankenPHP + MySQL 9.7)"
	@echo ""
	@echo "  make up              Start stack (php + mysql + messenger) + vite-build; prints HTTP/HTTPS ports"
	@echo "  make classic         FrankenPHP HTTP in classic mode"
	@echo "  make worker          FrankenPHP HTTP in worker mode"
	@echo "  make down            Stop containers"
	@echo "  make build           Rebuild the php image (dev)"
	@echo "  make build-prod      Build frankenphp_prod image (see docs/PRODUCTION.md)"
	@echo "  make logs            Follow php service logs"
	@echo "  make vite-hmr        Start Vite HMR (compose profile hmr)"
	@echo "  make vite            Follow Vite HMR logs"
	@echo "  make vite-build      pnpm run build (one-shot → public/build/)"
	@echo "  make vite-watch      pnpm run watch (vite build --watch, no HMR)"
	@echo "  make pnpm            pnpm in php container (ARGS='install' / 'add -D …')"
	@echo "  make mailpit         Start Mailpit (compose profile mail) for local SMTP; prints UI URL"
	@echo "  make mailpit-logs    Follow Mailpit logs"
	@echo "  make messenger-logs  Follow Messenger worker logs"
	@echo "  make mysql           mysql CLI shell"
	@echo "  make shell           Shell in the php container"
	@echo "  make console         bin/console (ARGS='...')"
	@echo "  make seed-platform   Upsert menus/breadcrumbs/cookie consent (safe after upgrades)"
	@echo "  make seed            Platform seed + demo user/project + .demo-client.env + server BEACON_DSN"
	@echo "  make seed-sample     Sample telemetry (PROFILE=dev|load|huge)"
	@echo "  make dogfood         Symfony Beacon project + ROLE_ADMIN access + BEACON_DSN (no new user)"
	@echo "  make bootstrap       Migrate DB + platform seed (after make up)"
	@echo "  make ready           bootstrap + seed (recommended first local run / dogfooding)"
	@echo "  make restart         Restart php + messenger"
	@echo "  make specify-check   Verify Specify CLI"
	@echo ""
	@echo "Quality:"
	@echo "  make cs              PHP-CS-Fixer (check)"
	@echo "  make cs-fix          PHP-CS-Fixer (fix)"
	@echo "  make twig-cs         Twig-CS-Fixer (lint)"
	@echo "  make twig-cs-fix     Twig-CS-Fixer (fix)"
	@echo "  make phpstan         PHPStan analyse"
	@echo "  make rector          Rector dry-run"
	@echo "  make rector-fix      Rector apply"
	@echo "  make test            PHPUnit"
	@echo "  make test-coverage   PHPUnit + Clover/HTML (var/coverage*); optional COVERAGE_MIN=N"
	@echo "  make kit-smoke       AuthKit smoke (login, magic login, password reset, throttle)"
	@echo "  make secrets-scan    Gitleaks secret scan (same gate as CI)"
	@echo "  make qa              cs + twig-cs + phpstan + rector + test"
	@echo "  make qa-fix          cs-fix + twig-cs-fix + phpstan + rector-fix + test"
	@echo "  make update-deps     composer update + pnpm update (in php container)"
	@echo "  make composer-outdated  Suggest composer require pins (nowo-tech/composer-update-helper)"
	@echo ""
	@echo "Git hygiene:"
	@echo "  make setup-hooks                    Install .githooks (strips Cursor co-authors)"
	@echo "  make check-no-cursor-coauthor       Fail if Cursor trailers exist in history"
	@echo "  make check-envelope-goldens         Diff Envelope fixtures vs sibling BeaconBundle"
	@echo "  make strip-cursor-coauthor-from-history  Rewrite local history to remove them"

setup-hooks:
	@chmod +x .githooks/commit-msg .githooks/prepare-commit-msg .scripts/check-no-cursor-coauthor.sh .scripts/strip-cursor-coauthor-from-history.sh
	@git config core.hooksPath .githooks
	@echo "✅ Git hooks installed (.githooks — strips Cursor Co-authored-by / Made-with trailers)."

check-no-cursor-coauthor:
	@chmod +x .scripts/check-no-cursor-coauthor.sh
	@./.scripts/check-no-cursor-coauthor.sh HEAD

check-envelope-goldens:
	@chmod +x .scripts/check-envelope-goldens.sh
	@./.scripts/check-envelope-goldens.sh

strip-cursor-coauthor-from-history:
	@chmod +x .scripts/strip-cursor-coauthor-from-history.sh
	@./.scripts/strip-cursor-coauthor-from-history.sh main

# Print published app ports (from running compose, else .env / defaults).
print-urls:
	@HTTP_PUB=$$(docker compose port php 80 2>/dev/null | head -1 | sed 's/.*://'); \
	HTTPS_PUB=$$(docker compose port php 443 2>/dev/null | head -1 | sed 's/.*://'); \
	if [ -z "$$HTTP_PUB" ]; then HTTP_PUB=$$(grep -E '^HTTP_PORT=' .env 2>/dev/null | cut -d= -f2-); fi; \
	if [ -z "$$HTTPS_PUB" ]; then HTTPS_PUB=$$(grep -E '^HTTPS_PORT=' .env 2>/dev/null | cut -d= -f2-); fi; \
	HTTP_PUB=$${HTTP_PUB:-9081}; \
	HTTPS_PUB=$${HTTPS_PUB:-9444}; \
	echo ""; \
	echo "Beacon is up:"; \
	echo "  HTTP:  http://localhost:$${HTTP_PUB}"; \
	echo "  HTTPS: https://localhost:$${HTTPS_PUB}"; \
	MAILPIT_UI=$$(docker compose --profile mail port mailer 8025 2>/dev/null | head -1 | sed 's/.*://'); \
	if [ -n "$$MAILPIT_UI" ]; then \
		echo "  Mailpit UI: http://localhost:$${MAILPIT_UI}  (SMTP from PHP: smtp://mailer:1025)"; \
	fi

up:
	@test -f .env || (cp .env.dist .env && echo "Created .env from .env.dist")
	docker compose up --build -d
	@echo "Building frontend assets (static public/build/)…"
	@$(MAKE) vite-build
	@$(MAKE) print-urls
	@echo "Optional local SMTP: make mailpit  (see docs/ops/MAILPIT.md)"

classic:
	@test -f .env || cp .env.dist .env
	FRANKENPHP_MODE=classic docker compose up --build -d
	@$(MAKE) vite-build
	@$(MAKE) print-urls

worker:
	@test -f .env || cp .env.dist .env
	FRANKENPHP_MODE=worker docker compose up --build -d
	@$(MAKE) vite-build
	@$(MAKE) print-urls

down:
	docker compose --profile hmr --profile mail down

build:
	docker compose build --no-cache

build-prod:
	docker build --target frankenphp_prod -t ${IMAGES_PREFIX:-}symfony-beacon:prod .

logs:
	docker compose logs -f php

vite-hmr:
	docker compose --profile hmr up -d vite
	@echo "Vite HMR is on — entrypoints use viteServer (browser shows a pending HMR WebSocket)."
	@echo "For stable UI without HMR: docker compose --profile hmr stop vite && make vite-build"

vite:
	docker compose logs -f vite

vite-build:
	docker compose exec -T php pnpm run build

vite-watch:
	docker compose exec php pnpm run watch

pnpm:
	docker compose exec -T php pnpm $(ARGS)

# Local SMTP catcher (Mailpit). Dev only — not started by `make up`; not in compose.prod.yaml.
# Docs: docs/ops/MAILPIT.md — save smtp://mailer:1025 under Administration → Mailer, then Send sample.
mailpit:
	@test -f .env || (cp .env.dist .env && echo "Created .env from .env.dist")
	docker compose --profile mail up -d mailer
	@UI_PUB=$$(docker compose --profile mail port mailer 8025 2>/dev/null | head -1 | sed 's/.*://'); \
	UI_PUB=$${UI_PUB:-18025}; \
	echo ""; \
	echo "Mailpit is up (dev/test only — not used in production):"; \
	echo "  UI:   http://localhost:$${UI_PUB}"; \
	echo "  SMTP (from PHP container): smtp://mailer:1025"; \
	echo "  Save that DSN under Administration → Mailer, then use Send sample email."; \
	echo "  Docs: docs/ops/MAILPIT.md"

mailpit-logs:
	docker compose --profile mail logs -f mailer

messenger-logs:
	docker compose logs -f messenger

shell:
	docker compose exec php sh

console:
	docker compose exec php bin/console $(ARGS)

# Halite key file lives under var/secrets/; the encrypt bundle does not mkdir for you.
ensure-halite-secrets:
	docker compose exec -T php mkdir -p var/secrets

seed-platform: ensure-halite-secrets
	docker compose exec -T php bin/console app:seed-platform

seed: seed-platform
	docker compose exec -T php bin/console app:seed-demo
	@echo "Client env: .demo-client.env — in BeaconBundle/demo/symfony8 run: make sync-beacon"
	@echo "Server dogfood: BEACON_DSN set in .env when empty (loopback 127.0.0.1)"
	@echo "Optional samples: make seed-sample   (or PROFILE=load / PROFILE=huge)"

seed-sample: ensure-halite-secrets
	docker compose exec -T php bin/console app:seed-sample --size=$${PROFILE:-dev}

# Ensure demo project + API key exist, grant ROLE_ADMIN membership, write .demo-client.env,
# and set server BEACON_DSN (loopback) when empty. Does not create a demo admin user.
dogfood: ensure-halite-secrets
	docker compose exec -T php bin/console app:seed-demo --skip-demo-user
	@echo "Dogfood: BEACON_DSN is written only when empty. If it changed, run: make restart"

bootstrap: ensure-halite-secrets
	docker compose exec -T php bin/console doctrine:migrations:migrate -n
	@$(MAKE) seed-platform
	@docker compose exec -T php sh -c 'mkdir -p var/site-backup && touch var/site-backup/setup.done'
	@echo "Next: make seed (or make ready) for demo user + dogfood DSN — or open /setup / register"

ready: bootstrap seed
	@echo "Ready: demo project seeded; restart php if BEACON_DSN was just written (make restart)"
	@echo "Ops panel: /_site_backup  |  Setup wizard: /setup"

restart:
	docker compose restart php messenger
	@$(MAKE) vite-build

mysql:
	docker compose exec database mysql -u$${MYSQL_USER:-app} -p$${MYSQL_PASSWORD:-!ChangeMe!} $${MYSQL_DATABASE:-app}

specify-check:
	@command -v specify >/dev/null || { echo "Install Specify: uv tool install specify-cli"; exit 1; }
	specify check
	@echo "Constitution: .specify/memory/constitution.md"
	@echo "Cursor skills: .cursor/skills/"
	@echo "Specs: specs/"

cs:
	docker compose exec -T php vendor/bin/php-cs-fixer check --diff

cs-fix:
	docker compose exec -T php vendor/bin/php-cs-fixer fix

twig-cs:
	docker compose exec -T php vendor/bin/twig-cs-fixer lint --config=.twig-cs-fixer.dist.php

twig-cs-fix:
	docker compose exec -T php vendor/bin/twig-cs-fixer fix --config=.twig-cs-fixer.dist.php

phpstan:
	docker compose exec -T php bin/console cache:warmup --env=dev
	docker compose exec -T php vendor/bin/phpstan analyse --memory-limit=512M -c phpstan.neon.dist

rector:
	docker compose exec -T php vendor/bin/rector process --dry-run

rector-fix:
	docker compose exec -T php vendor/bin/rector process

test:
	docker compose exec -T php vendor/bin/phpunit

# Fast AuthKit / identity smoke after kit bumps (see docs/CONTRIBUTING.md).
kit-smoke:
	docker compose exec -T php vendor/bin/phpunit \
		tests/Identity/AuthKitBootstrapTest.php \
		tests/Identity/MagicLoginTest.php \
		tests/Identity/PasswordResetTest.php \
		tests/Identity/LoginThrottleTest.php

# Soft gate: COVERAGE_MIN defaults in CI; override locally e.g. COVERAGE_MIN=35 make test-coverage
test-coverage:
	docker compose exec -T php mkdir -p var/coverage var/coverage-html
	docker compose exec -T -e XDEBUG_MODE=coverage php vendor/bin/phpunit \
		--coverage-text \
		--coverage-clover var/coverage/clover.xml \
		--coverage-html var/coverage-html
	docker compose exec -T -e COVERAGE_MIN="$(COVERAGE_MIN)" php sh .scripts/check-coverage-threshold.sh var/coverage/clover.xml

# Same gate as CI job "Secret scan (Gitleaks)". Requires Docker; pins the CLI version used in .github/workflows/ci.yml.
GITLEAKS_VERSION ?= 8.28.0
secrets-scan:
	docker run --rm -v "$(CURDIR):/repo:ro" -w /repo "zricethezav/gitleaks:v$(GITLEAKS_VERSION)" \
		detect --source . --verbose --redact --exit-code 1

qa: cs twig-cs phpstan rector test

qa-fix: cs-fix twig-cs-fix phpstan rector-fix test

# Update PHP (Composer) and frontend (pnpm) lockfiles within constraint ranges.
update-deps:
	docker compose exec -T php composer update
	docker compose exec -T php pnpm update
	@echo "Dependencies updated. Consider: make vite-build && make qa"

# Suggest pinned composer require commands for outdated direct deps (runs in php container).
# The helper may exit non-zero when outdated packages are found; still print suggestions.
composer-outdated:
	-docker compose exec -T php bash ./generate-composer-require.sh
