# Arquitetura da Plataforma360

A Plataforma360 adota uma arquitetura modular orientada a instalacao local, soberania de dados, interoperabilidade publica e evolucao incremental. A plataforma foi desenhada para combinar aplicacao institucional, plataforma de dados, BI, observabilidade tecnica, automacao operacional e trilhas futuras de IA e IoT sem sobrepor responsabilidades.

O objetivo dessa arquitetura e permitir que cada componente execute bem uma funcao especifica: o portal central governa o ecossistema, a camada de dados processa e entrega informacao, as ferramentas analiticas exibem inteligencia de negocio, a observabilidade acompanha a saude tecnica, e os hubs especializados tratam automacao operacional, IA e telemetria.

## 1. Visao Geral da Plataforma 360

A Plataforma360 e uma plataforma publica modular orientada a dados, automacao, IA, BI, observabilidade e governanca. O desenho prioriza desacoplamento entre aplicacao principal e servicos especializados, reduzindo dependencia entre camadas e facilitando implantacao local por municipio, secretaria ou consorcio.

Como principios de base, a plataforma preserva instalacao local, soberania de dados, interoperabilidade publica, abordagem open source, APIs documentadas, contratos evolutivos e clean architecture pragmatica sempre que a separacao entre dominio e infraestrutura agregar valor real.

Os blocos arquiteturais sao organizados da seguinte forma:

```text
Plataforma360
├── Portal Central de Governanca (apps/core)
│   └── Symfony 7 · PHP 8.3
│       ├── Fase 1-3: CKAN, Pipeline, RAW, Staging, Qualidade
│       ├── Fase 4: Warehouse, Metabase embed, Analytics APIs
│       ├── Fase 5: IA híbrida (Ollama local + OpenAI externo)
│       └── Fase 6: Operações, Pipelines Kestra, Observabilidade, Governança
├── Data Warehouse
│   └── PostgreSQL (schemas: staging.*, warehouse.*, public.*)
├── Orquestração de Dados
│   └── Kestra (perfil Docker: ops)
├── BI e Analytics
│   └── Metabase (embed via iframe)
├── IA Local
│   ├── Ollama (LLM — perfil Docker: ai)
│   └── Qdrant (banco vetorial — perfil Docker: ai)
├── Observabilidade (futuro)
│   └── Grafana
├── AI Hub (futuro)
│   └── n8n
├── IoT Hub (futuro)
│   └── Sensores + MQTT + Telemetria
└── Shared
    └── Autenticação, contratos, segurança e padrões comuns
```

Em termos praticos, o Core em `apps/core` permanece como portal central de governanca e configuracao, enquanto os demais componentes evoluem como servicos complementares e especializados. A pasta `future` continua reservada para trilhas de evolucao da data platform, observabilidade, AI Hub, IoT Hub e demais modulos avancados.

## 2. Principio de Separacao de Responsabilidades

A arquitetura da Plataforma360 adota separacao explicita de responsabilidades para evitar sobreposicao funcional, acoplamento indevido e retrabalho operacional.

- Symfony nao substitui BI.
- Metabase nao substitui aplicacao.
- Grafana nao substitui BI.
- Kestra nao substitui n8n.
- n8n nao substitui Data Platform.
- Cada componente tem papel especifico, fronteira clara e responsabilidade propria.

Na pratica, isso significa que a plataforma deve evitar concentrar dashboards analiticos, pipelines, observabilidade, automacoes operacionais e gestao institucional dentro de um unico sistema. O valor da arquitetura esta exatamente na especializacao de cada peca.

## 3. Responsabilidades por Componente

### 3.1 Symfony - Portal Central de Governanca

Responsavel por:

