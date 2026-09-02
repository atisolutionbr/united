# Manual Técnico — Módulo de Inteligência Artificial

## Visão Geral

O módulo de IA da Plataforma360 implementa uma **arquitetura híbrida**: modelos locais via **Ollama** (soberania de dados, sem custo) e modelos externos via **OpenAI** (quando maior capacidade for necessária). Toda interação é auditada em `ai_interactions`.

## Estado atual do modulo

- O acesso ao modulo acontece pelo hub **IA** no navbar principal.
- A navegacao administrativa foi consolidada em links diretos para as telas do modulo, evitando dropdowns extensos.
- A entidade `AiModel` foi estabilizada para formularios novos, com inicializacao segura dos campos textuais principais.

### Componentes

| Componente | Tecnologia | Perfil Docker | Porta |
|---|---|---|---|
| LLM Local | Ollama | `ai` | 11434 |
| Banco Vetorial | Qdrant | `ai` | 6333 |
| Serviço de roteamento | `AiProviderService` | — | — |
| Auditoria | `AiGovernanceService` | — | — |
| NL-to-SQL | `NaturalLanguageSqlService` | — | — |

---

## 1. Subindo o Perfil AI

```bash
# Apenas IA (Ollama + Qdrant)
docker compose --profile ai up -d

# Plataforma completa com IA e Operações
docker compose --profile ai --profile ops up -d

# Ou via Makefile
make up-ai
make up-all
```

Verificar se os containers estão ativos:

```bash
docker compose ps
# plataforma360-ollama   Running
# plataforma360-qdrant   Running
```

---

## 2. Ollama — Gerenciamento de Modelos

### Baixar modelos

Os modelos precisam ser baixados manualmente após o primeiro start:

```bash
# Modelos recomendados por uso

# Conversação geral — leve (4GB VRAM)
docker compose exec ollama ollama pull llama3

# Mais leve ainda, para máquinas com pouca RAM (2GB)
docker compose exec ollama ollama pull mistral

# Geração de código (NL-to-SQL, scripts)
docker compose exec ollama ollama pull codellama

# Embeddings vetoriais (Qdrant)
docker compose exec ollama ollama pull nomic-embed-text
```

### Listar modelos baixados

```bash
docker compose exec ollama ollama list
```

### Verificar se o Ollama está respondendo

```bash
curl http://localhost:11434/api/tags
```

Ou dentro do container PHP:

```bash
docker compose exec php curl http://ollama:11434/api/tags
```

### Remover modelo

```bash
docker compose exec ollama ollama rm mistral
```

---

## 3. Registrar Modelos no Portal

Após baixar os modelos no Ollama, registre-os no portal para que apareçam no assistente.

**Menu:** IA → Modelos → Novo Modelo

### Campos disponíveis

| Campo | Obrigatório | Descrição |
|---|---|---|
| Nome | Sim | Nome de exibição (ex: `Llama 3 Local`) |
| Slug | Sim | Identificador único gerado automaticamente |
| Provedor | Sim | `local_ollama`, `openai`, `azure_openai`, `other` |
| Nome do Modelo | Sim | Nome exato como aparece no Ollama ou OpenAI (ex: `llama3`, `gpt-4o-mini`) |
| Endpoint | Não | URL base da API. Padrão para Ollama: `http://ollama:11434` |
| API Key | Apenas OpenAI | Criptografada no banco — nunca armazenada em claro |
| Temperature | Não | 0.0 a 1.0. Valores baixos = respostas mais precisas. Padrão: 0.7 |
| Max Tokens | Não | Limite de tokens por resposta |
| Context Window | Não | Tamanho máximo do contexto em tokens |
| Suporta Embeddings | Não | Marcar para modelos usados na busca vetorial |
| Modelo Padrão | Não | Se marcado, é selecionado automaticamente no assistente |
| Descrição | Não | Texto informativo para a equipe |

### Exemplo: Ollama local

```
Provedor: local_ollama
Nome do Modelo: llama3
Endpoint: http://ollama:11434
Temperature: 0.7
Modelo Padrão: ✓
Suporta Embeddings: ✗
```

### Exemplo: OpenAI externo

```
Provedor: openai
Nome do Modelo: gpt-4o-mini
Endpoint: (deixar vazio — usa padrão OpenAI)
API Key: sk-proj-...
Temperature: 0.3
Suporta Embeddings: ✗
```

