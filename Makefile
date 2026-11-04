.DEFAULT_GOAL := help

.PHONY: help up down restart build logs ps shell test migrate artisan composer

help:
	@printf '%s\n' \
		'make up        собрать и поднять контейнеры' \
		'make down      остановить и убрать контейнеры' \
		'make restart   перезапустить сервисы' \
		'make build     пересобрать образы' \
		'make logs      хвост логов' \
		'make ps        статус' \
		'make shell     оболочка в контейнере app' \
		'make test      php artisan test' \
		'make migrate   php artisan migrate --force' \
		'make artisan cmd="migrate:status"   команда Artisan' \
		'make composer cmd="install"  команда Composer'

up:
	docker compose up -d --build

down:
	docker compose down

restart:
	docker compose restart

build:
	docker compose build

logs:
	docker compose logs -f --tail=100

ps:
	docker compose ps

shell:
	docker compose exec app bash

test:
	docker compose exec -T app php artisan test

migrate:
	docker compose exec -T app php artisan migrate --force

artisan:
	docker compose exec -T app php artisan $(cmd)

composer:
	docker compose exec app composer $(cmd)
