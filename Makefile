.PHONY: bootstrap up down build logs sh install migrate fresh test phpstan cs cs-fix check

## One-command from-scratch bring-up. Run this after `git clone`.
##
## Brings the full stack up, installs PHP dependencies into the running app
## container, applies migrations, and restarts worker-outbox (which races
## migrations on first boot and crashes if tables don't exist yet).
bootstrap:
	@echo "→ Building images and starting infrastructure (first run downloads images and builds the app — can take a minute)..."
	docker compose up -d --build
	@echo ""
	@echo "→ Waiting for postgres to accept connections..."
	@until docker compose exec -T postgres pg_isready -U app -d cards >/dev/null 2>&1; do sleep 1; done
	@echo "→ Installing PHP dependencies into the app container..."
	docker compose exec app composer install --no-interaction
	@echo ""
	@echo "→ Applying database migrations (dev)..."
	docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
	@echo ""
	@echo "→ Creating and migrating the test database (cards_test)..."
	docker compose exec app php bin/console --env=test doctrine:database:create --if-not-exists
	docker compose exec app php bin/console --env=test doctrine:migrations:migrate --no-interaction --allow-no-migration
	@echo ""
	@echo "→ Restarting worker-outbox (it boots before migrations and exits when outbox_events is missing)..."
	docker compose up -d worker-outbox
	@echo ""
	@echo "✓ Sentinel is ready."
	@echo "    API console:   http://localhost:8000/docs"
	@echo "    Mock receiver: http://localhost:8888/"
	@echo ""
	@echo "  Run 'make check' to confirm the test suite passes."

## Container lifecycle
up:
	docker compose up -d --build

down:
	docker compose down

build:
	docker compose build

logs:
	docker compose logs -f app

sh:
	docker compose exec app sh

## Application
install:
	docker compose exec app composer install

migrate:
	docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

fresh:
	docker compose exec app php bin/console doctrine:database:drop --force --if-exists
	docker compose exec app php bin/console doctrine:database:create
	docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

## Quality gates
test:
	docker compose exec app vendor/bin/phpunit

phpstan:
	docker compose exec app php bin/console cache:clear
	docker compose exec app vendor/bin/phpstan analyse --no-progress --memory-limit=512M

cs:
	docker compose exec app vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix:
	docker compose exec app vendor/bin/php-cs-fixer fix

check: phpstan cs test