- Gestao de usuarios e permissoes.
- Cadastro de municipios, secretarias e orgaos.
- Cadastro de fontes de dados.
- Cadastro de integracoes.
- Catalogo de indicadores oficiais.
- Gestao de modulos da plataforma.
- Centralizacao de links e embeds dos dashboards.
- Acionamento de fluxos do Kestra via API REST (Fase 6).
- Exibicao de cards executivos e status resumidos.
- Administracao das configuracoes do ecossistema.
- Catalogo de provedores CKAN, pacotes sincronizados e monitoramento manual inicial.
- Consulta administrativa de metadados `package_list` e `package_show`.
- Registro institucional das execucoes de sincronizacao e da trilha de ingestao.
- **Fase 5**: Assistente de IA híbrido (Ollama local + OpenAI), agentes especializados, NL-to-SQL, embeddings vetoriais, governança de IA.
- **Fase 6**: Dashboard de operações, gerenciamento de pipelines Kestra, observabilidade de serviços, alertas, trilha de auditoria, rastreamento de custos, classificação LGPD.
- Homepage publica institucional com cards de modulos e secao de postagens/avisos publicos lidos sem autenticacao.
- Navegacao administrativa organizada por hub pages para Dados, Inteligencia, Integracoes, IA, Operacoes, Governanca e Plataforma.

Nao deve ser responsavel por:

- Construir dashboards analiticos complexos.
- Substituir Metabase ou outra ferramenta de BI.
- Armazenar grandes volumes de dados brutos.
- Executar pipelines pesados de dados.

### 3.1.1 Modulo CKAN de Provedores de Dados

Dentro do Core Symfony, a Plataforma360 passou a contar com um modulo administrativo para provedores de dados CKAN. Esse modulo vive no menu `Dados` e organiza a camada de ingestao publica em seis superficies:

- `Visao Geral / Provedores de Dados`: cadastro da fonte CKAN, base URL, rotas de `package_list` e `package_show`, status e sincronizacao manual;
- `Pacotes CKAN`: inventario persistido de pacotes retornados pela API, com ativacao de monitoramento por pacote;
- `Ingestao de Dados`: visao operacional da pipeline real, com download, parser, preview e descoberta de schema;
- `Arquivos RAW`: catalogo dos artefatos fisicos salvos localmente em `storage/raw/{provider}/{package}/{year}/{month}`;
- `Preview Dataset`: inspecao tabular das primeiras linhas dos arquivos CSV/XLSX importados;
- `Historico de Execucoes`: trilha de auditoria das sincronizacoes, downloads, parser e schema discovery.

O dominio persistente foi estruturado em seis tabelas principais:

- `data_providers`: cadastro de provedores CKAN;
- `provider_packages`: pacotes sincronizados de cada provedor;
- `dataset_resources`: resources retornados por `package_show`;
- `raw_files`: arquivos fisicos baixados, hash SHA256, mime, path local, status e controle de duplicidade;
- `dataset_schemas`: colunas detectadas a partir dos parsers de preview;
- `ingestion_runs`: historico de execucao, mensagens e logs estruturados.

Na implementacao atual, o Core tambem passou a concentrar uma camada operacional desacoplada em `src/Service/DataPipeline` com tres servicos centrais:

- `RawFileStorageService`: download HTTP via Symfony HttpClient, deteccao de MIME, escrita fisica em RAW e deduplicacao por hash;
- `DatasetPreviewService`: parser CSV/XLSX, leitura de cabecalho, preview das primeiras linhas e inferencia de schema;
- `PipelineJobService`: orquestracao sincrona do fluxo `download -> raw -> preview -> schema`, preservando a API necessaria para futura troca por Kestra, RabbitMQ ou Symfony Messenger.

Essa camada prepara o Core para orquestrar ingestao publica real sem acoplar ETL pesado, staging ou warehouse ao portal institucional.

### 3.2 Kestra - Orquestracao de Dados

Responsavel por:

- Ingestao de dados.
- ETL e ELT.
- Agendamento de pipelines.
- Reprocessamento de fluxos.
- Automacao tecnica da Data Platform.
- Execucao de jobs.
- Registro de execucoes.
- Integracao com APIs, bancos, arquivos e servicos externos.

A partir da **Fase 6**, o Kestra está integrado ao `docker-compose.yml` principal via perfil `ops`, compartilhando a rede Docker `plataforma360` com o portal Symfony. O portal pode disparar e acompanhar execucoes via API REST do Kestra.

Para subir: `docker compose --profile ops up -d`

Nao deve ser responsavel por:

