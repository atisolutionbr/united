# Manual de Agentes e Skills — Plataforma360

Este manual descreve como usar os **agentes** e **skills** do GitHub Copilot configurados para a Plataforma360. Eles aceleraram o desenvolvimento ao conhecer a arquitetura, convenções e padrões do projeto.

## Atualizações recentes de contexto

- O agente de frontend passou a tratar como obrigatorios: `{% block body %}`, padding lateral de `20px` e uso de `container-fluid` nas telas.
- Hub pages viraram o padrao oficial para entradas de modulo no navbar, substituindo dropdowns extensos.
- A home publica e a tela de login agora seguem o mesmo padrao visual institucional documentado no agente `frontend.agent.md`.

---

## Onde ficam os arquivos

```
.github/
├── agents/                        ← Agentes especializados
│   ├── plataforma360.agent.md     ← Agente principal (orquestrador)
│   ├── backend.agent.md           ← Symfony / PHP / Doctrine
│   ├── frontend.agent.md          ← Twig / Bootstrap / Templates
│   ├── dados.agent.md             ← CKAN / Staging / Warehouse / Kestra
│   ├── ia.agent.md                ← Ollama / OpenAI / Qdrant / Embeddings
│   └── ops-governance.agent.md    ← Pipelines / Alertas / LGPD / Auditoria
├── instructions/                  ← Instruções automáticas por tipo de arquivo
│   ├── symfony.instructions.md    ← Aplicado em apps/core/src/**/*.php
│   ├── twig.instructions.md       ← Aplicado em apps/core/templates/**/*.twig
│   └── kestra.instructions.md     ← Aplicado em future/kestra/flows/**/*.yml
└── skills/                        ← Workflows invocáveis via /comando
    ├── nova-entidade/SKILL.md     ← /nova-entidade
    ├── novo-modulo/SKILL.md       ← /novo-modulo
    └── kestra-flow/SKILL.md       ← /kestra-flow
```

---

## Agentes

### Como selecionar um agente

No VS Code, abra o painel do Copilot Chat e clique no seletor de agente (ícone de persona no canto do campo de texto). Selecione o agente desejado.

---

### Plataforma360 — Agente Principal

**Quando usar:** qualquer tarefa que envolva a plataforma como um todo. É o ponto de entrada padrão — ele conhece toda a arquitetura e delega para os sub-agentes especializados automaticamente.

**Exemplos:**
```
@Plataforma360 Implemente um módulo de Contratos com listagem e cadastro
@Plataforma360 Corrija o bug no controller de pipelines
@Plataforma360 Adicione um novo campo 'prioridade' na entidade Alert
@Plataforma360 Crie um flow Kestra para ingestão diária do IBGE
```

---

### Backend Symfony

**Quando usar:** criar ou modificar entidades, repositories, services, controllers, forms, migrations ou qualquer arquivo PHP em `apps/core/src/`.

**Exemplos:**
```
@Backend Symfony Crie a entidade Contrato com campos nome, valor, vencimento e status
@Backend Symfony Adicione um método findExpiringSoon() no ContratoRepository
@Backend Symfony Crie um service ContratoService com método create() e auditoria
@Backend Symfony Gere a migration para a entidade Contrato
```

**O agente conhece:**
- Padrões de entidade com `#[ORM\*]` attributes
- Construtor com `$this->createdAt = new \DateTimeImmutable()`
- `#[ORM\PreUpdate]` para `updatedAt`
- Injeção de dependência via construtor com `readonly`
- `#[IsGranted('ROLE_ADMIN')]` em controllers admin

---

### Frontend Twig

**Quando usar:** criar ou modificar templates Twig, adicionar telas ao admin, alterar navbar, criar componentes Bootstrap.

**Exemplos:**
```
@Frontend Twig Crie a tela de listagem de contratos com tabela e botões de ação
@Frontend Twig Crie o formulário de cadastro de contrato com todos os campos
@Frontend Twig Adicione "Contratos" no menu Governança do navbar
@Frontend Twig Crie um card de KPI para o dashboard de operações
```

