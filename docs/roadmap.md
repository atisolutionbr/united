# Roadmap

## Fase 1 - Core Instalavel

- Docker Compose com Nginx, PHP 8.3 FPM, PostgreSQL/PostGIS e Adminer.
- Symfony 7 com Doctrine, Twig, Bootstrap 5 e API Platform.
- Homepage institucional, healthcheck e OpenAPI.
- Home publica com secao de avisos/postagens usando o modulo de blog em leitura publica.
- Tela de login administrativa em portugues e alinhada ao padrao visual institucional.

## Fase 2 - Dados Territoriais e Pipeline Operacional

- Modelo territorial base.
- Ingestao de dados abertos via modulo CKAN no Core.
- Download fisico de resources para `storage/raw`.
- Registro de metadata operacional em `raw_files`.
- Preview real de datasets CSV/XLSX.
- Descoberta automatica de schema em `dataset_schemas`.
- Organizacao das zonas raw, staging e processed.
- APIs publicas versionadas.
- Modulo CKAN no Core para cadastro de provedores, sincronizacao de `package_list`, consulta de `package_show`, selecao de pacotes monitorados e execucao sincrona da pipeline `download -> raw -> preview -> schema`.

## Fase 3 - Analytics e Observabilidade

- Dashboards territoriais.
- Indicadores turisticos.
- Logs estruturados, metricas e traces.
- Grafana e Superset.
- Evolucao da camada CKAN para verificacao diaria, jobs assincronos e disparo de pipelines Kestra ou Messenger sobre a base operacional ja existente.

## Fase 4 - Data Warehouse, Analytics e Metabase ✅ Implementada

- Camada `warehouse.*` no PostgreSQL com schemas fact/dim/dw.
- Entidade `AnalyticModel` para definir transformações STAGING → WAREHOUSE.
- Serviço `WarehouseTransformationService` — orquestração de transformações analíticas.
- Serviço `MetabaseService` — integração com Metabase (teste de conexão, sync).
- `MetabaseConfig` — configuração de instância Metabase com status de conexão.
- `MetabaseDashboard` — registro e embed de dashboards Metabase.
- `AnalyticsHistory` — trilha de auditoria de todos os eventos analíticos.
- APIs analíticas: `/api/analytics/indicadores`, `/turismo/agencias`, `/ranking`, `/lineage`.
- Linhagem de dados (CKAN → RAW → STAGING → WAREHOUSE → DASHBOARDS).
- Embed responsivo de dashboards via iframe integrado ao visual da plataforma.

## Fase 5 - Camada de Inteligência Artificial Híbrida ✅ Implementada

- **IA Local (Ollama)**: `OllamaService` — chat, embed, listagem de modelos. Perfil Docker `ai` com `ollama` e `qdrant`.
- **IA Externa (OpenAI)**: `OpenAiService` — chat e embeddings com estimativa de custo. Chave armazenada criptografada.
- **Roteamento**: `AiProviderService` — despacha para Ollama ou OpenAI com base no modelo configurado. Bloqueia envio externo por política de contexto.
- **Governança**: `AiGovernanceService` — registra todas as interações em `ai_interactions` com tokens, custo, duração, status e provider.
- **Registro de Prompts**: `AiPromptRepository` + `PromptTemplateService` — templates reutilizáveis com variáveis `{{placeholders}}`.
- **Agentes especializados**: `AiAgentService` + `AiToolRegistryService` — agentes por domínio (turismo, dados públicos, executivo, técnico) com ferramentas de consulta a warehouse, catálogo e indicadores.
- **NL-to-SQL seguro**: `NaturalLanguageSqlService` — converte perguntas em SQL SELECT para o warehouse. Bloqueia DDL/DML.
- **Entidades**: `AiModel`, `AiPrompt`, `AiContext`, `AiAgent`, `AiInteraction`, `AiEmbedding`.
- **Assistente Web**: Chat institucional com histórico, quick prompts, seletor de modelo/contexto/agente e indicador de latência.
- **Menu IA no navbar**: Assistente, Insights, Modelos, Agentes, Contextos, Prompts, Logs, Configurações.
- **Segurança**: API keys nunca em claro, aviso de IA externa, bloqueio por `allowedForExternal`, apenas SELECT no NL-to-SQL, auditoria completa.
- Menu atualizado: Dados → Warehouse/Modelos/Linhagem, Inteligência → Dashboards BI, Integrações → Metabase/APIs Analíticas.
- Manual do usuário em `docs/manual-usuario.md`.