- Interface de usuario final.
- Dashboards executivos.
- Atendimento via chatbot.
- Automacoes conversacionais.

### 3.3 Metabase - BI e Analytics de Negocio

Responsavel por:

- Dashboards customizados.
- Indicadores de gestao publica.
- Relatorios analiticos.
- Graficos por secretaria.
- Series historicas.
- Comparativos entre municipios.
- Visualizacoes para prefeito, secretario, gestor e tecnico.
- Exportacao de relatorios.

Nao deve ser responsavel por:

- Gestao de usuarios da plataforma principal.
- Orquestracao de pipelines.
- Logs tecnicos de infraestrutura.
- Cadastro estrutural do ecossistema.

### 3.4 Grafana - Observabilidade Tecnica

Responsavel por:

- Metricas tecnicas.
- Logs de servicos.
- Saude dos containers.
- Uptime.
- CPU, memoria e disco.
- Monitoramento de APIs.
- Monitoramento de banco de dados.
- Alertas tecnicos.
- Monitoramento de Kestra, n8n e servicos internos.
- Futuro monitoramento IoT.

Nao deve ser responsavel por:

- Dashboards de negocio.
- Indicadores de politicas publicas.
- Cadastros administrativos.
- Operacao de fluxos de dados.

### 3.5 PostgreSQL - Banco Relacional

Responsavel por:

- Dados relacionais da aplicacao.
- Metadados da plataforma.
- Configuracoes.
- Usuarios, permissoes e cadastros.
- Dados estruturados tratados.

O PostgreSQL e o banco relacional principal da Plataforma360 e tambem o repositorio central de metadados. Quando necessario, pode sustentar extensoes e modelos voltados a dados geograficos e analiticos, sem assumir o papel de data lake.

O banco `app` organiza os dados em tres schemas principais:

- `public` — tabelas gerenciadas pelo Doctrine (entidades, governanca, IA, operacoes, turismo).
- `staging.*` — dados normalizados apos aplicacao dos `DatasetColumnMappings` sobre os arquivos RAW.
- `warehouse.*` — tabelas analiticas criadas dinamicamente pelos `AnalyticModels`.

A instancia `kestra-postgres` e um banco separado, gerenciado exclusivamente pelo Kestra.

Modelagem completa em [docs/modelo-banco.md](modelo-banco.md).

### 3.6 MinIO - Data Lake e Armazenamento de Objetos

Responsavel por:

- Arquivos brutos.
- CSV, JSON, XML, PDFs e documentos.
- Dados recebidos antes do tratamento.
- Historico de ingestoes.
- Armazenamento compativel com S3.
- Base para pipelines de dados.

O MinIO funciona como camada de armazenamento de objetos e data lake inicial, desacoplando o recebimento de arquivos da persistencia relacional da aplicacao.

### 3.7 n8n - AI Hub e Automacoes Operacionais

Responsavel por:

- Webhooks.
- Integracoes rapidas.
- Fluxos com WhatsApp, e-mail, formularios e APIs.
- Chatbots.
- Automacoes com IA.
- Atendimento digital.
- Encaminhamento de demandas.
- Conexao entre sistemas externos e canais digitais.

Nao deve ser responsavel por:

- Data Lake.
- Pipelines analiticos pesados.
- BI principal.
- Observabilidade tecnica central.

### 3.8 Ollama - IA Local (Fase 5)

Responsavel por:

- Execucao de modelos de linguagem localmente (LLM).
- Chat com contexto institucional (turismo, dados públicos, warehouse).
- Geracao de embeddings vetoriais para busca semantica.
- Alternativa local e soberana ao OpenAI/GPT.

Integrado ao Symfony via `OllamaService`. Ativado com o perfil Docker `ai`.

Para subir: `docker compose --profile ai up -d`

### 3.9 Qdrant - Banco Vetorial (Fase 5)

Responsavel por:

- Armazenamento de embeddings vetoriais.
- Busca semântica sobre datasets, indicadores e documentos.
- Base para RAG (Retrieval-Augmented Generation) com Ollama ou OpenAI.

