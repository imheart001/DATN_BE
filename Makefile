.PHONY: init-env up down logs shell composer-install key migrate seed setup

init-env:
	test -f .env || cp .env.docker.example .env

up:
	$(MAKE) init-env
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f --tail=200

shell:
	docker compose exec app sh

composer-install:
	docker compose exec app composer install

key:
	docker compose exec app php artisan key:generate --force

migrate:
	docker compose exec app php artisan migrate --force

seed:
	docker compose exec app php artisan db:seed --force

setup:
	$(MAKE) init-env
	docker compose up -d --build
	docker compose exec app composer install
	docker compose exec app php artisan key:generate --force
	docker compose exec app php artisan storage:link
	docker compose exec app php artisan migrate --force
	docker compose exec app php artisan db:seed --force
