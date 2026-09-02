# Modelagem do Banco de Dados — Plataforma360

Descrição completa dos schemas, tabelas e colunas do banco de dados PostgreSQL da Plataforma360.

> **Estado atual importante:** o projeto recebeu a migration `Version20260523143000` para remover o prefixo `symfony_demo_` das tabelas principais de autenticacao e posts. A documentacao abaixo ja reflete os nomes atuais do mapeamento Doctrine.

**Documentação relacionada:**
- [Arquitetura da plataforma](arquitetura.md)
- [Instalação e Docker](instalacao.md)
- [Manual técnico da IA](manual-ia.md)
- [Manual técnico do Kestra](manual-kestra.md)
- [Cenários de teste](testes.md)

---

## Índice

1. [Visão Geral dos Bancos](#1-visão-geral-dos-bancos)
2. [Schema `public` — Core e Autenticação](#2-schema-public--core-e-autenticação)
3. [Schema `public` — Blog e Publicações](#3-schema-public--blog-e-publicações)
4. [Schema `public` — Turismo e Organizações](#4-schema-public--turismo-e-organizações)
5. [Schema `public` — Pipeline de Ingestão CKAN](#5-schema-public--pipeline-de-ingestão-ckan)
6. [Schema `public` — Dados RAW e Staging](#6-schema-public--dados-raw-e-staging)
7. [Schema `public` — Data Warehouse e Analytics](#7-schema-public--data-warehouse-e-analytics)
8. [Schema `public` — Inteligência Artificial](#8-schema-public--inteligência-artificial)
9. [Schema `public` — Operações e Pipelines](#9-schema-public--operações-e-pipelines)
10. [Schema `public` — Governança](#10-schema-public--governança)
11. [Schema `warehouse.*` — Tabelas Analíticas Dinâmicas](#11-schema-warehouse--tabelas-analíticas-dinâmicas)
12. [Schema `staging.*` — Dados Normalizados](#12-schema-staging--dados-normalizados)
13. [Qdrant — Banco Vetorial](#13-qdrant--banco-vetorial)
14. [PostgreSQL Kestra — Banco Interno do Orquestrador](#14-postgresql-kestra--banco-interno-do-orquestrador)
15. [Mapa de Relacionamentos](#15-mapa-de-relacionamentos)
16. [De-Para: CKAN → Staging → Warehouse](#16-de-para-ckan--staging--warehouse)

---

## 1. Visão Geral dos Bancos

A plataforma usa **três instâncias PostgreSQL** e um **banco vetorial**:

| Instância | Banco | Container | Porta host | Gerenciado por |
|---|---|---|---|---|
| `postgres` | `plataforma360` | `postgres` | 5432 | Doctrine Migrations |
| `kestra-postgres` | `kestra` | `kestra-postgres` | — (interno) | Kestra (auto) |
| Qdrant | N/A | `qdrant` | 6333 | API REST |

### Banco `plataforma360` — distribuição por fase

| Fase | Módulo | Tabelas principais |
|---|---|---|
| 1 | Core / Auth | `app_user`, `tenants` |
| 2 | Ingestão CKAN | `data_providers`, `provider_packages`, `dataset_resources`, `ingestion_runs` |
| 2 | Dados RAW | `raw_files`, `dataset_schemas`, `dataset_column_mappings`, `dataset_quality_reports` |
| 3 | Catálogo / Indicadores | `indicators`, `data_sources`, `batch_routines` |
| 4 | Warehouse | `analytic_models`, `analytics_history`, `metabase_configs`, `metabase_dashboards` |
| 5 | IA | `ai_models`, `ai_contexts`, `ai_agents`, `ai_prompts`, `ai_interactions`, `ai_embeddings` |
| 6 | Operações | `pipelines`, `pipeline_executions`, `alerts`, `system_metrics` |
| 6 | Governança | `audit_logs`, `cost_records`, `data_governance_records` |
| — | Turismo | `organization`, `tourist_spot`, `tourism_event`, `tourist_guide`, `accommodation` |
| — | Blog / avisos publicos | `post`, `tag`, `comment`, `symfony_demo_post_tag` |

---

## 2. Schema `public` — Core e Autenticação

### `app_user`
> Usuários do portal. Autenticação via Symfony Security.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | integer | NOT NULL | PK auto-increment |
| `full_name` | varchar(255) | YES | Nome completo do usuário |
| `username` | varchar(191) | NOT NULL | Login único |
| `email` | varchar(180) | YES | E-mail único |
| `password` | varchar(255) | YES | Hash bcrypt/argon2 |
| `roles` | json | NOT NULL | Ex: `["ROLE_ADMIN"]` |

Índices únicos: `username`, `email`

---

### `tenants`
> Configuração multi-tenant. Permite isolar dados por prefeitura, secretaria ou órgão.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK auto-increment |
| `name` | varchar(255) | NOT NULL | Nome do tenant |
| `slug` | varchar(191) | NOT NULL | Slug único |
| `type` | varchar(30) | NOT NULL | `prefeitura` \| `secretaria` \| `orgao` \| `ambiente` |
| `municipio_id` | varchar(10) | YES | Código IBGE do município |
| `estado` | varchar(2) | YES | UF (ex: `PE`) |
| `is_active` | boolean | NOT NULL | Padrão: `true` |
| `settings` | json | YES | Configurações específicas do tenant |
| `description` | text | YES | Descrição livre |
| `created_at` | datetime | NOT NULL | Data de criação |
| `updated_at` | datetime | YES | Data de atualização |

Índice único: `slug`

---

## 3. Schema `public` — Blog e Publicações

> O modulo de posts e herdado do Symfony Demo, mas foi adaptado para a Plataforma360. Atualmente ele atende a area publica `/blog` e a secao de avisos/postagens exibida na home publica.

> A tabela de juncao ManyToMany permanece `symfony_demo_post_tag` no estado atual do mapeamento.

### `post`

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | integer | NOT NULL | PK auto-increment |
| `author_id` | integer | NOT NULL | FK → `app_user.id` |
| `title` | varchar(255) | NOT NULL | Título do post |
| `slug` | varchar(255) | NOT NULL | Slug único |
| `summary` | varchar(255) | NOT NULL | Resumo (máx. 255 chars) |
| `content` | text | NOT NULL | Conteúdo completo |
| `published_at` | datetime | NOT NULL | Data de publicação |

Índice único: `slug`
Relações: `author_id` → `app_user`, posts ↔ tags via `symfony_demo_post_tag`

---

### `tag`

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | integer | NOT NULL | PK auto-increment |
| `name` | varchar(191) | NOT NULL | Nome da tag (único) |

---

### `comment`

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | integer | NOT NULL | PK auto-increment |
| `post_id` | integer | NOT NULL | FK → `post.id` |
| `author_id` | integer | NOT NULL | FK → `app_user.id` |
| `content` | text | NOT NULL | Texto do comentário |
| `published_at` | datetime | NOT NULL | Data de publicação |

---

### `symfony_demo_post_tag` *(tabela de junção ManyToMany)*

| Coluna | Tipo | Descrição |
|---|---|---|
| `post_id` | integer | FK → `post.id` |
| `tag_id` | integer | FK → `tag.id` |

---

## 4. Schema `public` — Turismo e Organizações

> Entidades do domínio de turismo inteligente. Cadastros de pontos, eventos, guias e hospedagens.

### `organization`
> Entidades organizacionais (empresas, cooperativas, associações do setor turístico).

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK auto-increment |
| `name` | varchar(255) | NOT NULL | Nome da organização |
| `slug` | varchar(191) | NOT NULL | Slug único |
| `description` | text | YES | Descrição |
| `type` | varchar(100) | NOT NULL | Tipo de organização |
| `document_number` | varchar(30) | YES | CNPJ ou similar |
| `website` | varchar(255) | YES | Site |
| `instagram` | varchar(255) | YES | Instagram |
| `phone` | varchar(50) | YES | Telefone |
| `email` | varchar(180) | YES | E-mail |
| `address` | varchar(255) | YES | Endereço |
| `district` | varchar(100) | YES | Bairro |
| `city` | varchar(100) | NOT NULL | Cidade (padrão: `Olinda`) |
| `state` | varchar(2) | NOT NULL | UF (padrão: `PE`) |
| `latitude` | decimal(10,8) | YES | Latitude geográfica |
| `longitude` | decimal(11,8) | YES | Longitude geográfica |
| `active` | boolean | NOT NULL | Padrão: `true` |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

---

### `tourist_spot`
> Pontos turísticos e atrações.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `organization_id` | bigint | YES | FK → `organization.id` |
| `name` | varchar(255) | NOT NULL | Nome do ponto |
| `slug` | varchar(191) | NOT NULL | Slug único |
| `description` | text | NOT NULL | Descrição |
| `history` | text | YES | Contexto histórico |
| `category` | varchar(100) | NOT NULL | Categoria da atração |
| `address` | varchar(255) | YES | Endereço |
| `district` | varchar(100) | YES | Bairro |
| `latitude` | decimal(10,8) | NOT NULL | Latitude |
| `longitude` | decimal(11,8) | NOT NULL | Longitude |
| `opening_hours` | varchar(255) | YES | Horário de funcionamento |
| `ticket_price` | decimal(10,2) | YES | Valor do ingresso |
| `accessibility` | boolean | NOT NULL | Acessível |
| `has_bathroom` | boolean | NOT NULL | Possui banheiro |
| `has_parking` | boolean | NOT NULL | Possui estacionamento |
| `safety_level` | varchar(50) | YES | Nível de segurança |
| `source` | varchar(100) | YES | Fonte do dado |
| `active` | boolean | NOT NULL | Padrão: `true` |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

---

### `tourism_event`
> Eventos culturais e turísticos.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `organization_id` | bigint | YES | FK → `organization.id` |
| `title` | varchar(255) | NOT NULL | Título do evento |
| `slug` | varchar(191) | NOT NULL | Slug único |
| `description` | text | NOT NULL | Descrição |
| `category` | varchar(100) | NOT NULL | Categoria |
| `start_date` | datetime | NOT NULL | Início |
| `end_date` | datetime | YES | Fim |
| `location` | varchar(255) | NOT NULL | Local |
| `district` | varchar(100) | YES | Bairro |
| `latitude` | decimal(10,8) | YES | Latitude |
| `longitude` | decimal(11,8) | YES | Longitude |
| `expected_audience` | integer | YES | Público esperado |
| `is_free` | boolean | NOT NULL | Gratuito (padrão: `true`) |
| `ticket_url` | varchar(255) | YES | Link para ingressos |
| `website` | varchar(255) | YES | Site |
| `instagram` | varchar(255) | YES | Instagram |
| `source` | varchar(100) | YES | Fonte do dado |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

---

### `tourist_guide`
> Guias de turismo cadastrados.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `organization_id` | bigint | YES | FK → `organization.id` |
| `name` | varchar(255) | NOT NULL | Nome |
| `cadastur_code` | varchar(100) | YES | Código Cadastur |
| `languages` | json | YES | Idiomas falados |
| `specialties` | json | YES | Especialidades |
| `phone` | varchar(50) | YES | Telefone |
| `email` | varchar(180) | YES | E-mail |
| `instagram` | varchar(255) | YES | Instagram |
| `service_region` | varchar(255) | YES | Região de atuação |
| `active` | boolean | NOT NULL | Padrão: `true` |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

---

### `accommodation`
> Meios de hospedagem.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `organization_id` | bigint | YES | FK → `organization.id` |
| `name` | varchar(255) | NOT NULL | Nome |
| `slug` | varchar(191) | NOT NULL | Slug único |
| `description` | text | YES | Descrição |
| `category` | varchar(100) | NOT NULL | Categoria (hotel, hostel, pousada...) |
| `cadastur_code` | varchar(100) | YES | Código Cadastur |
| `address` | varchar(255) | NOT NULL | Endereço |
| `district` | varchar(100) | YES | Bairro |
| `latitude` | decimal(10,8) | YES | Latitude |
| `longitude` | decimal(11,8) | YES | Longitude |
| `rooms` | integer | YES | Número de quartos |
| `beds` | integer | YES | Número de leitos |
| `wifi` | boolean | NOT NULL | Wi-Fi disponível |
| `parking` | boolean | NOT NULL | Estacionamento |
| `accessibility` | boolean | NOT NULL | Acessibilidade |
| `website` | varchar(255) | YES | Site |
| `instagram` | varchar(255) | YES | Instagram |
| `phone` | varchar(50) | YES | Telefone |
| `email` | varchar(180) | YES | E-mail |
| `active` | boolean | NOT NULL | Padrão: `true` |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

---

## 5. Schema `public` — Pipeline de Ingestão CKAN

> Tabelas que governam o fluxo de ingestão de dados de portais CKAN externos.

```
data_providers
    └── provider_packages (1:N)
            └── dataset_resources (1:N)
                    └── raw_files (1:N)
ingestion_runs (FK para provider + package + resource)
```

### `data_providers`
> Provedores de dados externos (portais CKAN, APIs governamentais).

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `name` | varchar(255) | NOT NULL | Nome do provedor |
| `type` | varchar(50) | NOT NULL | Tipo (ex: `ckan`) |
| `base_url` | varchar(255) | NOT NULL | URL base da API |
| `package_list_path` | varchar(255) | NOT NULL | Path para listar pacotes |
| `package_show_path` | varchar(255) | NOT NULL | Path para detalhar pacote |
| `is_active` | boolean | NOT NULL | Padrão: `true` |
| `last_synced_at` | datetime | YES | Última sincronização |
| `notes` | text | YES | Observações |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

---

### `provider_packages`
> Pacotes (datasets) catalogados de um provedor CKAN.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `data_provider_id` | bigint | NOT NULL | FK → `data_providers.id` (cascade delete) |
| `package_id` | varchar(191) | NOT NULL | ID externo do pacote no CKAN |
| `title` | varchar(255) | YES | Título |
| `description` | text | YES | Descrição |
| `is_monitored` | boolean | NOT NULL | Monitoramento ativo (padrão: `false`) |
| `is_active` | boolean | NOT NULL | Padrão: `true` |
| `last_checked_at` | datetime | YES | Última verificação |
| `raw_metadata` | json | NOT NULL | Metadados brutos do provedor |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

Índice único: `(data_provider_id, package_id)`

---

### `dataset_resources`
> Recursos individuais (arquivos CSV, XLSX, JSON) de um pacote.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `provider_package_id` | bigint | NOT NULL | FK → `provider_packages.id` (cascade delete) |
| `resource_id` | varchar(191) | NOT NULL | ID externo do resource no CKAN |
| `name` | varchar(255) | YES | Nome do resource |
| `format` | varchar(50) | YES | Formato: `CSV`, `XLSX`, `JSON`... |
| `url` | varchar(2048) | NOT NULL | URL de download |
| `size` | integer | YES | Tamanho em bytes |
| `hash` | varchar(255) | YES | Hash do arquivo na fonte |
| `created_at_source` | datetime | YES | Criação na fonte |
| `last_modified_source` | datetime | YES | Modificação na fonte |
| `raw_metadata` | json | NOT NULL | Metadados brutos |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

Índice único: `(provider_package_id, resource_id)`

---

### `ingestion_runs`
> Registros de execuções de ingestão (download de arquivos).

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `data_provider_id` | bigint | NOT NULL | FK → `data_providers.id` |
| `provider_package_id` | bigint | YES | FK → `provider_packages.id` |
| `dataset_resource_id` | bigint | YES | FK → `dataset_resources.id` |
| `status` | varchar(50) | NOT NULL | `pending` \| `running` \| `success` \| `warning` \| `failed` |
| `started_at` | datetime | YES | Início da execução |
| `finished_at` | datetime | YES | Fim da execução |
| `message` | text | YES | Mensagem de status |
| `logs` | json | NOT NULL | Log detalhado |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

---

## 6. Schema `public` — Dados RAW e Staging

### `raw_files`
> Arquivos físicos baixados dos provedores. Vincula o arquivo no disco ao registro no banco.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `dataset_resource_id` | bigint | NOT NULL | FK → `dataset_resources.id` |
| `provider_package_id` | bigint | NOT NULL | FK → `provider_packages.id` |
| `data_provider_id` | bigint | NOT NULL | FK → `data_providers.id` |
| `original_name` | varchar(255) | NOT NULL | Nome original do arquivo |
| `stored_name` | varchar(255) | NOT NULL | Nome no storage |
| `local_path` | varchar(1024) | NOT NULL | Caminho em `storage/raw/` |
| `mime_type` | varchar(255) | YES | MIME type detectado |
| `extension` | varchar(20) | YES | Extensão (csv, xlsx...) |
| `file_size` | bigint | YES | Tamanho em bytes |
| `file_hash` | varchar(64) | YES | SHA-256 do arquivo |
| `download_status` | varchar(30) | NOT NULL | `pending` \| `downloaded` \| `duplicate` \| `failed` |
| `already_processed` | boolean | NOT NULL | Transformação realizada |
| `staging_path` | varchar(1024) | YES | Caminho do staging gerado |
| `transformation_status` | varchar(30) | NOT NULL | `pending` \| `running` \| `done` \| `failed` |
| `downloaded_at` | datetime | YES | Timestamp do download |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

Índices: `file_hash`, `download_status`, `downloaded_at`

---

### `dataset_schemas`
> Schema inferido de um arquivo RAW (colunas e tipos detectados automaticamente).

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `raw_file_id` | bigint | NOT NULL | FK → `raw_files.id` (cascade delete) |
| `column_name` | varchar(255) | NOT NULL | Nome da coluna no arquivo |
| `detected_type` | varchar(50) | NOT NULL | Tipo inferido: `string`, `integer`, `decimal`, `date`... |
| `nullable` | boolean | NOT NULL | Padrão: `true` |
| `sample_value` | text | YES | Exemplo de valor da coluna |
| `created_at` | datetime | NOT NULL | — |

---

### `dataset_column_mappings`
> Regras de mapeamento: coluna original → coluna normalizada + tipo + regra de transformação.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `provider_package_id` | bigint | NOT NULL | FK → `provider_packages.id` (cascade delete) |
| `original_column` | varchar(255) | NOT NULL | Nome exato da coluna no arquivo fonte |
| `normalized_column` | varchar(255) | NOT NULL | Nome snake_case no staging |
| `target_data_type` | varchar(30) | NOT NULL | `string` \| `text` \| `integer` \| `decimal` \| `boolean` \| `date` \| `datetime` \| `json` \| `geometry` |
| `normalization_rule` | varchar(50) | YES | `trim` \| `uppercase` \| `lowercase` \| `normalize_uf` \| `normalize_cnpj` \| ... |
| `required_field` | boolean | NOT NULL | Campo obrigatório (padrão: `false`) |
| `is_active` | boolean | NOT NULL | Mapeamento ativo |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

Índice único: `(provider_package_id, original_column)`

---

### `dataset_quality_reports`
> Relatório de qualidade gerado após transformação RAW → STAGING.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `raw_file_id` | bigint | NOT NULL | FK → `raw_files.id` (cascade delete) |
| `total_rows` | integer | NOT NULL | Total de linhas no arquivo |
| `valid_rows` | integer | NOT NULL | Linhas que passaram na validação |
| `invalid_rows` | integer | NOT NULL | Linhas rejeitadas |
| `duplicated_rows` | integer | NOT NULL | Linhas duplicadas |
| `null_fields` | integer | NOT NULL | Contagem de campos nulos |
| `validation_errors` | json | NOT NULL | Detalhe dos erros por linha/coluna |
| `generated_at` | datetime | NOT NULL | Timestamp da geração |

Índices: `raw_file_id`, `generated_at`

---

### `data_sources`
> Fontes de dados genéricas (referência para batch routines e indicadores).

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `name` | varchar(255) | NOT NULL | Nome |
| `slug` | varchar(191) | NOT NULL | Slug único |
| `type` | varchar(100) | NOT NULL | Tipo |
| `url` | varchar(255) | YES | URL |
| `description` | text | YES | Descrição |
| `update_frequency` | varchar(100) | YES | Frequência de atualização |
| `active` | boolean | NOT NULL | Padrão: `true` |
| `created_at` | datetime | NOT NULL | — |

---

### `batch_routine`
> Rotinas de processamento em lote para ingestão e sincronização.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `name` | varchar(255) | NOT NULL | Nome da rotina |
| `slug` | varchar(191) | NOT NULL | Slug único |
| `source_name` | varchar(255) | NOT NULL | Nome da fonte de dados |
| `source_type` | varchar(100) | NOT NULL | Tipo da fonte |
| `description` | text | YES | Descrição |
| `api_url` | varchar(255) | YES | URL da API |
| `cron_expression` | varchar(100) | YES | Expressão cron |
| `scheduled_time` | varchar(20) | YES | Horário agendado |
| `status` | varchar(50) | NOT NULL | `pending` \| `running` \| `success` \| `warning` \| `failed` \| `disabled` |
| `last_execution_at` | datetime | YES | Última execução |
| `next_execution_at` | datetime | YES | Próxima execução |
| `records_processed` | bigint | NOT NULL | Registros processados |
| `records_created` | bigint | NOT NULL | Registros criados |
| `records_updated` | bigint | NOT NULL | Registros atualizados |
| `records_failed` | bigint | NOT NULL | Registros com falha |
| `execution_time_seconds` | float | YES | Duração em segundos |
| `last_message` | text | YES | Última mensagem de status |
| `active` | boolean | NOT NULL | Padrão: `true` |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

---

## 7. Schema `public` — Data Warehouse e Analytics

### `indicators`
> KPIs calculados e publicados via API analítica.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `title` | varchar(255) | NOT NULL | Título do indicador |
| `slug` | varchar(191) | NOT NULL | Slug único |
| `category` | varchar(100) | NOT NULL | Categoria (turismo, economia...) |
| `value` | float | NOT NULL | Valor atual |
| `unit` | varchar(50) | YES | Unidade (%, R$, qtd...) |
| `reference_date` | date | NOT NULL | Data de referência |
| `source` | varchar(255) | NOT NULL | Fonte do dado |
| `description` | text | YES | Descrição |
| `created_at` | datetime | NOT NULL | — |

Índice único: `slug`

---

### `analytic_models`
> Definição de modelos analíticos que geram tabelas no schema `warehouse.*`.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `name` | varchar(255) | NOT NULL | Nome do modelo |
| `slug` | varchar(191) | NOT NULL | Slug único |
| `description` | text | YES | Descrição |
| `source_table` | varchar(255) | NOT NULL | Tabela origem (ex: `staging.turismo_agencias`) |
| `target_table` | varchar(255) | NOT NULL | Tabela destino (ex: `warehouse.dw_agencias_turismo`) |
| `dimensions` | json | NOT NULL | Colunas de dimensão |
| `metrics` | json | NOT NULL | Colunas de métricas/agregações |
| `filters` | json | NOT NULL | Filtros aplicados |
| `refresh_strategy` | varchar(30) | NOT NULL | `manual` \| `daily` \| `hourly` \| `weekly` |
| `last_refresh_status` | varchar(30) | YES | Status do último refresh |
| `last_refreshed_at` | datetime | YES | Timestamp do último refresh |
| `row_count` | bigint | YES | Contagem atual de linhas |
| `is_active` | boolean | NOT NULL | Padrão: `true` |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

Índices: `slug`, `is_active`

---

### `analytics_history`
> Auditoria de eventos analíticos (refreshes, ETLs, sincronizações).

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `event_type` | varchar(50) | NOT NULL | `warehouse_refresh` \| `model_update` \| `indicator_generation` \| `etl_failure` \| `metabase_sync` |
| `subject` | varchar(255) | NOT NULL | Sujeito do evento |
| `detail` | text | YES | Detalhe do evento |
| `status` | varchar(30) | NOT NULL | `success` \| `failed` \| `running` \| `warning` |
| `duration_ms` | bigint | YES | Duração em ms |
| `rows_affected` | bigint | YES | Linhas afetadas |
| `metadata` | json | YES | Metadados adicionais |
| `created_at` | datetime | NOT NULL | — |

Índices: `event_type`, `status`, `created_at`

---

### `metabase_configs`
> Configuração da integração com o Metabase.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `name` | varchar(255) | NOT NULL | Padrão: `Metabase Principal` |
| `base_url` | varchar(512) | NOT NULL | URL base do Metabase |
| `database_name` | varchar(255) | YES | Nome do banco conectado |
| `username` | varchar(255) | YES | Usuário do Metabase |
| `password_encrypted` | varchar(1024) | YES | Senha criptografada |
| `secret_key` | varchar(512) | YES | Chave para embed JWT |
| `connection_status` | varchar(30) | NOT NULL | `untested` \| `connected` \| `failed` |
| `last_tested_at` | datetime | YES | Último teste de conexão |
| `last_sync_at` | datetime | YES | Última sincronização |
| `is_active` | boolean | NOT NULL | Padrão: `true` |
| `notes` | text | YES | Observações |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

---

### `metabase_dashboards`
> Dashboards e questions do Metabase registrados no portal.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `name` | varchar(255) | NOT NULL | Nome |
| `description` | text | YES | Descrição |
| `metabase_id` | bigint | YES | ID interno do Metabase |
| `embed_url` | varchar(1024) | YES | URL de embed (iframe) |
| `public_uuid` | varchar(255) | YES | UUID público para compartilhamento |
| `type` | varchar(30) | NOT NULL | `dashboard` \| `question` |
| `dataset` | varchar(255) | YES | Dataset associado |
| `origin` | varchar(255) | YES | Referência de origem |
| `status` | varchar(30) | NOT NULL | `active` \| `inactive` \| `syncing` |
| `allow_embed` | boolean | NOT NULL | Embed permitido (padrão: `false`) |
| `is_active` | boolean | NOT NULL | Padrão: `true` |
| `metabase_updated_at` | datetime | YES | Última atualização no Metabase |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

Índice: `is_active`

---

## 8. Schema `public` — Inteligência Artificial

### `ai_models`
> Configuração de modelos de IA (Ollama local, OpenAI, Azure OpenAI).

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `name` | varchar(255) | NOT NULL | Nome do modelo |
| `slug` | varchar(191) | NOT NULL | Slug único |
| `provider` | varchar(50) | NOT NULL | `local_ollama` \| `openai` \| `azure_openai` \| `other` |
| `model_name` | varchar(100) | NOT NULL | Identificador do modelo (ex: `llama3`, `gpt-4o-mini`) |
| `endpoint` | varchar(512) | YES | URL do endpoint (Ollama: `http://ollama:11434`) |
| `api_key_encrypted` | varchar(1024) | YES | API Key criptografada (OpenAI/Azure) |
| `temperature` | decimal(3,2) | YES | Temperatura (0.0–1.0) |
| `max_tokens` | bigint | YES | Máximo de tokens por resposta |
| `context_window` | bigint | YES | Janela de contexto |
| `supports_embeddings` | boolean | NOT NULL | Suporta geração de embeddings |
| `is_default` | boolean | NOT NULL | Modelo padrão do assistente |
| `is_active` | boolean | NOT NULL | Padrão: `true` |
| `description` | text | YES | Descrição |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

Índice único: `slug`
Índices: `provider`, `is_active`

---

### `ai_contexts`
> Definições de contexto: quais dados são injetados no prompt antes da resposta.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `name` | varchar(255) | NOT NULL | Nome do contexto |
| `slug` | varchar(191) | NOT NULL | Slug único |
| `description` | text | YES | Descrição |
| `sources` | json | NOT NULL | Fontes: `warehouse`, `catalog`, `indicators`, `analytics_api`, `documents`, `lineage`, `quality` |
| `warehouse_tables` | json | NOT NULL | Tabelas do warehouse incluídas |
| `allowed_for_external` | boolean | NOT NULL | Pode ser usado com provedores externos (OpenAI)? Padrão: `false` |
| `max_rows_context` | bigint | YES | Máximo de linhas injetadas (padrão: 100) |
| `is_active` | boolean | NOT NULL | Padrão: `true` |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

---

### `ai_agents`
> Agentes de IA especializados por domínio.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `name` | varchar(255) | NOT NULL | Nome do agente |
| `slug` | varchar(191) | NOT NULL | Slug único |
| `description` | text | YES | Descrição |
| `agent_type` | varchar(50) | NOT NULL | `turismo` \| `dados_publicos` \| `executivo` \| `tecnico` |
| `default_model_id` | bigint | YES | FK → `ai_models.id` (SET NULL) |
| `default_context_id` | bigint | YES | FK → `ai_contexts.id` (SET NULL) |
| `prompt_id` | bigint | YES | FK → `ai_prompts.id` (SET NULL) |
| `tools` | json | NOT NULL | Ferramentas disponíveis (array de strings) |
| `is_active` | boolean | NOT NULL | Padrão: `true` |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

---

### `ai_prompts`
> Templates de prompt reutilizáveis por finalidade.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `name` | varchar(255) | NOT NULL | Nome |
| `slug` | varchar(191) | NOT NULL | Slug único |
| `purpose` | varchar(80) | NOT NULL | `indicator_analysis` \| `report_generation` \| `dataset_explanation` \| `executive_summary` \| `territorial_comparison` \| `data_quality_diagnosis` \| `general_assistant` \| `nl_to_sql` |
| `prompt_template` | text | NOT NULL | Corpo do prompt com variáveis `{{variavel}}` |
| `context_type` | varchar(100) | YES | Tipo de contexto esperado |
| `provider` | varchar(50) | YES | Provedor de preferência |
| `version` | integer | NOT NULL | Versão (padrão: 1) |
| `is_active` | boolean | NOT NULL | Padrão: `true` |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

Índice: `purpose`

---

### `ai_interactions`
> Log de todas as interações com modelos de IA.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `user_identifier` | varchar(255) | YES | Identificador do usuário |
| `provider` | varchar(50) | NOT NULL | Provedor utilizado |
| `model_name` | varchar(100) | NOT NULL | Modelo utilizado |
| `agent_slug` | varchar(191) | YES | Agente utilizado |
| `prompt` | text | NOT NULL | Prompt enviado |
| `response` | text | YES | Resposta recebida |
| `context_used` | json | YES | Dados de contexto injetados |
| `tokens_input` | bigint | YES | Tokens consumidos no input |
| `tokens_output` | bigint | YES | Tokens consumidos no output |
| `estimated_cost_usd` | decimal(10,6) | YES | Custo estimado em USD |
| `duration_ms` | bigint | YES | Duração em ms |
| `status` | varchar(30) | NOT NULL | `running` \| `success` \| `failed` |
| `error_message` | text | YES | Mensagem de erro |
| `is_external_provider` | boolean | NOT NULL | `true` se OpenAI/Azure (afeta governança) |
| `created_at` | datetime | NOT NULL | — |

Índices: `provider`, `status`, `created_at`

---

### `ai_embeddings`
> Registro dos vetores gerados e armazenados no Qdrant.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `source_type` | varchar(50) | NOT NULL | `dataset` \| `indicator` \| `warehouse_table` \| `document` \| `quality_report` |
| `source_id` | varchar(255) | NOT NULL | Identificador da entidade fonte |
| `chunk_text` | text | NOT NULL | Trecho de texto vetorizado |
| `embedding_provider` | varchar(50) | NOT NULL | Provedor do embedding |
| `embedding_model` | varchar(100) | NOT NULL | Modelo utilizado |
| `vector_id` | varchar(255) | YES | ID do vetor no Qdrant |
| `metadata` | json | YES | Metadados extras para filtros no Qdrant |
| `created_at` | datetime | NOT NULL | — |

Índice: `source_type`, `source_id`

---

## 9. Schema `public` — Operações e Pipelines

### `pipelines`
> Definição dos pipelines Kestra registrados no portal.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `name` | varchar(255) | NOT NULL | Nome do pipeline |
| `slug` | varchar(191) | NOT NULL | Slug único |
| `kestra_namespace` | varchar(255) | YES | Namespace no Kestra (ex: `plataforma360`) |
| `kestra_flow_id` | varchar(255) | YES | ID do flow no Kestra |
| `description` | text | YES | Descrição |
| `type` | varchar(50) | NOT NULL | `ingestion` \| `transformation` \| `warehouse` \| `embeddings` \| `ai_job` \| `sync` \| `backup` |
| `trigger_type` | varchar(50) | NOT NULL | `manual` \| `cron` \| `event` \| `webhook` |
| `cron_expression` | varchar(100) | YES | Expressão cron (ex: `0 2 * * *`) |
| `kestra_yaml` | text | YES | YAML completo do flow |
| `is_active` | boolean | NOT NULL | Padrão: `true` |
| `last_execution_id` | varchar(255) | YES | ID da última execução no Kestra |
| `last_execution_status` | varchar(50) | YES | Status da última execução |
| `last_executed_at` | datetime | YES | Timestamp da última execução |
| `next_execution_at` | datetime | YES | Próxima execução agendada |
| `avg_duration_ms` | bigint | YES | Duração média |
| `failure_count` | bigint | NOT NULL | Contador de falhas |
| `success_count` | bigint | NOT NULL | Contador de sucessos |
| `dataset_slug` | varchar(191) | YES | Dataset associado |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

---

### `pipeline_executions`
> Histórico de execuções dos pipelines.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `pipeline_id` | bigint | YES | FK → `pipelines.id` (SET NULL) |
| `kestra_execution_id` | varchar(255) | YES | ID da execução no Kestra |
| `status` | varchar(50) | NOT NULL | `CREATED` \| `RUNNING` \| `SUCCESS` \| `FAILED` \| `CANCELLED` \| `WARNING` |
| `started_at` | datetime | YES | Início |
| `finished_at` | datetime | YES | Fim |
| `duration_ms` | bigint | YES | Duração em ms |
| `triggered_by` | varchar(255) | YES | Quem disparou |
| `trigger_type` | varchar(50) | NOT NULL | `manual` \| `scheduled` \| `api` \| `event` |
| `logs` | text | YES | Logs da execução |
| `error_message` | text | YES | Mensagem de erro |
| `retry_count` | integer | NOT NULL | Número de tentativas |
| `inputs` | json | YES | Parâmetros de entrada |
| `outputs` | json | YES | Saídas da execução |
| `created_at` | datetime | NOT NULL | — |

Índices: `status`, `created_at`

---

### `alerts`
> Alertas do sistema gerados por falhas, thresholds ou indisponibilidades.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `type` | varchar(80) | NOT NULL | `pipeline_failed` \| `dataset_stale` \| `storage_full` \| `ai_unavailable` \| `warehouse_slow` \| `api_unavailable` \| `embedding_failed` \| `metabase_offline` \| `kestra_offline` \| `high_error_rate` \| `cost_threshold` \| `general` |
| `level` | varchar(20) | NOT NULL | `info` \| `warning` \| `critical` |
| `title` | varchar(255) | NOT NULL | Título do alerta |
| `message` | text | NOT NULL | Mensagem detalhada |
| `source` | varchar(100) | YES | Sistema de origem |
| `source_id` | varchar(255) | YES | ID da entidade que gerou o alerta |
| `status` | varchar(30) | NOT NULL | `active` \| `acknowledged` \| `resolved` |
| `acknowledged_by` | varchar(255) | YES | Usuário que reconheceu |
| `acknowledged_at` | datetime | YES | Timestamp do reconhecimento |
| `resolved_at` | datetime | YES | Timestamp da resolução |
| `metadata` | json | YES | Dados adicionais |
| `created_at` | datetime | NOT NULL | — |

Índices: `level`, `status`

---

### `system_metrics`
> Métricas de saúde dos serviços (coletadas pelo HealthCheckService).

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `metric_name` | varchar(100) | NOT NULL | Nome da métrica (ex: `memory_usage_mb`) |
| `metric_type` | varchar(50) | NOT NULL | `gauge` |
| `value` | decimal(18,4) | NOT NULL | Valor numérico |
| `unit` | varchar(50) | YES | Unidade (MB, %, ms...) |
| `labels` | json | YES | Labels adicionais |
| `source` | varchar(50) | NOT NULL | `symfony` \| `kestra` \| `postgres` \| `ollama` \| `qdrant` \| `nginx` \| `metabase` \| `storage` |
| `recorded_at` | datetime | NOT NULL | Timestamp da coleta |

Índices: `(source, metric_name)`, `recorded_at`

---

## 10. Schema `public` — Governança

### `audit_logs`
> Trilha de auditoria imutável de todas as ações administrativas.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `action` | varchar(50) | NOT NULL | `create` \| `update` \| `delete` \| `read` \| `execute` \| `login` \| `logout` \| `export` \| `ai_query` \| `pipeline_run` \| `pipeline_pause` \| `sql_execute` \| `dashboard_view` \| `dataset_access` \| `config_change` |
| `entity_type` | varchar(100) | YES | Entidade afetada (ex: `Pipeline`) |
| `entity_id` | varchar(255) | YES | ID da entidade |
| `user_identifier` | varchar(255) | YES | Usuário que executou |
| `description` | text | NOT NULL | Descrição da ação |
| `before_value` | json | YES | Estado anterior (updates) |
| `after_value` | json | YES | Estado posterior (updates) |
| `ip_address` | varchar(45) | YES | IP (suporta IPv6) |
| `user_agent` | text | YES | Browser/cliente |
| `metadata` | json | YES | Metadados adicionais |
| `tenant_id` | bigint | YES | Tenant de contexto |
| `created_at` | datetime | NOT NULL | — |

> **Importante:** sem botão de delete — tabela somente leitura após inserção.

Índices: `action`, `entity_type`, `user_identifier`, `created_at`

---

### `cost_records`
> Rastreamento de custos por serviço e período.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `service` | varchar(50) | NOT NULL | `openai` \| `embeddings` \| `warehouse` \| `storage` \| `pipeline` \| `metabase` \| `kestra` \| `ollama` \| `qdrant` |
| `period_date` | date | NOT NULL | Data do período |
| `quantity` | decimal(18,4) | NOT NULL | Quantidade consumida |
| `unit` | varchar(30) | YES | Unidade (tokens, GB, execuções...) |
| `unit_cost_usd` | decimal(10,8) | NOT NULL | Custo unitário em USD |
| `total_cost_usd` | decimal(10,6) | NOT NULL | Custo total em USD |
| `description` | text | YES | Descrição |
| `metadata` | json | YES | Dados adicionais |
| `tenant_id` | bigint | YES | Tenant |
| `created_at` | datetime | NOT NULL | — |

Índices: `service`, `period_date`

---

### `data_governance_records`
> Registros de governança LGPD por dataset.

| Coluna | Tipo | Nullable | Descrição |
|---|---|---|---|
| `id` | bigint | NOT NULL | PK |
| `dataset_id` | bigint | YES | Referência ao dataset |
| `dataset_name` | varchar(255) | NOT NULL | Nome do dataset |
| `dataset_slug` | varchar(191) | YES | Slug único do dataset |
| `owner` | varchar(255) | YES | Responsável pelo dado |
| `steward` | varchar(255) | YES | Guardião do dado |
| `classification` | varchar(30) | NOT NULL | `public` \| `internal` \| `restricted` \| `sensitive` |
| `retention_days` | bigint | YES | Dias de retenção |
| `sensitivity_level` | varchar(20) | NOT NULL | `none` \| `low` \| `medium` \| `high` |
| `lgpd_applicable` | boolean | NOT NULL | LGPD se aplica (padrão: `false`) |
| `lgpd_basis` | varchar(30) | YES | `consent` \| `legal_obligation` \| `public_policy` \| `research` \| `contract` \| `not_applicable` |
| `description` | text | YES | Descrição |
| `tags` | json | YES | Tags livres |
| `tenant_id` | bigint | YES | Tenant |
| `is_active` | boolean | NOT NULL | Padrão: `true` |
| `created_at` | datetime | NOT NULL | — |
| `updated_at` | datetime | YES | — |

Índice único: `dataset_slug`

---

## 11. Schema `warehouse.*` — Tabelas Analíticas Dinâmicas

O schema `warehouse` é criado dinamicamente pelo `AnalyticModel` ao executar uma transformação. Não tem estrutura fixa — cada modelo define sua tabela destino.

### Tabelas criadas automaticamente

| Tabela | Origem | Descrição |
|---|---|---|
| `warehouse.dw_agencias_turismo` | `staging.turismo_agencias` | Agências de turismo consolidadas |
| `warehouse.dw_*` | `staging.*` | Qualquer modelo analítico cadastrado |

### Estrutura típica de tabela warehouse

As colunas são definidas pelo campo `dimensions` + `metrics` do `AnalyticModel`:

```sql
-- Exemplo gerado pelo modelo "Agências de Turismo"
CREATE TABLE warehouse.dw_agencias_turismo (
    id              bigserial PRIMARY KEY,
    nome_fantasia   text,
    razao_social    text,
    cnpj            varchar(18),
    municipio       varchar(100),
    uf              varchar(2),
    situacao        varchar(50),
    data_referencia date,
    created_at      timestamp DEFAULT NOW()
);
```

> Consulte a entidade `AnalyticModel` e o `AnalyticsService` para a lógica de geração do DDL.

---

## 12. Schema `staging.*` — Dados Normalizados

O schema `staging` contém os dados após aplicação dos `DatasetColumnMappings` sobre os `RawFiles`. Tabelas são nomeadas a partir do slug do `ProviderPackage`.

### Estrutura típica

```sql
-- Exemplo: staging.turismo_agencias
-- Colunas definidas pelos DatasetColumnMappings do pacote "turismo_agencias"
CREATE TABLE staging.turismo_agencias (
    id              bigserial PRIMARY KEY,
    nome_fantasia   text,
    razao_social    text,
    cnpj            varchar(18),
    municipio       varchar(100),
    uf              varchar(2),
    situacao        varchar(50),
    _ingested_at    timestamp DEFAULT NOW(),
    _source_file    text
);
```

> As colunas `_ingested_at` e `_source_file` são adicionadas automaticamente pelo `TransformationService`.

---

## 13. Qdrant — Banco Vetorial

O Qdrant armazena os vetores gerados pelo `AiEmbeddingService`. O registro de metadados fica no PostgreSQL (`ai_embeddings`), o vetor numérico fica no Qdrant.

### Collection `plataforma360_embeddings`

| Campo | Tipo | Descrição |
|---|---|---|
| `id` | uuid | Corresponde a `ai_embeddings.vector_id` |
| `vector` | float[] | Vetor de embedding (dimensão varia por modelo) |
| `payload.source_type` | string | Tipo da fonte (`dataset`, `indicator`...) |
| `payload.source_id` | string | ID da entidade fonte |
| `payload.chunk_text` | string | Texto original vetorizado |
| `payload.provider_package_id` | integer | Pacote CKAN de origem (quando aplicável) |

### Busca vetorial típica

```python
# Busca semântica por similaridade
results = qdrant_client.search(
    collection_name="plataforma360_embeddings",
    query_vector=embed("agências de turismo em Olinda"),
    query_filter={"source_type": "dataset"},
    limit=5
)
```

---

## 14. PostgreSQL Kestra — Banco Interno do Orquestrador

O container `kestra-postgres` é gerenciado exclusivamente pelo Kestra. **Não modifique manualmente.**

| Schema | Descrição |
|---|---|
| `kestra` | Schema principal do Kestra |
| `kestra.flows` | Definições de flows |
| `kestra.executions` | Histórico de execuções |
| `kestra.logs` | Logs de execução |
| `kestra.triggers` | Configurações de triggers |

> A Plataforma360 **nunca acessa** o banco do Kestra diretamente — toda comunicação é via API REST do Kestra (`http://kestra:8080/api/v1/`).

---

## 15. Mapa de Relacionamentos

```
symfony_demo_user
    ├── symfony_demo_post (author_id)
    │       ├── symfony_demo_comment (post_id)
    │       └── symfony_demo_post_tag → symfony_demo_tag

data_providers
    └── provider_packages (data_provider_id)
            ├── dataset_resources (provider_package_id)
            │       └── raw_files (dataset_resource_id)
            │               ├── dataset_schemas (raw_file_id)
            │               └── dataset_quality_reports (raw_file_id)
            ├── dataset_column_mappings (provider_package_id)
            └── ingestion_runs (provider_package_id)

analytic_models
    └── [gera] warehouse.dw_* (via TransformationService)

ai_models ←── ai_agents (default_model_id)
ai_contexts ←── ai_agents (default_context_id)
ai_prompts ←── ai_agents (prompt_id)
ai_interactions (referencia model_name, agent_slug por valor)
ai_embeddings (vector_id aponta para Qdrant)

pipelines ←── pipeline_executions (pipeline_id)

organization
    ├── tourist_spot (organization_id)
    ├── tourism_event (organization_id)
    ├── tourist_guide (organization_id)
    └── accommodation (organization_id)
```

---

## 16. De-Para: CKAN → Staging → Warehouse

Fluxo completo de um dataset de agências de turismo do MTur:

### Etapa 1 — CKAN → `provider_packages`

| Campo CKAN (raw_metadata) | Coluna em `provider_packages` |
|---|---|
| `id` | `package_id` |
| `title` | `title` |
| `notes` | `description` |
| `resources[*]` | → `dataset_resources` |
| *(tudo)* | `raw_metadata` (json completo) |

### Etapa 2 — Resource → `raw_files`

| Campo CKAN resource | Coluna em `dataset_resources` | Arquivo físico |
|---|---|---|
| `id` | `resource_id` | — |
| `url` | `url` | Baixado para `storage/raw/{slug}/` |
| `format` | `format` | — |
| `hash` | `hash` | Verificado em `raw_files.file_hash` |

### Etapa 3 — `dataset_column_mappings` define o DE-PARA de colunas

| Coluna original (CSV) | → `original_column` | Coluna normalizada | `normalized_column` | Tipo destino |
|---|---|---|---|---|
| `NM_FANTASIA` | `NM_FANTASIA` | `nome_fantasia` | `nome_fantasia` | `string` |
| `CNPJ` | `CNPJ` | `cnpj` | `cnpj` | `string` + `normalize_cnpj` |
| `SG_UF` | `SG_UF` | `uf` | `uf` | `string` + `normalize_uf` |
| `NM_MUNICIPIO` | `NM_MUNICIPIO` | `municipio` | `municipio` | `string` + `trim` |
| `DS_SITUACAO_CADASTRAL` | `DS_SITUACAO_CADASTRAL` | `situacao` | `situacao` | `string` |

### Etapa 4 — `staging.turismo_agencias` → `warehouse.dw_agencias_turismo`

O `AnalyticModel` define:

```json
{
  "source_table": "staging.turismo_agencias",
  "target_table": "warehouse.dw_agencias_turismo",
  "dimensions": ["nome_fantasia", "cnpj", "uf", "municipio", "situacao"],
  "metrics": ["COUNT(*) AS total_agencias"],
  "filters": {"situacao": "ATIVA"}
}
```

O resultado é consultável via API:
```
GET /api/analytics/turismo/agencias
```
