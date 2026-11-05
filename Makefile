.DEFAULT_GOAL := help

COMPOSE := docker compose
APP := $(COMPOSE) exec app

.PHONY: help setup up down shell migrate fresh test lint fix analyse check docs traffic

help: ## List the available targets
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-8s\033[0m %s\n", $$1, $$2}'

setup: ## First start: build and start the containers, install, configure, migrate and seed
	@test -f .env || cp .env.example .env
	APP_UID=$$(id -u) APP_GID=$$(id -g) $(COMPOSE) build
	$(COMPOSE) up -d
	$(APP) composer install --no-interaction
	@grep -q '^APP_KEY=base64:' .env || $(APP) php artisan key:generate
	$(APP) php artisan migrate --seed --force

up: ## Start the containers
	$(COMPOSE) up -d

down: ## Stop the containers
	$(COMPOSE) down

shell: ## Open a shell in the application container
	$(APP) sh

migrate: ## Run the pending database migrations
	$(APP) php artisan migrate

fresh: ## Rebuild the database from the migrations and seed the demo data
	$(APP) php artisan migrate:fresh --seed

test: ## Run the test suite (dedicated MySQL test database)
	$(APP) composer test

lint: ## Check the coding style (PSR-12: Pint + PHP_CodeSniffer)
	$(APP) composer lint

fix: ## Fix the coding style with Pint
	$(APP) composer fix

analyse: ## Run the static analysis (Larastan, max level)
	$(APP) composer analyse

check: ## Run every quality gate (composer validate, PSR-4, PSR-12, static analysis, tests)
	$(APP) composer check

docs: ## Export the OpenAPI specification to docs/openapi.json
	$(APP) composer docs:export

traffic: ## Show the latest API traffic log entries
	$(COMPOSE) exec mysql sh -c 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE" -e "SELECT id, created_at, request_id, method, url, response_status, duration_ms FROM api_request_logs ORDER BY id DESC LIMIT 20"'
