# Import Docker env vars so they're available to both Compose and Makefile targets.
include .docker/.env
export

# Auto-detect host UID/GID so files written from inside the app container are
# owned by the developer on Linux. Override by setting HOST_UID / HOST_GID.
HOST_UID ?= $(shell id -u)
HOST_GID ?= $(shell id -g)

NETWORK  := phantom-braking
DC       := docker compose -f .dev/docker-compose.yml
PHP      := $(DC) exec --user www-data php
PHP_ROOT := $(DC) exec --user root php

TRAEFIK_CERT := ~/.traefik/certs/$(PROJECT_URL).pem
TRAEFIK_KEY  := ~/.traefik/certs/$(PROJECT_URL)-key.pem

.DEFAULT_GOAL := help

.PHONY: help traefik traefik-setup up down stop start build rebuild restart logs ps shell bash \
        composer composer-require composer-remove console migrate migration db-setup cache translations worker \
        seed media admin s3-bucket media-import asset asset-prod asset-tailwind asset-tailwind-prod asset-compile \
        test test-phpstan test-unit test-functional test-e2e test-coverage cs-fix pre-commit install \
        admin-link deploy deploy-rollback deploy-unlock

help: ## Show this help
	@awk 'BEGIN {FS = ":.*?## "; printf "\n  ⚠  Meldpunt spook-remmen\n     Phantom braking reporting point\n\nUsage: make <target>\n\nTargets:\n"} \
		/^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-22s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# ---------- traefik ----------
# Traefik is shared between projects. Every project brings its own certificate
# (signed by the shared root CA) and registers it in ~/.traefik/dynamic.yaml.

traefik-setup: ## One-time: root CA (if missing) + a certificate for this project in ~/.traefik
	@mkdir -p ~/.traefik/certs
	@if [ ! -f ~/.traefik/certs/rootCA.pem ]; then \
		echo "Generating root CA..." && \
		openssl genrsa -out ~/.traefik/certs/rootCA-key.pem 4096 && \
		openssl req -x509 -new -nodes -key ~/.traefik/certs/rootCA-key.pem \
			-sha256 -days 3650 -out ~/.traefik/certs/rootCA.pem \
			-subj "/CN=Traefik Local CA" && \
		echo "" && \
		echo "Trust the root CA on your Mac:" && \
		echo "  sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain ~/.traefik/certs/rootCA.pem" && \
		echo "" ; \
	else \
		echo "Root CA already exists at ~/.traefik/certs/rootCA.pem"; \
	fi
	@if [ ! -f $(TRAEFIK_CERT) ]; then \
		echo "Generating certificate for $(PROJECT_URL) and *.$(PROJECT_URL)..." && \
		openssl genrsa -out $(TRAEFIK_KEY) 2048 && \
		openssl req -new -key $(TRAEFIK_KEY) -out /tmp/traefik-$(PROJECT_URL).csr -subj "/CN=$(PROJECT_URL)" && \
		printf "subjectAltName=DNS:$(PROJECT_URL),DNS:*.$(PROJECT_URL)" > /tmp/traefik-$(PROJECT_URL)-ext.cnf && \
		openssl x509 -req -in /tmp/traefik-$(PROJECT_URL).csr \
			-CA ~/.traefik/certs/rootCA.pem -CAkey ~/.traefik/certs/rootCA-key.pem \
			-CAcreateserial -out $(TRAEFIK_CERT) \
			-days 825 -sha256 -extfile /tmp/traefik-$(PROJECT_URL)-ext.cnf && \
		rm -f /tmp/traefik-$(PROJECT_URL).csr /tmp/traefik-$(PROJECT_URL)-ext.cnf; \
	else \
		echo "Certificate for $(PROJECT_URL) already exists"; \
	fi
	@if [ ! -f ~/.traefik/dynamic.yaml ]; then \
		printf 'tls:\n  stores:\n    default:\n      defaultCertificate:\n        certFile: /etc/traefik/certs/$(PROJECT_URL).pem\n        keyFile: /etc/traefik/certs/$(PROJECT_URL)-key.pem\n  certificates:\n' \
			> ~/.traefik/dynamic.yaml && \
		echo "Created ~/.traefik/dynamic.yaml"; \
	fi
	@# Appends to the certificates list, which must be the last block of dynamic.yaml (as generated above).
	@if ! grep -q '/etc/traefik/certs/$(PROJECT_URL).pem' ~/.traefik/dynamic.yaml; then \
		grep -q '^  certificates:' ~/.traefik/dynamic.yaml || printf '  certificates:\n' >> ~/.traefik/dynamic.yaml; \
		printf '    - certFile: /etc/traefik/certs/$(PROJECT_URL).pem\n      keyFile: /etc/traefik/certs/$(PROJECT_URL)-key.pem\n' >> ~/.traefik/dynamic.yaml && \
		echo "Registered $(PROJECT_URL) certificate in ~/.traefik/dynamic.yaml"; \
	fi

