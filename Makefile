.PHONY: help up down build build-prod logs shell console seed bootstrap classic worker restart mysql messenger-logs messenger-ping vite vite-hmr vite-build vite-watch pnpm specify-check \
	cs cs-fix twig-cs twig-cs-fix phpstan rector rector-fix test test-coverage qa composer-outdated \
	setup-hooks check-no-cursor-coauthor strip-cursor-coauthor-from-history check-envelope-goldens

help:
	@echo "symfony-beacon — self-hosted error tracking (Symfony 8.1 + FrankenPHP + MySQL 9.7)"
	@echo ""
	@echo "  make up              Start stack (php + mysql + messenger) + vite-build"
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
	@echo "  make messenger-logs  Follow Messenger worker logs"
	@echo "  make mysql           mysql CLI shell"
	@echo "  make shell           Shell in the php container"
	@echo "  make console         bin/console (ARGS='...')"
	@echo "  make seed            Seed demo user + project + write .demo-client.env"
	@echo "  make bootstrap       Migrate DB + seed (after make up)"
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
	@echo "  make qa              cs + twig-cs + phpstan + rector + test"
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

up:
	@test -f .env || (cp .env.dist .env && echo "Created .env from .env.dist")
	docker compose up --build -d
	@echo "Building frontend assets (static public/build/)…"
	@$(MAKE) vite-build

classic:
	@test -f .env || cp .env.dist .env
	FRANKENPHP_MODE=classic docker compose up --build -d
	@$(MAKE) vite-build

worker:
	@test -f .env || cp .env.dist .env
	FRANKENPHP_MODE=worker docker compose up --build -d
	@$(MAKE) vite-build

down:
	docker compose --profile hmr down

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

messenger-logs:
	docker compose logs -f messenger

shell:
	docker compose exec php sh

console:
	docker compose exec php bin/console $(ARGS)

seed:
	docker compose exec -T php bin/console app:seed-demo
	@echo "Client env: .demo-client.env — in BeaconBundle/demo/symfony8 run: make sync-beacon"

bootstrap:
	docker compose exec -T php bin/console doctrine:migrations:migrate -n
	@$(MAKE) seed

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

test-coverage:
	docker compose exec -T -e XDEBUG_MODE=coverage php vendor/bin/phpunit --coverage-text --coverage-html var/coverage-html

qa: cs twig-cs phpstan rector test

# Suggest pinned composer require commands for outdated direct deps (runs in php container).
# The helper may exit non-zero when outdated packages are found; still print suggestions.
composer-outdated:
	-docker compose exec -T php bash ./generate-composer-require.sh
