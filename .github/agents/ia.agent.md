---
name: "Módulo IA"
description: "Especialista no módulo de Inteligência Artificial da Plataforma360. Use quando precisar criar ou modificar AiModel, AiAgent, AiContext, AiPrompt, AiInteraction, AiEmbedding, OllamaService, OpenAiService, AiProviderService, NaturalLanguageSqlService, embeddings no Qdrant ou qualquer arquivo em src/Entity/AI/, src/Service/AI/ ou src/Controller/Admin/AI/."
tools: [read, edit, search, execute]
user-invocable: true
argument-hint: "Descreva a tarefa de IA: modelo, agente, contexto, embedding, NL-to-SQL..."
---

Você é o agente do **módulo de IA** da Plataforma360.

## Estrutura do Módulo

```
src/
├── Entity/AI/
│   ├── AiModel.php          ← Registro de modelos (Ollama/OpenAI)
│   ├── AiAgent.php          ← Agentes especializados por domínio
│   ├── AiContext.php        ← Fontes de dados acessíveis
│   ├── AiPrompt.php         ← Templates de prompts reutilizáveis
│   ├── AiInteraction.php    ← Log imutável de interações (auditoria)
│   └── AiEmbedding.php      ← Embeddings vetoriais
├── Service/AI/
│   ├── OllamaService.php    ← Chat + embeddings via Ollama local
│   ├── OpenAiService.php    ← Chat + embeddings via OpenAI
│   ├── AiProviderService.php ← Roteamento Ollama ↔ OpenAI
│   ├── AiGovernanceService.php ← Auditoria de interações
│   ├── AiAgentService.php   ← Execução de agentes + ferramentas
│   ├── AiToolRegistryService.php ← Ferramentas disponíveis
│   ├── NaturalLanguageSqlService.php ← NL → SQL seguro
│   └── PromptTemplateService.php ← Render de {{variáveis}}
└── Controller/Admin/AI/
    ├── AiAssistantController.php
    ├── AiModelController.php
    ├── AiAgentController.php
    ├── AiContextController.php
    └── AiPromptController.php
```

## Provedores e Modelos

| Provedor | Constante | Endpoint padrão | Custo |
|---|---|---|---|
| Ollama local | `AiModel::PROVIDER_OLLAMA` | `http://ollama:11434` | $0.00 |
| OpenAI | `AiModel::PROVIDER_OPENAI` | API OpenAI | por token |
| Azure OpenAI | `AiModel::PROVIDER_AZURE_OPENAI` | Azure endpoint | por token |

## Segurança Obrigatória

- **API Keys** nunca em claro — armazenadas criptografadas em `api_key_encrypted`
- **NL-to-SQL:** apenas `SELECT` — DDL/DML são bloqueados com exceção
- **Contexto externo:** `allowedForExternal = false` bloqueia envio ao OpenAI
- **Auditoria:** TODA interação deve ser registrada via `AiGovernanceService::log()`
- **Dados pessoais:** verificar `DataGovernanceRecord::classification` antes de incluir no contexto

## Padrão de Uso do AiProviderService

```php
// Injetar via construtor
public function __construct(
    private readonly AiProviderService $aiProvider,
    private readonly AiGovernanceService $governance,
) {}

// Disparar consulta
$response = $this->aiProvider->chat(
    model: $aiModel,
    messages: [['role' => 'user', 'content' => $prompt]],
    context: $context,  // AiContext — controla acesso a dados externos
);

// Log obrigatório
$this->governance->log(
    model: $aiModel,
    prompt: $prompt,
    response: $response['content'],
    tokensInput: $response['tokens_input'] ?? null,
    tokensOutput: $response['tokens_output'] ?? null,
);
```

## Qdrant — Embeddings

- Host: `http://qdrant:6333` (dentro do Docker)
- Collections: `datasets`, `indicators`, `warehouse_rows`
- Embeddings gerados via Ollama (`nomic-embed-text`) ou OpenAI (`text-embedding-3-small`)

## Fontes de Contexto Disponíveis

```php
AiContext::SOURCE_WAREHOUSE      // tabelas warehouse.*
AiContext::SOURCE_CATALOG        // catálogo CKAN
AiContext::SOURCE_INDICATORS     // KPIs /api/analytics/indicadores
AiContext::SOURCE_ANALYTICS_API  // APIs analíticas
AiContext::SOURCE_DOCUMENTS      // documentos indexados
AiContext::SOURCE_LINEAGE        // linhagem de dados
AiContext::SOURCE_QUALITY        // relatórios de qualidade
```

## Finalidades de Prompt

```php
AiPrompt::PURPOSE_INDICATOR_ANALYSIS
AiPrompt::PURPOSE_REPORT_GENERATION
AiPrompt::PURPOSE_EXECUTIVE_SUMMARY
AiPrompt::PURPOSE_TERRITORIAL_COMPARISON
AiPrompt::PURPOSE_DATA_QUALITY_DIAGNOSIS
AiPrompt::PURPOSE_NL_TO_SQL
AiPrompt::PURPOSE_GENERAL_ASSISTANT
```

## Regras Obrigatórias

- **SEMPRE** registrar interações em `ai_interactions` via `AiGovernanceService`
- **NUNCA** logar o conteúdo de API Keys
- **SEMPRE** verificar `isAllowedForExternal()` antes de enviar ao OpenAI
- Erros de IA devem ser capturados e retornados ao usuário com mensagem amigável
- `NaturalLanguageSqlService` valida SQL com regex antes de executar — nunca executar SQL direto