traefik: ## Start a standalone Traefik reverse proxy with SSL (if not already running)
	@if docker ps --format '{{.Names}}' | grep -q '^traefik$$'; then \
		echo "Traefik is already running"; \
	else \
		docker rm -f traefik >/dev/null 2>&1 || true; \
		docker run -d --name traefik --restart unless-stopped \
			-p 80:80 -p 443:443 -p 8080:8080 \
			-v /var/run/docker.sock:/var/run/docker.sock:ro \
			-v ~/.traefik/certs:/etc/traefik/certs:ro \
			-v ~/.traefik/dynamic.yaml:/etc/traefik/dynamic.yaml:ro \
			traefik:latest \
			--api.insecure=true \
			--providers.docker=true \
			--providers.docker.exposedByDefault=false \
			--providers.file.filename=/etc/traefik/dynamic.yaml \
			--entrypoints.web.address=:80 \
			--entrypoints.websecure.address=:443 \
			--entrypoints.web.http.redirections.entrypoint.to=websecure \
			--entrypoints.web.http.redirections.entrypoint.scheme=https; \
	fi

# ---------- lifecycle ----------

up: traefik ## Start the stack (starts Traefik first)
	$(DC) up -d
	@docker network connect $(NETWORK) traefik 2>/dev/null || true
	$(PHP_ROOT) sh -c "mkdir -p /app/var/cache /app/var/log /app/var/storage && chmod -R 777 /app/var/cache /app/var/log /app/var/storage"
	@echo "Project viewable on  https://$(PROJECT_URL)/"

down: ## Stop and remove containers (volumes preserved)
	$(DC) down

stop: ## Stop containers without removing them
	$(DC) stop

start: ## Start previously stopped containers
	$(DC) start

restart: ## Restart all services
	$(DC) restart

build: ## Build images
	$(DC) build --build-arg USERID=$(HOST_UID) --build-arg GROUPID=$(HOST_GID)

rebuild: ## Rebuild images without cache
	$(DC) build --no-cache --build-arg USERID=$(HOST_UID) --build-arg GROUPID=$(HOST_GID)

ps: ## Show running services
	$(DC) ps

logs: ## Tail logs from all services
	$(DC) logs -f --tail=200

# ---------- shells ----------

shell: ## Open a /bin/sh shell in the php container
	$(DC) exec php /bin/sh

bash: ## Open a /bin/bash shell in the php container
	$(DC) exec php /bin/bash

# ---------- app commands (run inside the php container) ----------

composer: ## Run composer install
	$(PHP) sh -c "composer install"

composer-require: ## Add a dev dependency, e.g. make composer-require pkg="vendor/package"
	$(PHP) sh -c "composer require --dev $(pkg)"

composer-remove: ## Remove a dependency, e.g. make composer-remove pkg="vendor/package"
	$(PHP) sh -c "composer remove $(pkg)"

console: ## Run a Symfony console command, e.g. make console cmd="cache:clear"
	$(PHP) sh -c "php -d memory_limit=-1 bin/console $(cmd)"

migrate: ## Run pending Doctrine migrations
	$(PHP) sh -c "bin/console doctrine:migrations:migrate --no-interaction"

migration: ## Generate a new Doctrine migration from entity diff
	$(PHP) sh -c "bin/console doctrine:migrations:diff"

db-setup: ## Drop, create and migrate the dev database
	@echo "Waiting for Postgres to be ready..."
	@$(DC) exec postgres sh -c 'until pg_isready -U $(DATABASE_USER) -d $(DATABASE_NAME) >/dev/null 2>&1; do sleep 1; done'
	$(PHP) sh -c "bin/console doctrine:database:drop --force --if-exists --env=dev"
	$(PHP) sh -c "bin/console doctrine:database:create --env=dev"
	$(PHP) sh -c "bin/console doctrine:migrations:migrate --no-interaction --env=dev -v"

cache: ## Clear and warm the cache
	$(PHP) sh -c "rm -rf var/cache/*"
	$(PHP) sh -c "php -d memory_limit=-1 bin/console doctrine:cache:clear-result"
	$(PHP) sh -c "php -d memory_limit=-1 bin/console doctrine:cache:clear-query"
	$(PHP) sh -c "php -d memory_limit=-1 bin/console doctrine:cache:clear-metadata"
	$(PHP) sh -c "php -d memory_limit=-1 bin/console cache:warmup"

translations: ## Show missing translations for nl + en (catalogs are hand-written ICU, so no extract)
	$(PHP) sh -c "php -d memory_limit=-1 bin/console debug:translation nl --only-missing"
	$(PHP) sh -c "php -d memory_limit=-1 bin/console debug:translation en --only-missing"

worker: ## Run the async messenger worker
	$(PHP) sh -c "php -d memory_limit=-1 bin/console messenger:consume async --failure-limit=1 --memory-limit=128M --time-limit=300 -vv --profile"

seed: ## DEV ONLY: insert random demo reports, e.g. make seed count=300 country=DE with_media=20  (NL|DE|FR)
	$(PHP) sh -c "bin/console app:demo:seed --count=$(or $(count),150) --country=$(or $(country),NL) --with-media=$(or $(with_media),0)"