> **Segurança:** A API Key é criptografada antes de ser gravada no banco. Ao editar, o campo aparece vazio — só preencha se quiser alterar.

---

## 4. Contextos de IA

Contextos definem **quais dados** o assistente pode consultar em cada pergunta. São a principal barreira de segurança para evitar que dados sensíveis sejam enviados a provedores externos.

**Menu:** IA → Contextos → Novo Contexto

### Campos

| Campo | Descrição |
|---|---|
| Nome | Identificação do contexto (ex: `Turismo Público`) |
| Fontes de dados | Seleção múltipla das fontes disponíveis (ver tabela abaixo) |
| Tabelas do Warehouse | Lista de tabelas `warehouse.*` que o assistente pode consultar |
| Permitido para provedor externo | Se desmarcado, bloqueia envio do contexto ao OpenAI |
| Máximo de linhas no contexto | Limita quantas linhas do warehouse são carregadas (padrão: 100) |

### Fontes disponíveis

| Código | O que inclui |
|---|---|
| `warehouse` | Tabelas analíticas do schema `warehouse.*` |
| `catalog` | Metadados dos datasets (CKAN, packages) |
| `indicators` | Indicadores executivos calculados |
| `analytics_api` | Endpoints `/api/analytics/*` |
| `documents` | Documentos e PDFs indexados |
| `lineage` | Dados de linhagem CKAN → Warehouse |
| `quality` | Relatórios de qualidade dos datasets |

### Boas práticas

- Crie um contexto `Dados Públicos` com `allowed_for_external = true` apenas para tabelas sem dados pessoais
- Crie um contexto `Dados Internos` com `allowed_for_external = false`
- Defina `max_rows_context` pequeno (50–100) para evitar timeouts em consultas grandes

---

## 5. Templates de Prompts

Templates pré-definidos com variáveis `{{placeholder}}` para reutilização.

**Menu:** IA → Prompts → Novo Template

### Campos

| Campo | Descrição |
|---|---|
| Nome | Identificação do template |
| Finalidade (Purpose) | Categoria do template (ver tabela) |
| Template do Prompt | Texto com variáveis `{{variavel}}` |
| Tipo de Contexto | Contexto padrão associado |
| Provedor | Se restrito a um provedor específico |
| Versão | Controle de versão do template |

### Finalidades disponíveis

| Código | Descrição |
|---|---|
| `indicator_analysis` | Análise de indicadores |
| `report_generation` | Geração de relatórios |
| `dataset_explanation` | Explicação de datasets |
| `executive_summary` | Resumo executivo |
| `territorial_comparison` | Comparativo territorial |
| `data_quality_diagnosis` | Diagnóstico de qualidade |
| `general_assistant` | Assistente geral |
| `nl_to_sql` | Conversão de linguagem natural para SQL |

### Exemplo de template

```
Nome: Análise de Turismo por Estado
Finalidade: territorial_comparison
Template:
  Analise os dados de turismo para o estado de {{estado}}.
  Apresente: total de agências, municípios com maior concentração,
  variação em relação ao período anterior e 3 recomendações estratégicas.
  Use os dados do warehouse disponíveis no contexto.
```

---

## 6. Agentes Especializados

Agentes combinam um modelo, um contexto e um template de prompt para criar assistentes especializados.

**Menu:** IA → Agentes → Novo Agente

### Campos

| Campo | Descrição |
|---|---|
| Nome | Nome do agente (ex: `Analista de Turismo`) |
| Tipo | `turismo`, `dados_publicos`, `executivo`, `tecnico` |
| Modelo Padrão | Modelo de IA a usar |
| Contexto Padrão | Contexto de dados associado |
| Template de Prompt | System prompt do agente |
| Ferramentas | Lista JSON de ferramentas disponíveis |

### Ferramentas disponíveis

```json
[
  "buscar_indicadores",
  "consultar_warehouse",
  "listar_datasets",
  "obter_qualidade",
  "obter_linhagem_dataset",
  "gerar_relatorio"
]
```

### Exemplo: Agente de Turismo

```
Nome: Analista de Turismo
Tipo: turismo
Modelo Padrão: Llama 3 Local
Contexto Padrão: Turismo Público
Ferramentas: ["buscar_indicadores", "consultar_warehouse"]
```

---

## 7. NL-to-SQL — Consultas em Linguagem Natural

O `NaturalLanguageSqlService` converte perguntas em português para SQL executado no warehouse.