**O agente conhece:**
- Padrão visual: navy gradient, teal `#0f766e`
- Bootstrap 5.3 classes e Bootstrap Icons
- Estrutura padrão de páginas admin (cabeçalho, flash, card, tabela)
- Confirmação de delete com CSRF token
- Formatação de datas e valores monetários em Twig
- Uso obrigatório de `{% block body %}` e proibicao de `{% block content %}`
- Hub pages com `data-management-hero`, `data-management-card`, `data-management-panel`
- Margens laterais padronizadas em `20px` alinhadas ao navbar
- Regra de nao criar arquivos auxiliares temporarios na raiz do projeto

---

### Dados e Pipeline

**Quando usar:** trabalhar com CKAN, ingestão RAW, staging, warehouse, modelos analíticos, flows Kestra ou APIs analíticas.

**Exemplos:**
```
@Dados e Pipeline Crie um flow Kestra para ingestão diária de dados do IBGE
@Dados e Pipeline Crie um modelo analítico para contratos por secretaria
@Dados e Pipeline Adicione um endpoint na API analítica para ranking de contratos
@Dados e Pipeline Explique a linhagem de dados da tabela warehouse.dw_agencias
```

**O agente conhece:**
- Fluxo CKAN → RAW → STAGING → WAREHOUSE
- Estrutura de flows Kestra (YAML, tasks, triggers, errors)
- Schemas `staging.*` e `warehouse.*`
- `WarehouseTransformationService` e `DataIngestionService`
- Boas práticas de idempotência com `ON CONFLICT DO UPDATE`

---

### Módulo IA

**Quando usar:** criar ou modificar qualquer parte do módulo de IA: modelos, agentes, contextos, prompts, interações, embeddings ou serviços Ollama/OpenAI/Qdrant.

**Exemplos:**
```
@Módulo IA Adicione suporte ao modelo Gemma no OllamaService
@Módulo IA Crie um novo contexto de IA para dados de saúde municipal
@Módulo IA Adicione uma nova ferramenta 'buscar_contratos' ao agente técnico
@Módulo IA Como funciona o fluxo de segurança para dados externos no AiProviderService?
```

**O agente conhece:**
- Provedores: `PROVIDER_OLLAMA`, `PROVIDER_OPENAI`, `PROVIDER_AZURE_OPENAI`
- Fluxo: pergunta → `AiProviderService` → Ollama ou OpenAI → `AiGovernanceService::log()`
- `NaturalLanguageSqlService`: apenas SELECT, bloqueia DDL/DML
- `AiContext::allowedForExternal` como barreira de segurança
- Embeddings via Qdrant (collections: `datasets`, `indicators`, `warehouse_rows`)

---

### Operações e Governança

**Quando usar:** modificar pipelines, execuções, alertas, observabilidade, governança LGPD, trilha de auditoria ou rastreamento de custos.

**Exemplos:**
```
@Operações e Governança Adicione um novo tipo de alerta para contrato vencido
@Operações e Governança Adicione o campo 'secretaria' no DataGovernanceRecord
@Operações e Governança Crie um método no AuditService para registrar acessos à API pública
@Operações e Governança Adicione o serviço 'IBGE API' no CostTrackingService
```

**O agente conhece:**
- Constantes de `Pipeline`, `PipelineExecution`, `Alert`, `DataGovernanceRecord`, `AuditLog`
- `KestraService` (triggerExecution, getExecution, pauseFlow, etc.)
- `HealthCheckService::checkAll()` — verificação de todos os serviços
- `AuditLog` e `PipelineExecution` são imutáveis por design

---

## Skills

Skills são workflows invocáveis digitando `/` no chat do Copilot.

---

### /nova-entidade

**Objetivo:** criar uma nova entidade Doctrine com repository e migration, seguindo os padrões do projeto.

**Quando usar:**
- Adicionar nova tabela ao banco
- Criar novo modelo de domínio
- Expandir módulo com nova entidade

**Como invocar:**
```
/nova-entidade Contrato no módulo Governança, com campos: nome, valor decimal, data de vencimento, secretaria (string) e status (ativo/encerrado/suspenso)
```