Integrado ao Symfony via `AiEmbedding` e serviços de embeddings. Ativado com o perfil Docker `ai`.

### 3.10 IoT Hub - Sensores e Cidades Inteligentes

Responsavel por:

- Recebimento de dados de sensores.
- MQTT.
- Telemetria.
- Eventos de dispositivos.
- Regras de alerta.
- Integracao com dados urbanos.
- Futuras aplicacoes de cidade inteligente.

O IoT Hub permanece reservado para a evolucao da arquitetura em cenarios com sensores, dispositivos conectados e telemetria urbana em tempo real.

### 3.11 Apps - Aplicacoes da Plataforma

Responsavel por:

- Portal institucional.
- Painel administrativo.
- APIs.
- Servicos digitais.
- Modulos especificos por secretaria ou area de governo.
- Home publica com leitura de avisos internos publicados via modulo de posts.
- Experiencia administrativa padronizada com navbar unico, hubs de entrada e login institucional em portugues.

Apps representam os modulos de negocio da Plataforma360. Eles materializam funcionalidades para usuarios finais e equipes gestoras, utilizando servicos compartilhados e integracoes controladas com os demais componentes.

### 3.12 Shared - Camada Transversal

Responsavel por:

- Autenticacao.
- Autorizacao.
- Contratos de integracao.
- DTOs.
- Schemas.
- Padroes comuns.
- Seguranca.
- Observabilidade compartilhada.
- Bibliotecas internas.

Shared e a camada transversal que define consistencia tecnica entre modulos, reduz duplicacao de implementacao e padroniza contratos internos e externos.

## 4. Matriz de Responsabilidades

| Componente | Responsabilidade Principal | O que pode fazer | O que nao deve fazer |
| --- | --- | --- | --- |
| Symfony | Governanca e configuracao central | Usuarios, permissoes, cadastros, catalogos, modulos, links de dashboards, acionamento do Kestra, IA hibrida, operacoes, governanca | BI complexo, data lake, pipelines pesados, substituicao do Metabase |
| Kestra | Orquestracao de dados | Ingestao, ETL/ELT, agendamentos, reprocessamentos, jobs e integracoes tecnicas | Interface final, dashboards executivos, chatbot, automacoes conversacionais |
| Metabase | BI e analytics de negocio | Dashboards, relatorios, comparativos, series historicas e exportacoes | Gestao estrutural da plataforma, pipelines, logs tecnicos, cadastros administrativos |
| PostgreSQL | Persistencia relacional e metadados | Dados estruturados, staging, warehouse, configuracoes, usuarios, permissoes | Armazenamento massivo de arquivos brutos como papel principal |
| Ollama | IA local soberana | LLM local, embeddings, chat institucional, alternativa ao OpenAI | BI, pipelines pesados, bancos vetoriais |
| Qdrant | Banco vetorial | Embeddings, busca semantica, RAG | Dados relacionais, dashboard, pipeline ETL |
| Grafana | Observabilidade tecnica (futuro) | Metricas, logs, alertas, uptime, monitoramento de servicos | BI de negocio, indicadores publicos, cadastros administrativos |
| n8n | Automacoes operacionais e AI Hub (futuro) | Webhooks, integracoes rapidas, chatbot, IA, atendimento digital e canais externos | Data lake, pipelines pesados, BI principal, observabilidade central |
| IoT Hub | Telemetria e sensores (futuro) | MQTT, eventos, telemetria, alertas e integracao com dispositivos | Papel de BI, portal administrativo ou orquestrador central de dados gerais |
| Apps | Funcionalidades de negocio | Portais, APIs, modulos setoriais e servicos digitais | Substituir componentes especializados de BI, dados ou observabilidade |
| Shared | Capacidade transversal | Autenticacao, contratos, seguranca, DTOs, schemas e padroes comuns | Concentrar regras de negocio especificas de um modulo |

## 5. Fluxo de Funcionamento

Exemplo de fluxo operacional da arquitetura (Fases 1–6):

