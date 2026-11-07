# Developer and quality-gate commands. Everything runs inside the Docker containers; the host only needs Docker
# (with the Compose plugin) and make.

COMPOSE ?= docker compose
HOST_UID ?= $(shell id -u)
HOST_GID ?= $(shell id -g)
export HOST_UID HOST_GID

APP := $(COMPOSE) exec -T app

.DEFAULT_GOAL := help
.PHONY: help up down logs shell migrate seed fresh test lint fix analyse check

help: ## List the available targets
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk -F ':.*## ' '{printf "  %-10s %s\n", $$1, $$2}'

.env:
	cp .env.example .env

up: .env ## Build and start the stack, wait until every service is healthy
	$(COMPOSE) up -d --build --wait

down: ## Stop the stack (the database volume is kept)
	$(COMPOSE) down

logs: ## Follow the logs of every service
	$(COMPOSE) logs -f

shell: up ## Open a shell in the app container
	$(COMPOSE) exec app sh

migrate: up ## Run the database migrations
	$(APP) php artisan migrate --force

seed: up ## Load the demo data
	$(APP) php artisan db:seed --force

fresh: up ## Rebuild the database from the migrations and load the demo data
	$(APP) php artisan migrate:fresh --seed --force

test: up ## Run the PHPUnit test suite
	$(APP) composer test

lint: up ## Check the code style (Pint with the PSR-12 preset, PHP_CodeSniffer PSR12)
	$(APP) composer lint

fix: up ## Fix the code style with Pint
	$(APP) composer fix

analyse: up ## Run static analysis (Larastan, level max)
	$(APP) composer analyse

check: up ## Quality gate: code style, static analysis and tests
	$(APP) composer check