### Segurança

- Apenas `SELECT` é permitido — DDL e DML são bloqueados
- Apenas tabelas do schema `warehouse.*` são acessíveis
- O SQL gerado é validado antes de executar
- A consulta é auditada em `ai_interactions`

### Exemplos de perguntas que funcionam

```
"Quantas agências de turismo existem por estado?"
"Quais os 10 municípios com mais agências cadastradas?"
"Qual a média de agências por estado na região Nordeste?"
"Mostre a evolução mensal do total de agências em 2025"
```

### Exemplos de perguntas que NÃO funcionam

```
"Apague todos os dados de turismo" → bloqueado (DELETE)
"Crie uma tabela de relatórios" → bloqueado (CREATE)
"Atualize o status do município X" → bloqueado (UPDATE)
"Liste os usuários do sistema" → fora do schema warehouse
```

---

## 8. Qdrant — Banco Vetorial

O Qdrant armazena embeddings para busca semântica (RAG — Retrieval-Augmented Generation).

### Verificar status

```bash
curl http://localhost:6333/health
# {"status":"ok"}

# Dentro do container PHP
docker compose exec php curl http://qdrant:6333/health
```

### Painel Web do Qdrant

```
http://localhost:6333/dashboard
```

### Collections criadas pela plataforma

| Collection | Conteúdo |
|---|---|
| `datasets` | Embeddings dos datasets do catálogo |
| `indicators` | Embeddings dos indicadores executivos |
| `warehouse_rows` | Embeddings de linhas selecionadas do warehouse |

### Gerar embeddings manualmente

Via console Symfony:

```bash
docker compose exec php php bin/console app:ai:generate-embeddings --dataset=agencias_turismo
```

---

## 9. Governança e Auditoria de IA

Toda interação com o assistente é registrada automaticamente em `ai_interactions`.

**Menu:** IA → Logs

### Campos registrados

| Campo | Descrição |
|---|---|
| Usuário | Identificador do usuário que fez a pergunta |
| Provedor | `local_ollama` ou `openai` |
| Modelo | Nome exato do modelo usado |
| Agente | Slug do agente utilizado (se aplicável) |
| Prompt | Pergunta enviada |
| Resposta | Resposta recebida |
| Tokens entrada | Tokens consumidos no input |
| Tokens saída | Tokens consumidos no output |
| Custo estimado | Valor em USD (zero para Ollama) |
| Duração (ms) | Tempo de resposta em milissegundos |
| Status | `success`, `failed`, `running` |
| Provedor externo | Se a requisição foi para OpenAI/Azure |

### Filtros disponíveis

- Por período (data início / data fim)
- Por provedor
- Por status
- Por usuário

---

## 10. Variáveis de Ambiente

Adicione ao `.env.local`:

```dotenv
# Ollama
OLLAMA_BASE_URL=http://ollama:11434

# OpenAI (opcional — apenas se for usar)
OPENAI_API_KEY=sk-proj-...
OPENAI_ORG_ID=org-...

# Qdrant
QDRANT_HOST=qdrant
QDRANT_PORT=6333
```

> Nunca comite `.env.local` no git. Use `.env.local` que está no `.gitignore`.

---

## 11. Troubleshooting

### Ollama não responde

```bash
# Verificar logs
docker compose logs ollama --tail=50

# Reiniciar
docker compose restart ollama

# Verificar se o modelo foi baixado
docker compose exec ollama ollama list
```

### Modelo não aparece no portal

1. Confirme que o modelo foi baixado: `ollama list`
2. Confirme que foi registrado em **IA → Modelos**
3. O campo **Nome do Modelo** deve ser idêntico ao retornado por `ollama list`

### Resposta muito lenta

- Modelos grandes (llama3:70b) requerem GPU ou muita RAM
- Use `mistral` ou `llama3:8b` para servidores sem GPU
- Ajuste `max_tokens` no cadastro do modelo para limitar o tamanho da resposta

### OpenAI retorna erro 401

- Verifique se a API Key está correta no cadastro do modelo
- A chave deve começar com `sk-proj-` ou `sk-`
- Verifique saldo/créditos na conta OpenAI

### Qdrant sem dados

- Execute a geração de embeddings: `php bin/console app:ai:generate-embeddings`
- Verifique se há dados no warehouse (`warehouse.*` tabelas)
- Acesse o dashboard: `http://localhost:6333/dashboard`