## Fase 6 - Observabilidade, Orquestração, Governança Operacional e Automação Enterprise ✅ Implementada

### Infraestrutura
- **Kestra** adicionado ao `docker-compose.yml` com perfil Docker `ops` (`postgres_kestra` + `kestra`). Porta configurável via `KESTRA_PORT` (default 8082).
- `KestraService`: cliente REST para a API do Kestra — triggerExecution, getExecution, listExecutions, listFlows, pauseFlow, resumeFlow, getExecutionLogs.

### Operações
- **Entidades**: `Pipeline`, `PipelineExecution`, `Alert`, `SystemMetric`.
- **Repositórios**: `PipelineRepository`, `PipelineExecutionRepository`, `AlertRepository`, `SystemMetricRepository`.
- **Serviços**: `PipelineService` (orquestra trigger/sync com Kestra), `AlertService` (cria/reconhece/resolve alertas).
- **Observabilidade**: `HealthCheckService` — verifica Symfony, PostgreSQL, Kestra, Ollama, Qdrant, Metabase e Storage em uma única chamada.
- **Controllers** (todos em `/admin/operations/`, `ROLE_ADMIN`):
  - `OperationsOverviewController` — dashboard com KPIs, saúde dos serviços, execuções e alertas recentes
  - `PipelineController` — CRUD + trigger + pause + YAML viewer
  - `ExecutionController` — listagem paginada e detalhe com sync Kestra
  - `ObservabilityController` — grid de saúde em tempo real
  - `AlertController` — listagem + acknowledge + resolve
  - `AiMetricsController` — uso de IA, tokens, custo estimado por provedor
  - `LogsController` — auditoria + execuções de pipeline combinados
- **Templates**: 11 templates em `templates/admin/operations/` com design consistente (cards, badges, tabelas hover).
- **4 Pipelines de seed**: ingestão CKAN turismo, transformação staging, carga warehouse, geração de embeddings.

### Governança
- **Entidades**: `DataGovernanceRecord` (LGPD, classificação, retenção), `AuditLog` (trilha completa), `Tenant` (multi-tenant), `CostRecord` (rastreamento de custos USD).
- **Repositórios**: `DataGovernanceRecordRepository`, `AuditLogRepository`, `TenantRepository`, `CostRecordRepository`.
- **Serviços**: `AuditService` (registra ações + request metadata), `CostTrackingService` (custos OpenAI, serviços externos).
- **Controllers** (em `/admin/governance/`, `ROLE_ADMIN`):
  - `DataGovernanceController` — CRUD de registros com classificação LGPD
  - `AiGovernanceController` — rastreamento de uso IA por provedor (local vs. externo)
  - `CostController` — breakdown de custos por serviço e série diária
  - `AuditController` — trilha de auditoria com filtros por ação e usuário
- **Templates**: 5 templates em `templates/admin/governance/`.

### Navbar
- Dois novos menus adicionados: **Operações** (Visão Geral, Pipelines, Execuções, Observabilidade, Alertas, Métricas IA, Logs) e **Governança** (Dados, Governança IA, Custos, Auditoria).
- Variáveis `operationsActive` e `governanceActive` para highlight ativo.
- Expansao do uso de hub pages para Dados, Inteligencia, Integracoes, IA, Operacoes, Governanca e Plataforma.

### UX e consistencia visual
- Layout lateral padronizado com `20px` no navbar e no corpo das paginas.
- Home publica reorganizada com cards institucionais antes da secao de publicacoes.
- Login redesenhado em dois cards, mantendo acessos de exemplo no card de entrada.

### Banco de dados (migração `Version20260522220000`)
- 8 tabelas criadas: `pipelines`, `pipeline_executions`, `alerts`, `system_metrics`, `data_governance_records`, `audit_logs`, `tenants`, `cost_records`.
- Seeds: 1 tenant padrão, 4 pipelines de exemplo, 1 alerta informativo.

