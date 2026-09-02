SHELL := /bin/sh
COMPOSE := docker compose
PHP := $(COMPOSE) exec php

.PHONY: install up down restart logs bash migrate \
        up-ai down-ai \
        up-ops down-ops \
        up-all down-all \
        kestra-logs kestra-restart \
        create-admin-hubs

install:
	cp -n .env.example .env || true
	$(COMPOSE) build
	$(COMPOSE) up -d
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction || true

# Sobe apenas os serviços core (Symfony, PostgreSQL, Nginx, Metabase)
up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

restart:
	$(COMPOSE) restart

logs:
	$(COMPOSE) logs -f --tail=200

bash:
	$(PHP) sh

migrate:
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction

# Perfil AI: Ollama + Qdrant
up-ai:
	$(COMPOSE) --profile ai up -d

down-ai:
	$(COMPOSE) --profile ai down

# Perfil Ops: Kestra + kestra-postgres
up-ops:
	$(COMPOSE) --profile ops up -d

down-ops:
	$(COMPOSE) --profile ops down

kestra-logs:
	$(COMPOSE) --profile ops logs -f kestra --tail=200

kestra-restart:
	$(COMPOSE) --profile ops restart kestra

# Sobe tudo: core + AI + Ops
up-all:
	$(COMPOSE) --profile ai --profile ops up -d

down-all:
	$(COMPOSE) --profile ai --profile ops down

# Criar estrutura de diretórios e templates para admin hubs
create-admin-hubs:
	mkdir -p apps/core/templates/admin/intelligence
	mkdir -p apps/core/templates/admin/integrations
	mkdir -p apps/core/templates/admin/operations
	mkdir -p apps/core/templates/admin/platform
	@echo "✓ Diretórios criados com sucesso"
	php create-admin-hubs.php

create-admin-hubs-files:
	@echo "Criando arquivos de templates..."
	@$(PHP) php bin/console cache:clear || true