s3-bucket: ## Create the private media bucket (idempotent)
	$(PHP) sh -c "bin/console app:media:ensure-bucket"

media-import: ## One-off: copy media from local disk (var/storage/footage) into the S3 bucket
	$(PHP) sh -c "bin/console app:media:import-local"

admin: ## Create an admin/moderator and print a one-time login link to add a passkey: make admin name="Your Name"
	$(DC) exec --user www-data php sh -c "bin/console app:admin:create '$(name)'"

admin-link: ## One-time login link for an admin (new device, lost passkey): make admin-link name="Your Name"
	$(DC) exec --user www-data php sh -c "bin/console app:admin:login-link '$(name)'"

media: ## Moderate footage/photos: make media  |  make media action=approve type=photo id=<id>
	$(PHP) sh -c "bin/console app:media:moderate $(action) $(type) $(id)"

# ---------- frontend (Tailwind + Asset Mapper) ----------

asset: ## Build all assets (dev)
	$(PHP) sh -c "php -d memory_limit=-1 bin/console tailwind:build"

asset-prod: ## Build all assets (prod, minified)
	$(PHP) sh -c "php -d memory_limit=-1 bin/console tailwind:build --minify"
	$(PHP) sh -c "php -d memory_limit=-1 bin/console asset-map:compile"

asset-tailwind: ## Build Tailwind CSS (dev)
	$(PHP) sh -c "php -d memory_limit=-1 bin/console tailwind:build"

asset-tailwind-prod: ## Build Tailwind CSS (prod, minified)
	$(PHP) sh -c "php -d memory_limit=-1 bin/console tailwind:build --minify"

asset-compile: ## Compile asset map
	$(PHP) sh -c "php -d memory_limit=-1 bin/console asset-map:compile"

# ---------- testing / quality ----------

test: test-phpstan test-unit test-functional test-e2e ## Run phpstan + unit + functional + e2e tests

test-phpstan: ## Static analysis (level 8, no baseline)
	$(PHP) sh -c "php -d memory_limit=-1 bin/console cache:warmup --env=dev >/dev/null && php -d memory_limit=-1 vendor/bin/phpstan analyse"

test-unit: ## Unit tests
	$(PHP) sh -c "vendor/bin/phpunit -c phpunit.dist.xml --testsuite=Unit"

test-functional: ## Functional tests (command handlers against in-memory repositories)
	$(PHP) sh -c "vendor/bin/phpunit -c phpunit.dist.xml --testsuite=Functional"

test-e2e: ## End-to-end tests (real kernel + HTTP client on throwaway SQLite)
	$(PHP) sh -c "vendor/bin/phpunit -c phpunit.dist.xml --testsuite=E2E"

test-coverage: ## Test suite with HTML coverage report
	$(PHP) sh -c "XDEBUG_MODE=coverage vendor/bin/phpunit -c phpunit.dist.xml --coverage-html=./coverage"

cs-fix: ## PHP-CS-Fixer + phpcbf
	$(PHP) sh -c "./vendor/bin/php-cs-fixer fix -v"
	$(PHP) sh -c "./vendor/bin/phpcbf -p || ./vendor/bin/phpcs"

pre-commit: test-phpstan cs-fix ## Pre-commit checks

# ---------- deploy (Deployer, from your machine; see docs/deployment.md) ----------

deploy: ## Deploy to phantombraking.eu: make deploy  |  a tag: make deploy version=1.2.0
	vendor/bin/dep deploy phantombraking.eu -vv $(if $(version),--tag v$(version:v%=%),)

deploy-rollback: ## Switch back to the previous release
	vendor/bin/dep rollback phantombraking.eu

deploy-unlock: ## Remove a stale deploy lock
	vendor/bin/dep deploy:unlock phantombraking.eu

# ---------- first-time install ----------

install: ## First-time project setup
	@if [ ! -f .env.local ]; then \
		printf "APP_SECRET=%s\nPLATE_FINGERPRINT_KEY=%s\n" "$$(openssl rand -hex 16)" "$$(openssl rand -hex 32)" > .env.local && \
		echo "Created .env.local with fresh secrets (keep PLATE_FINGERPRINT_KEY stable!)"; \
	fi
	@$(MAKE) traefik-setup
	@$(MAKE) build
	@$(MAKE) up
	@if ! grep -q $(PROJECT_URL) /etc/hosts; then \
		echo "Adding hostnames to /etc/hosts (sudo required)..."; \
		sudo bash -c 'cat .dev/hostnames.txt >> /etc/hosts'; \
		echo "Done."; \
	else \
		echo "Hostnames already present in /etc/hosts."; \
	fi
	@$(MAKE) composer
	@$(MAKE) db-setup
	@$(MAKE) s3-bucket
	@$(MAKE) asset
	@echo ""
	@echo "Project ready on https://$(PROJECT_URL)/"