### Banco de dados (migração `Version20260523143000`)
- Renomeacao das tabelas legadas `symfony_demo_post`, `symfony_demo_comment`, `symfony_demo_tag` e `symfony_demo_user` para `post`, `comment`, `tag` e `app_user`.

### Segurança
- YAML dos pipelines sanitizado com `strip_tags()` antes de persistir.
- `AuditLog` registra IP, User-Agent e usuário em todas as ações sensíveis.
- `CostRecord` vincula custos de IA externa para rastreabilidade financeira.
- Multi-tenant preparado com campo `tenantId` nas tabelas de governança.

## Fase 7 - Realtime Government Platform (Futuro)

Transformar a plataforma de pipelines agendados para **arquitetura orientada a eventos**, com ingestão em tempo real, IoT Hub, stream processing e Command Center operacional.

### 7.1 Event Bus e Message Broker
- **RabbitMQ** como broker de mensagens (Docker). Futuramente Kafka para alta escala.
- Eventos padronizados: `dataset.imported`, `pipeline.failed`, `ai.insight.generated`, `sensor.updated`, `dashboard.refreshed`, `alert.triggered`.
- Workers Symfony (Messenger) consumindo filas assíncronas.
- Nova área no portal: **Operações → Eventos** — visualização de eventos em tempo real.

### 7.2 IoT Hub — Sensores Urbanos
- **Eclipse Mosquitto** como MQTT Broker (Dockerizado).
- Recepção de telemetria: sensores urbanos, GPS, energia, água, clima, trânsito, iluminação, câmeras.
- Protocolos: MQTT (principal), WebSocket streaming, HTTP streaming.
- Arquitetura: `Sensor → MQTT Broker → IoT Hub → Event Bus → Warehouse → IA → Alertas`.
- Nova área: **Infraestrutura → IoT Hub**.

### 7.3 Time Series Database
- **TimescaleDB** (extensão PostgreSQL) para dados temporais de sensores.
- Schema: `sensor_id`, `timestamp`, `value`, `unit`, `location`.
- Retenção automática com políticas de compressão.

### 7.4 Stream Processing e Realtime Analytics
- Processamento de dados em tempo real: temperatura, chuva, trânsito, energia, telemetria.
- Nova área: **Inteligência → Tempo Real** — streaming, sensores, eventos, alertas vivos, mapas ao vivo.
- Dashboards de streaming no Metabase ou Grafana integrado ao portal.

### 7.5 Rule Engine — Automação Governamental
- Nova área: **Automação → Regras**.
- Regras configuráveis via interface: `SE temperatura > 40 ENTÃO gerar alerta crítico`.
- Conecta evento → pipeline → IA → alerta → ação de forma declarativa.
- Fluxo completo: `dataset atualizado → pipeline executa → warehouse atualiza → IA gera insight → gestor recebe alerta`.

### 7.6 Sistema de Notificações
- Nova área: **Operações → Notificações**.
- Canais: e-mail, WhatsApp, Telegram, Webhook, push notification.
- Disparado por eventos do Event Bus ou alertas da Fase 6.

### 7.7 Mapas em Tempo Real
- PostGIS + realtime para visualização geoespacial ao vivo.
- Casos de uso: sensores urbanos no mapa, ônibus, energia, clima, turismo.

### 7.8 Digital Government Command Center
- Painel unificado: cidade, dados, eventos, alertas, IA, IoT, analytics e operações em uma única tela.
- Base para Digital Twin — representação virtual operacional do município.
- Preparação para Edge Computing: edge nodes e mini servidores municipais para soberania local.

### Infraestrutura adicionada na Fase 7
- `rabbitmq` no `docker-compose.yml` com perfil `events`
- `mosquitto` (MQTT) com perfil `iot`
- TimescaleDB como extensão do PostgreSQL existente ou instância separada

## Fase 8 - Interoperabilidade e IA Avançada (Futuro)

- MCP Layer aprimorada.
- AI Hub com múltiplos agentes autônomos.
- Vector DB com Qdrant para busca semântica expandida em datasets.
- Conectores com sistemas públicos (SEI, SIGCON, SICONV).
- Automação Kestra com flows complexos e dependências.
- Multi-tenant completo com isolamento de dados por prefeitura/secretaria.
