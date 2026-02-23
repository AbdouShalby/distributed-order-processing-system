.PHONY: up down build restart logs fresh seed test lint worker

# ==========================================
# Docker Commands
# ==========================================

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache

restart:
	docker compose restart

logs:
	docker compose logs -f

logs-app:
	docker compose logs -f app

logs-worker:
	docker compose logs -f worker

logs-reverb:
	docker compose logs -f reverb

# ==========================================
# Application Commands
# ==========================================

install:
	docker compose exec app composer install

migrate:
	docker compose exec app php artisan migrate

fresh:
	docker compose exec app php artisan migrate:fresh --seed

seed:
	docker compose exec app php artisan db:seed

# ==========================================
# Development
# ==========================================

tinker:
	docker compose exec app php artisan tinker

shell:
	docker compose exec app sh

worker:
	docker compose exec app php artisan queue:work redis --tries=3 --backoff=1,3,5

# ==========================================
# Testing
# ==========================================

test:
	docker compose exec app php artisan test

test-unit:
	docker compose exec app php artisan test --testsuite=Unit

test-feature:
	docker compose exec app php artisan test --testsuite=Feature

lint:
	docker compose exec app ./vendor/bin/pint

lint-check:
	docker compose exec app ./vendor/bin/pint --test

# ==========================================
# Health
# ==========================================

health:
	curl -s http://localhost/api/health | jq .

# ==========================================
# Setup (first time)
# ==========================================

setup: build up
	docker compose exec app composer install
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate:fresh --seed
	@echo "✅ Setup complete! API available at http://localhost"
