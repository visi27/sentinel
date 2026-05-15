.PHONY: up down build logs sh install migrate fresh test phpstan cs cs-fix check

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
