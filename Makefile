.DEFAULT_GOAL := help

# Docker compose convenience target
DC = docker compose

.PHONY: help
help: ## Show this help.
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage: make \033[36m<target>\033[0m\n\nTargets:\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

.PHONY: init
init: ## Copy .env.example to .env and create library/ folder if missing.
	@test -f .env || cp .env.example .env
	@mkdir -p library

.PHONY: build
build: init ## Build all Docker images.
	$(DC) build

.PHONY: install
install: build ## Bootstrap the Laravel application (composer create-project + dependencies + migrations + assets).
	./scripts/install.sh

.PHONY: up
up: ## Start the application stack in the background.
	$(DC) up -d app web db redis worker

.PHONY: dev
dev: ## Start the stack with the Vite dev server (foreground for the node container).
	$(DC) --profile dev up app web db redis worker node

.PHONY: horizon
horizon: ## Tail the Horizon worker logs.
	$(DC) logs -f worker

.PHONY: horizon-restart
horizon-restart: ## Tell Horizon to gracefully restart its workers (picks up code changes).
	$(DC) exec app php artisan horizon:terminate

.PHONY: down
down: ## Stop and remove containers (preserves volumes).
	$(DC) down

.PHONY: nuke
nuke: ## Stop containers and remove volumes (destroys DB + uploaded books).
	$(DC) down -v

.PHONY: shell
shell: ## Open a shell inside the PHP container.
	$(DC) exec app bash

.PHONY: artisan
artisan: ## Run an artisan command, e.g. `make artisan c="migrate --seed"`.
	$(DC) exec app php artisan $(c)

.PHONY: composer
composer: ## Run a composer command, e.g. `make composer c="require foo/bar"`.
	$(DC) exec app composer $(c)

.PHONY: npm
npm: ## Run an npm command in the app container, e.g. `make npm c="run build"`.
	$(DC) exec app npm $(c)

.PHONY: test
test: ## Run the test suite inside the container.
	$(DC) exec app php artisan test

.PHONY: dusk-setup
dusk-setup: ## Create the `library_dusk` DB AND build Vite assets — required before `make dusk`.
	# 1. The MySQL init script only runs when the data volume is first
	#    created, so for an existing dev environment we have to create
	#    the DB by hand. Idempotent — safe to re-run.
	$(DC) exec db sh -c "mysql -uroot -p$$(printenv DB_ROOT_PASSWORD 2>/dev/null || echo root) \
		-e 'CREATE DATABASE IF NOT EXISTS library_dusk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
		    GRANT ALL PRIVILEGES ON library_dusk.* TO \"library\"@\"%\"; FLUSH PRIVILEGES;'"
	# 2. Dusk renders the real Blade root view, which calls @vite(). Without
	#    public/build/manifest.json that directive throws — Chromium sees
	#    a Laravel error page instead of the SPA, and `type('email',…)`
	#    fails with "no such element". Make sure the build artefacts exist.
	$(DC) exec app npm run build

.PHONY: dusk
dusk: ## Run the Dusk browser tests inside the PHP container.
	# Has to run inside `app` so DUSK_DRIVER_URL=http://localhost:9515
	# resolves to the chromedriver we install in docker/php/Dockerfile —
	# from the host it would not be reachable. The .env.dusk.local file
	# overrides APP_URL to http://web so the browser hits nginx on the
	# docker-compose network instead of localhost (where nothing listens
	# inside `app`).
	$(DC) exec app php artisan dusk $(c)

.PHONY: migrate
migrate: ## Run database migrations.
	$(DC) exec app php artisan migrate

.PHONY: seed
seed: ## Run database seeders.
	$(DC) exec app php artisan db:seed

.PHONY: fresh
fresh: ## Drop all tables, re-migrate, and re-seed.
	$(DC) exec app php artisan migrate:fresh --seed

.PHONY: logs
logs: ## Tail logs from all containers.
	$(DC) logs -f

.PHONY: pint
pint: ## Run Laravel Pint code style fixer. Pass arguments with `c=`, e.g. `make pint c="--test"`.
	$(DC) exec app vendor/bin/pint $(c)