1. Uma fonte pública ou sistema municipal disponibiliza dados.
2. O módulo CKAN do Symfony cadastra o provedor, consulta `package_list` e persiste o inventário de pacotes.
3. O administrador marca quais pacotes devem entrar em monitoramento.
4. O Core consulta `package_show`, persiste resources e registra `ingestion_runs`.
5. **Fase 6**: Um pipeline é disparado pelo menu Operações → Pipelines, acionando o Kestra via API REST.
6. O Kestra executa a ingestão, transforma e promove dados para as tabelas `staging.*` e `warehouse.*`.
7. O portal sincroniza o status da execução via `KestraService`.
8. Metabase gera indicadores e dashboards a partir do warehouse.
9. **Fase 5**: O assistente de IA consulta dados do warehouse em linguagem natural via Ollama local ou OpenAI.
10. Symfony apresenta visão central, embeds de dashboards, operações e governança.
11. **Fase 6**: Alertas são gerados automaticamente em caso de falhas ou indisponibilidade de serviços.
12. **Fase 6**: Auditoria registra todas as ações administrativas com IP e usuário.

```text
Fonte publica ou sistema municipal
        |
        v
  Kestra (perfil ops)
        |
        v
PostgreSQL warehouse.* / staging.*
        |
        v
     Metabase ←→ embed no Symfony
        |
        v
     Symfony
  (Portal + IA + Operações + Governança)
        |
        ↕
  Ollama + Qdrant (perfil ai) — IA local soberana
```

## 6. Navegacao e UX do Portal

O portal adota o padrão **hub page por módulo** para simplificar a barra de navegação e melhorar o acesso a funcionalidades.

### Princípio

Cada módulo de topo (IA, Governança, Operações) expõe uma **página hub** com cards visuais que descrevem e acessam suas sub-funcionalidades. O menu da navbar aponta diretamente para a hub, sem dropdown com dezenas de itens.

### Estrutura de hub pages

| Módulo | Rota hub | Rota |
| --- | --- | --- |
| Dados | Gestão de Dados | `/admin/data-management` |
| Operações | Visão Geral | `/admin/operations` |
| IA | Hub IA | `/admin/ai` |
| Governança | Hub Governança | `/admin/governance` |

### Navbar simplificado

```
Início | Inteligência ▾ | Dados ▾ | Integrações ▾ | IA | Operações ▾ | Governança | Plataforma ▾
```

- **IA** — link direto para `/admin/ai` (hub com 6 cards)
- **Governança** — link direto para `/admin/governance` (hub com 4 cards)
- **Operações** — dropdown mínimo: Visão Geral, Pipelines, Execuções

### Diretriz

Sub-páginas que já aparecem na hub **não devem** aparecer no dropdown do menu. O menu serve para acesso rápido entre módulos; a navegação interna é responsabilidade da hub page.

## 7. Decisao Arquitetural Oficial

Para a Plataforma360 (Fases 1–6 implementadas) estão em uso:

- Symfony (portal, IA, operações, governança)
- Kestra (orquestração — perfil `ops`)
- PostgreSQL (dados, warehouse, metadados)
- Metabase (BI e dashboards)
- Ollama (IA local — perfil `ai`)
- Qdrant (banco vetorial — perfil `ai`)

**Planejados para o futuro:**
- Grafana (observabilidade técnica avançada)
- n8n (AI Hub, automações operacionais)
- IoT Hub (sensores e telemetria)

MinIO foi avaliado e não foi adotado no MVP — o armazenamento de arquivos brutos usa o filesystem local em `storage/raw/`.

## 8. Diretrizes de Evolucao

- Evitar duplicacao de dashboards no Symfony.
- Usar Metabase para analytics de negocio.
- Usar Grafana para observabilidade tecnica (futuro).
- Usar Kestra para pipelines — disparado pelo portal via API.
- Usar n8n para automacoes operacionais (futuro).
- Usar Ollama para IA local soberana; OpenAI apenas quando necessário e com auditoria.
- Manter documentacao atualizada a cada mudanca arquitetural.

Como diretriz complementar, o portal central deve permanecer desacoplado da execucao de pipelines e do armazenamento bruto; a data platform deve continuar separada da automacao conversacional; e a observabilidade deve ser tratada como capacidade tecnica transversal, nao como ferramenta de BI.
