# Instalação

## Pré-requisitos

- Docker
- Docker Compose v2
- Git
- WSL Ubuntu (no Windows)

## Instalação padrão (Fases 1–4)

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

No Windows, execute via WSL apontando para `/mnt/c/Plataforma360`:

```bash
wsl -e bash -c "cd /mnt/c/Plataforma360 && docker compose up -d --build"
wsl -e bash -c "cd /mnt/c/Plataforma360 && docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction"
```

Para carregar os dados de demonstração:

```bash
docker compose exec -T php php bin/console doctrine:fixtures:load
```

## Perfis opcionais

A plataforma usa **perfis Docker** para serviços que não são obrigatórios no core:

### Perfil `ai` — Inteligência Artificial local (Fase 5)

Sobe **Ollama** (LLM local) e **Qdrant** (banco vetorial).

```bash
docker compose --profile ai up -d
```

Para baixar um modelo no Ollama:
```bash
docker compose exec ollama ollama pull llama3
```

### Perfil `ops` — Orquestração Kestra (Fase 6)

Sobe **Kestra** (orquestrador de pipelines) e seu PostgreSQL dedicado.

```bash
docker compose --profile ops up -d
```

### Subir tudo junto

```bash
docker compose --profile ai --profile ops up -d
```

## URLs de acesso

| Serviço | URL | Perfil |
|---|---|---|
| Plataforma360 | http://localhost:8080 | padrão |
| Login admin | http://localhost:8080/admin/login | padrão |
| Adminer (DB) | http://localhost:8081 | padrão |
| Kestra UI | http://localhost:8082/ui/ | `ops` |
| Ollama API | http://localhost:11434 | `ai` |
| Qdrant UI | http://localhost:6333/dashboard | `ai` |
| API Platform | http://localhost:8080/api | padrão |
| Healthcheck | http://localhost:8080/health | padrão |

### Estado atual da interface

- A home publica (`/`) exibe os cards institucionais da plataforma e uma secao de postagens publicas/avisos.
- O acesso administrativo acontece pela tela `admin/login`, ja traduzida para portugues e alinhada ao padrao visual da plataforma.
- O navbar principal usa hubs diretos para `Inteligencia`, `Dados`, `Integracoes`, `IA`, `Operacoes`, `Governanca` e `Plataforma`.

## Usuários padrão (homologação local)

- `jane_admin / kitten` — ROLE_ADMIN
- `john_user / kitten` — ROLE_USER

Na tela de login, os usuarios de exemplo aparecem abaixo do botao **Entrar** para preenchimento rapido no ambiente local.

## Navegação por fase

### Fases 1–3: Dados e Pipeline CKAN
- **Dados → Provedores de Dados** — cadastrar fonte CKAN
- **Dados → Pacotes CKAN** — monitorar pacotes
- **Dados → Ingestão de Dados** — baixar arquivos para RAW
- **Dados → Arquivos RAW** — catálogo de arquivos físicos
- **Dados → Preview Dataset** — inspecionar CSV/XLSX
- **Dados → Histórico de Execuções** — trilha operacional

### Fase 4: Warehouse e Metabase
- **Dados → Modelos Analíticos** — transformar STAGING → WAREHOUSE
- **Dados → Data Warehouse** — tabelas analíticas
- **Integrações → Metabase** — configurar e incorporar dashboards
- **Inteligência → Dashboards BI** — dashboards incorporados

### Fase 5: Inteligência Artificial (perfil `ai`)
- **IA → Assistente** — chat em linguagem natural com dados
- **IA → Modelos** — configurar Ollama (local) e OpenAI (externo)
- **IA → Agentes** — agentes especializados por domínio
- **IA → Contextos** — fontes de dados disponíveis por consulta
- **IA → Prompts** — templates reutilizáveis
- **IA → Logs** — auditoria completa de interações
- **IA → Configurações** — status do Ollama

### Fase 6: Operações e Governança (perfil `ops`)
- **Operações → Visão Geral** — dashboard de saúde e KPIs
- **Operações → Pipelines** — gerenciar e disparar pipelines Kestra
- **Operações → Execuções** — histórico de execuções
- **Operações → Observabilidade** — saúde de todos os serviços
- **Operações → Alertas** — alertas ativos
- **Operações → Métricas IA** — uso de modelos e custo
- **Governança → Dados** — classificação LGPD e retenção
- **Governança → Governança IA** — rastreamento de uso IA
- **Governança → Custos** — custos por serviço externo
- **Governança → Auditoria** — trilha completa de ações admin

## Migrações recentes importantes

Foi adicionada a migration `Version20260523143000` para renomear tabelas legadas do Symfony Demo:

- `symfony_demo_post` → `post`
- `symfony_demo_comment` → `comment`
- `symfony_demo_tag` → `tag`
- `symfony_demo_user` → `app_user`

Para aplicar no ambiente local:

```bash
wsl -e bash -c "cd /mnt/c/Plataforma360 && docker compose exec php bin/console doctrine:migrations:migrate --no-interaction"
```

## Storage RAW

Os arquivos físicos são gravados em:

```text
storage/raw/{provider_slug}/{package_slug}/{year}/{month}/
```

No Docker, esse diretório é montado como `/var/storage/raw` dentro do container PHP.

```bash
find storage/raw -type f | head
```

## Banco de dados

- Host: `postgres`
- Porta interna: `5432`
- Banco: `plataforma360`
- Usuário: `plataforma360`
- Senha: `plataforma360`

A extensão PostGIS é habilitada automaticamente pelos scripts em `infra/postgres/init`.