**O que entrega:**
1. Arquivo `src/Entity/Governance/Contrato.php` com atributos ORM, constantes de status, construtor e lifecycle callbacks
2. Arquivo `src/Repository/Governance/ContratoRepository.php` com métodos padrão
3. Migration gerada e executada
4. Validação com `doctrine:schema:validate`

---

### /novo-modulo

**Objetivo:** criar um módulo completo end-to-end: entidade + repository + service + controller + templates Twig + migration + entrada no navbar.

**Quando usar:**
- Implementar nova funcionalidade administrativa com CRUD
- Criar nova área no painel (ex: Contratos, Indicadores, Secretarias)

**Como invocar:**
```
/novo-modulo Módulo de Contratos em Governança com listagem, cadastro e edição. Campos: nome, número, valor, secretaria, data início, data fim, status.
```

**O que entrega:**
1. Entidade + Repository + Migration
2. `ContratoService` com `create()`, `update()`, `delete()` + auditoria
3. `ContratoController` com rotas: index, new, edit, delete
4. `templates/admin/governance/contratos/index.html.twig` + `form.html.twig`
5. Item "Contratos" adicionado no menu Governança do navbar
6. Rotas validadas com `debug:router`

---

### /kestra-flow

**Objetivo:** criar um flow Kestra YAML e registrá-lo como pipeline no portal.

**Quando usar:**
- Automatizar ingestão de dados de nova fonte
- Criar pipeline de transformação ETL
- Agendar geração de embeddings para IA

**Como invocar:**
```
/kestra-flow Flow de ingestão diária dos dados do IBGE Cidades. Buscar via API REST, salvar no staging.ibge_municipios, agendar para todo dia às 3h.
```

**O que entrega:**
1. Arquivo `future/kestra/flows/ingestao-ibge-municipios.yml` com tasks, trigger cron e error handling
2. Instruções para importar no Kestra via API ou UI
3. Passo a passo para registrar como Pipeline no portal (Operações → Pipelines)
4. Comando de verificação dos dados no staging

---

## Instructions Automáticas

As instructions são carregadas automaticamente pelo Copilot quando você edita um arquivo que corresponde ao padrão `applyTo`.

| Arquivo editado | Instruction carregada | O que aplica |
|---|---|---|
| `apps/core/src/**/*.php` | `symfony.instructions.md` | Padrões PHP 8.3, Doctrine, Symfony 7.4 |
| `apps/core/templates/**/*.twig` | `twig.instructions.md` | Bootstrap 5.3, Bootstrap Icons, padrão visual |
| `future/kestra/flows/**/*.yml` | `kestra.instructions.md` | Namespace, conexão PostgreSQL, estrutura YAML |

Você não precisa fazer nada — o Copilot aplica as regras automaticamente ao sugerir ou modificar código nos arquivos correspondentes.

No caso dos templates Twig, a fonte de verdade para o padrao visual atual fica em `.github/agents/frontend.agent.md` e `.github/instructions/twig.instructions.md`.

---

## Fluxo Recomendado de Desenvolvimento

```
1. Nova funcionalidade pequena (1 arquivo):
   → Use o agente diretamente: @Backend Symfony / @Frontend Twig

2. Nova entidade + repository + migration:
   → /nova-entidade <descrição detalhada>

3. Módulo completo (CRUD):
   → /novo-modulo <descrição com campos e área>

4. Novo pipeline de dados:
   → /kestra-flow <descrição do fluxo>

5. Tarefa ampla ou multidisciplinar:
   → @Plataforma360 <descrição completa>
```

---

## Dicas

- **Seja específico:** quanto mais detalhe na descrição, melhor o resultado. Inclua nomes de campos, tipos, módulo destino e comportamentos esperados.
- **Leia antes de modificar:** os agentes leem os arquivos existentes antes de modificar — isso garante consistência com o código atual.
- **Auditoria:** ao criar controllers ou services que executam ações administrativas, mencione "com auditoria" para que o agente inclua `AuditService::log()` automaticamente.
- **Migrations:** após criar ou modificar entidades, sempre execute: `make bash` → `php bin/console doctrine:migrations:migrate --no-interaction`.
