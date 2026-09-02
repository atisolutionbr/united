# Manual do Usuário — Plataforma360

## Guia Passo a Passo: Da Ingestão até os Dashboards

Este manual descreve a ordem correta para cadastrar e operar cada funcionalidade da Plataforma360, desde a origem dos dados até a publicação de dashboards analíticos.

---

## Acesso inicial e navegacao atual

- **Home publica:** `http://localhost:8080/` exibe os cards institucionais da plataforma e uma secao de postagens/avisos publicos.
- **Login administrativo:** `http://localhost:8080/admin/login`
- **Navbar principal:** usa links diretos para hubs de `Inteligencia`, `Dados`, `Integracoes`, `IA`, `Operacoes`, `Governanca` e `Plataforma`.

### Como ler os avisos publicos

1. Acesse a home publica.
2. Role ate a secao de postagens logo abaixo dos cards institucionais.
3. Clique em **Ler postagem** para abrir o conteudo completo.
4. Clique em **Ver todas as postagens** para abrir a listagem publica do blog.

---

## Ordem de Operação Recomendada

```
1. Provedor de Dados (CKAN)
2. Pacotes CKAN
3. Ingestão de Dados (RAW)
4. Mapeamento de Colunas
5. Transformação → STAGING
6. Qualidade dos Dados
7. Catálogo de Datasets
8. Modelos Analíticos (Warehouse)
9. Configuração Metabase
10. Registro de Dashboards
11. Incorporação (Embed) de Dashboards
```

---

## 1. Cadastrar Provedor de Dados

**Menu:** Dados → Provedores de Dados

Um **provedor de dados** é um endpoint CKAN público (ex: Portal Brasileiro de Dados Abertos do Turismo).

**Como cadastrar:**
1. Acesse **Dados → Provedores de Dados → Novo Provedor**
2. Preencha:
   - **Nome:** Identificação interna (ex: "MTUR - Cadastur")
   - **URL Base:** Endereço raiz da API CKAN (ex: `https://dados.turismo.gov.br`)
   - **Path Package List:** `/api/3/action/package_list`
   - **Path Package Show:** `/api/3/action/package_show?id={package_id}`
3. Marque **Ativo**
4. Clique em **Salvar**

Após salvar, clique em **Sincronizar** para buscar os pacotes disponíveis.

---

## 2. Selecionar Pacotes CKAN para Monitoramento

**Menu:** Dados → Pacotes CKAN

**Como configurar:**
1. Acesse **Dados → Pacotes CKAN**
2. Localize o pacote desejado (ex: "agencias-turismo")
3. Clique em **Monitorar** para ativar o download automático
4. Clique em **Detalhar** para visualizar os resources disponíveis (CSV, XLSX, etc.)

---

## 3. Executar a Ingestão de Dados

**Menu:** Dados → Ingestão de Dados

A ingestão faz o **download físico** dos arquivos do provedor para `storage/raw/`.

**Como executar:**
1. Acesse **Dados → Ingestão de Dados**
2. Localize a execução desejada e clique em **Baixar Arquivos**
3. Acompanhe o status: `pending → running → success`
4. Após o download, acesse **Dados → Arquivos RAW** para confirmar os arquivos

---

## 4. Fazer Preview do Dataset

**Menu:** Dados → Preview Dataset

Antes de transformar, visualize o conteúdo do arquivo.

**Como usar:**
1. Acesse **Dados → Preview Dataset**
2. Selecione o arquivo desejado
3. Verifique:
   - Colunas detectadas automaticamente
   - Tipos de dados inferidos
   - Amostras dos primeiros registros
   - Encoding do arquivo

---

## 5. Configurar Mapeamento de Colunas

**Menu:** Dados → Mapeamento (Fase 3)

O mapeamento define como as colunas do arquivo original serão normalizadas.

**Como configurar:**
1. Acesse **Dados → Mapeamento**
2. Selecione o pacote/arquivo
3. Para cada coluna, defina:
   - **Coluna normalizada:** nome padronizado (ex: `nome_municipio`)
   - **Tipo de dado:** string, integer, decimal, date, boolean
   - **Regra de normalização:** trim, upper, lower, slug, etc.
   - **Obrigatório:** se o campo é requerido
4. Salve o mapeamento

---

## 6. Executar Transformação para STAGING

**Menu:** Dados → Preview Staging

A transformação aplica o mapeamento e grava os dados normalizados no STAGING.

**Como executar:**
1. Acesse **Dados → Arquivos RAW**
2. Localize o arquivo e clique em **Transformar**
3. O sistema executa: normalização → validação → relatório de qualidade → importação staging
4. Após concluído, acesse **Dados → Preview Staging** para confirmar

---

## 7. Verificar Qualidade dos Dados

**Menu:** Dados → Qualidade (Fase 3)

**Como verificar:**
1. Acesse **Dados → Qualidade**
2. Visualize por dataset:
   - **Score de qualidade** (0–100%)
   - Total de linhas válidas, inválidas e duplicadas
   - Campos nulos
   - Erros de validação detalhados

Datasets com score abaixo de 70% devem ser revisados antes de ir ao Warehouse.

---

## 8. Visualizar o Catálogo de Datasets

**Menu:** Dados → Catálogo (Fase 3)

O catálogo lista todos os datasets normalizados prontos para análise.

**Como usar:**
1. Acesse **Dados → Catálogo**
2. Pesquise por nome, tipo ou provedor
3. Clique em um dataset para ver seu schema detalhado

---

## 9. Criar Modelo Analítico (Warehouse)

**Menu:** Dados → Modelos Analíticos

Um **modelo analítico** define como os dados do STAGING serão transformados em tabelas otimizadas no **Warehouse**.

**Como criar:**
1. Acesse **Dados → Modelos Analíticos → Novo Modelo**
2. Preencha:
   - **Nome:** Identificação do modelo (ex: "Agências de Turismo por Estado")
   - **Tabela Origem:** tabela staging (ex: `staging_agencias_turismo`)
   - **Tabela Destino:** tabela warehouse (ex: `warehouse.dw_agencias_turismo`)
   - **Dimensões:** colunas de agrupamento (ex: `estado,municipio,regiao`)
   - **Métricas:** colunas numéricas (ex: `total_agencias`)
   - **Estratégia de Atualização:** manual, diário, horário ou semanal
3. Clique em **Criar modelo**
4. Na listagem, clique no botão ▶ para **executar a transformação**

Após executar, o status muda para **Pronto** e a tabela é criada no schema `warehouse` do PostgreSQL.

---

## 10. Visualizar o Warehouse

**Menu:** Dados → Data Warehouse

Após executar modelos, acesse o painel do Warehouse para verificar:
- Tabelas geradas no schema `warehouse`
- Número de linhas por tabela
- Histórico de execuções e transformações

---

## 11. Configurar Integração com Metabase

**Menu:** Integrações → Metabase

O Metabase se conecta **diretamente ao PostgreSQL Warehouse** e gera dashboards analíticos.

**Pré-requisito:** ter o Metabase rodando (veja `docker-compose.yml` ou instância externa).

**Como configurar:**
1. Acesse **Integrações → Metabase**
2. Preencha:
   - **URL Base:** endereço da sua instância Metabase (ex: `http://localhost:3000`)
   - **Nome do Banco:** como o banco está cadastrado no Metabase
   - **Usuário / Senha:** credenciais do Metabase
   - **Secret Key:** chave para embedding (opcional — necessária para incorporação)
3. Clique em **Salvar Configuração**
4. Clique em **Testar Conexão** para verificar

**No Metabase (configuração inicial):**
1. Acesse o Metabase via browser
2. Vá em **Admin → Databases → Add database**
3. Selecione **PostgreSQL**
4. Configure: host `postgres`, porta `5432`, database `app`, schema `warehouse`
5. Salve e aguarde sincronização

---

## 12. Registrar Dashboards do Metabase

**Menu:** Inteligência → Dashboards BI (ou Integrações → Metabase → Dashboards)

**Como registrar:**
1. No Metabase, crie seu dashboard com as perguntas desejadas
2. Para obter a URL de incorporação: clique em **Compartilhar → Incorporar no site**
3. Copie a URL gerada
4. Na Plataforma360, acesse **Integrações → Metabase → Dashboards → Registrar Dashboard**
5. Preencha:
   - **Nome:** nome do dashboard
   - **URL de Embed:** URL copiada do Metabase
   - **Dataset:** tabela warehouse utilizada (ex: `warehouse.dw_agencias_turismo`)
   - **Origem:** fonte dos dados (ex: "Cadastur / MTUR")
   - Marque **Permitir incorporação**
6. Clique em **Registrar dashboard**

---

## 13. Abrir Dashboard Incorporado

**Menu:** Inteligência → Dashboards BI

1. Na listagem de dashboards, clique no botão 👁 (Abrir)
2. O dashboard do Metabase será carregado via **iframe** dentro da plataforma
3. Metadados como dataset, origem e tipo são exibidos abaixo do dashboard

---

## 14. Visualizar Indicadores Executivos

**Menu:** Inteligência → Indicadores

O painel de indicadores exibe KPIs estratégicos calculados a partir do warehouse:
- Total de agências cadastradas
- Estados e municípios atendidos
- Tendências e comparativos

---

## 15. Usar as APIs Analíticas

As APIs analíticas estão disponíveis em `/api/analytics/`:

| Endpoint | Descrição |
|---|---|
| `GET /api/analytics/indicadores` | Indicadores executivos do warehouse |
| `GET /api/analytics/turismo/agencias` | Dados de agências com ranking |
| `GET /api/analytics/turismo/municipios` | Ranking por estado |
| `GET /api/analytics/ranking` | Ranking + série temporal mensal |
| `GET /api/analytics/lineage` | Linhagem de dados (contagens por camada) |
| `GET /api/analytics/models` | Lista de modelos analíticos ativos |

**Exemplo de uso:**
```bash
curl https://sua-plataforma.gov.br/api/analytics/indicadores
```

---

## 16. Visualizar Linhagem de Dados

**Menu:** Dados → Linhagem

O painel de linhagem mostra o fluxo completo dos dados:

```
CKAN → RAW → STAGING → WAREHOUSE → INDICADORES → DASHBOARDS
```

Para cada camada, você vê a contagem de itens processados e se está ativa.

---

## Fase 5 — Inteligência Artificial Híbrida

> **Pré-requisito:** iniciar com o perfil `ai`: `docker compose --profile ai up -d` (ou `make up-ai`)

---

### 17. Usar o Assistente de IA

**Menu:** IA → Assistente

O assistente permite fazer perguntas sobre os dados da plataforma em português, sem precisar saber SQL.

#### O que aparece na tela

- **Área central:** histórico da conversa (pergunta + resposta)
- **Barra lateral esquerda:**
  - Seletor de **Modelo** (Ollama local ou OpenAI)
  - Seletor de **Contexto** (quais dados o assistente pode ver)
  - Seletor de **Agente** (especialização temática)
- **Rodapé:** campo de texto para digitar a pergunta + botão Enviar
- **Quick prompts:** botões de atalho para perguntas comuns

#### Passo a passo

1. Acesse **IA → Assistente**
2. Na barra lateral, selecione:
   - **Modelo:** escolha `Llama 3 Local` para consultas internas sem custo, ou um modelo OpenAI para respostas mais elaboradas
   - **Contexto:** selecione `Turismo Público` para dados de agências/municípios. Se não houver contexto configurado, o assistente responde sem acesso ao warehouse
   - **Agente:** `Analista de Turismo` para perguntas setoriais, `Executivo` para resumos gerenciais
3. Digite sua pergunta no campo de texto:
   - ✅ *"Quantas agências de turismo existem no Nordeste?"*
   - ✅ *"Quais os 5 estados com mais agências cadastradas?"*
   - ✅ *"Gere um resumo executivo dos indicadores de turismo"*
4. Clique em **Enviar** ou pressione Enter
5. Aguarde a resposta — o indicador de latência (ms) aparece ao lado da resposta

#### Indicadores visuais

| Ícone/Badge | Significado |
|---|---|
| 🟢 Local | Resposta via Ollama — dados não saíram do servidor |
| 🔴 Externo | Resposta via OpenAI — interação auditada e com custo |
| ⏱ Xms | Tempo de resposta em milissegundos |

> **Dado sensível?** Se o contexto tiver `Permitido para externo = Não`, o sistema bloqueia automaticamente o envio para OpenAI e exibe uma mensagem de aviso.

---

### 18. Configurar Modelos de IA

**Menu:** IA → Modelos

Lista todos os modelos cadastrados com provedor, status e se é o padrão.

#### Como adicionar um modelo Ollama (local — sem custo)

1. Acesse **IA → Modelos → Novo Modelo**
2. Preencha:

| Campo | Valor exemplo |
|---|---|
| Nome | `Llama 3 Local` |
| Provedor | `local_ollama` |
| Nome do Modelo | `llama3` *(deve ser idêntico ao nome no Ollama)* |
| Endpoint | `http://ollama:11434` |
| Temperature | `0.7` *(0 = preciso, 1 = criativo)* |
| Max Tokens | `2048` |
| Modelo Padrão | ✓ |

3. Salve

> **Modelos disponíveis no Ollama:** `llama3`, `mistral`, `codellama`, `gemma`. Para baixar: acesse o terminal e execute `docker compose exec ollama ollama pull llama3`

#### Como adicionar OpenAI (externo — tem custo)

1. Provedor: `openai`
2. Nome do Modelo: `gpt-4o-mini` *(mais barato)* ou `gpt-4o` *(mais capaz)*
3. Cole a **API Key** no campo — ela é criptografada ao salvar
4. Deixe Endpoint vazio (usa o padrão OpenAI)
5. Salve

> ⚠️ Toda interação com OpenAI é registrada em **IA → Logs** com custo estimado em USD.

#### Ações na listagem

- **Editar:** alterar configurações do modelo
- **Testar:** envia uma pergunta simples para verificar se o modelo responde
- **Desativar:** remove da seleção do assistente sem excluir

---

### 19. Configurar Contextos de IA

**Menu:** IA → Contextos

Contextos definem **quais dados** o assistente pode acessar. É a camada de segurança que impede vazamento de dados sensíveis para provedores externos.

#### Como criar um contexto

1. Acesse **IA → Contextos → Novo Contexto**
2. Preencha:

| Campo | Descrição | Exemplo |
|---|---|---|
| Nome | Nome de exibição | `Turismo Público` |
| Fontes de Dados | O que o assistente pode consultar | `warehouse`, `indicators` |
| Tabelas do Warehouse | Tabelas específicas acessíveis | `warehouse.dw_agencias_turismo` |
| Permitido para externo | Libera envio ao OpenAI | ✗ para dados sensíveis |
| Máximo de linhas | Limite de linhas carregadas no contexto | `100` |

#### Fontes de dados disponíveis

| Fonte | O que inclui |
|---|---|
| `warehouse` | Tabelas analíticas processadas |
| `catalog` | Catálogo de datasets (CKAN) |
| `indicators` | KPIs executivos |
| `analytics_api` | APIs `/api/analytics/*` |
| `lineage` | Linhagem de dados por camada |
| `quality` | Relatórios de qualidade |

---

### 20. Gerenciar Templates de Prompts

**Menu:** IA → Prompts

Templates são instruções pré-definidas com variáveis `{{estado}}`, `{{periodo}}`, `{{dataset}}` que o assistente preenche automaticamente.

#### Como criar um template

1. Acesse **IA → Prompts → Novo Template**
2. Preencha:

| Campo | Descrição |
|---|---|
| Nome | Identificação do template |
| Finalidade | Categoria (análise, relatório, diagnóstico, etc.) |
| Template do Prompt | Texto com `{{variáveis}}` nos pontos que mudam |
| Versão | Controle de versão (incremente ao alterar substancialmente) |

#### Finalidades disponíveis

| Finalidade | Quando usar |
|---|---|
| Análise de indicadores | Perguntas sobre KPIs e métricas |
| Geração de relatório | Documentos estruturados |
| Resumo executivo | Síntese para gestores |
| Comparativo territorial | Comparação entre estados/municípios |
| Diagnóstico de qualidade | Avaliar completude e consistência dos dados |
| Assistente geral | Conversação livre |
| NL-to-SQL | Conversão de pergunta em consulta SQL |

---

### 21. Gerenciar Agentes Especializados

**Menu:** IA → Agentes

Agentes são combinações de modelo + contexto + prompt que criam assistentes focados em um domínio.

#### Como criar um agente

1. Acesse **IA → Agentes → Novo Agente**
2. Preencha:

| Campo | Descrição |
|---|---|
| Nome | Nome de exibição (ex: `Analista de Turismo`) |
| Tipo | `turismo`, `dados_publicos`, `executivo`, `tecnico` |
| Modelo Padrão | Modelo de IA que o agente usa |
| Contexto Padrão | Contexto de dados associado |
| Template de Prompt | System prompt que define o comportamento |
| Ferramentas | Ações que o agente pode executar |

#### Agentes pré-configurados

| Agente | Tipo | Especialidade |
|---|---|---|
| Analista de Turismo | turismo | Agências, destinos, indicadores setoriais |
| Analista de Dados | dados_publicos | Catálogo CKAN, qualidade, linhagem |
| Assistente Executivo | executivo | Resumos, relatórios, comparativos |
| Técnico de Dados | tecnico | SQL, qualidade, diagnóstico técnico |

---

### 22. Consultar Logs de IA

**Menu:** IA → Logs

Exibe o histórico completo de todas as interações com o assistente.

#### Colunas da listagem

| Coluna | Descrição |
|---|---|
| Data/Hora | Quando ocorreu a interação |
| Usuário | Quem fez a pergunta |
| Provedor | `local_ollama` ou `openai` |
| Modelo | Nome do modelo utilizado |
| Tokens entrada | Tokens da pergunta |
| Tokens saída | Tokens da resposta |
| Custo (USD) | Estimativa de custo ($0.00 para Ollama) |
| Duração | Tempo de resposta em ms |
| Status | `success` (verde), `failed` (vermelho) |
| Externo | 🔴 se foi enviado para OpenAI |

#### Como usar para controle

- Monitore **custos acumulados** de OpenAI pelo campo Custo (USD)
- Identifique **perguntas que falharam** pelo status `failed` e a mensagem de erro
- Verifique **quem usou IA externa** pelo badge "Externo"

---

## Fase 6 — Operações e Governança

### 23. Visão Geral de Operações

**Menu:** Operações → Visão Geral

Dashboard com a situação operacional em tempo real.

#### Cards de KPI (linha superior)

| Card | O que mostra |
|---|---|
| Pipelines Ativos | Total de pipelines com status `active` |
| Falhas Hoje | Execuções com status `failed` nas últimas 24h |
| Alertas Críticos | Alertas com nível `critical` e status `active` |
| Saúde Geral | `healthy` / `degraded` / `down` com base em todos os serviços |

#### Seção de saúde dos serviços

Grid com o status atual de cada serviço: Symfony, PostgreSQL, Kestra, Ollama, Qdrant, Metabase, Storage. Cada card exibe:
- Status (verde/amarelo/vermelho)
- Informação complementar (versão, latência, modelos carregados)

---

### 24. Gerenciar Pipelines

**Menu:** Operações → Pipelines

Lista de pipelines cadastrados com tipo, trigger, status e última execução.

#### Como cadastrar um pipeline

1. Acesse **Operações → Pipelines → Novo Pipeline**
2. Preencha:

| Campo | Obrigatório | Descrição |
|---|---|---|
| Nome | Sim | Nome descritivo (ex: `Ingestão CKAN Turismo`) |
| Tipo | Sim | Categoria do pipeline (ver tabela abaixo) |
| Trigger | Sim | Como é disparado |
| Cron Expression | Se agendado | Expressão cron (ex: `0 2 * * *` = todo dia às 2h) |
| Kestra Namespace | Sim | Namespace no Kestra (ex: `plataforma360`) |
| Kestra Flow ID | Sim | ID do flow no Kestra (ex: `ingestao-ckan-turismo`) |
| Dataset (Slug) | Não | Dataset associado ao pipeline |
| Descrição | Não | Texto explicativo |
| Kestra YAML | Não | Definição do flow para referência e versionamento |
| Ativo | — | Ligado/Desligado |

#### Tipos de pipeline

| Tipo | Descrição |
|---|---|
| `ingestion` | Ingestão de dados de fontes externas |
| `transformation` | Transformação STAGING → WAREHOUSE |
| `warehouse_load` | Carga analítica no warehouse |
| `embedding_generation` | Geração de embeddings para IA |
| `quality_check` | Verificação de qualidade |
| `export` | Exportação de dados |
| `sync` | Sincronização com sistemas externos |

#### Tipos de trigger

| Trigger | Descrição |
|---|---|
| `manual` | Disparado manualmente pelo botão ▶ |
| `cron` | Agendado por expressão cron |
| `event` | Disparado por evento da plataforma |
| `webhook` | Disparado por chamada HTTP externa |

#### Ações na listagem

| Botão | Ação |
|---|---|
| ▶ Disparar | Executa o pipeline no Kestra agora |
| ⏸ Pausar | Pausa o flow no Kestra |
| ✏ Editar | Editar cadastro |
| 📄 YAML | Visualiza o YAML do flow |
| 🗑 Excluir | Remove o pipeline (não remove o flow no Kestra) |

---

### 25. Acompanhar Execuções

**Menu:** Operações → Execuções

#### Colunas da listagem

| Coluna | Descrição |
|---|---|
| Pipeline | Nome do pipeline executado |
| Status | Status atual (ver tabela) |
| Trigger | Como foi disparado (manual, cron, api, event) |
| Disparado por | Usuário ou sistema que iniciou |
| Início | Data/hora de início |
| Fim | Data/hora de conclusão |
| Duração | Tempo total em segundos |

#### Status das execuções

| Status | Cor | Significado |
|---|---|---|
| `CREATED` | Cinza | Criada, aguardando início |
| `RUNNING` | Azul | Em execução no Kestra |
| `SUCCESS` | Verde | Concluída com sucesso |
| `FAILED` | Vermelho | Falhou — ver detalhe |
| `CANCELLED` | Laranja | Cancelada manualmente |
| `WARNING` | Amarelo | Concluída com avisos |

#### Ver detalhe de uma execução

1. Clique no ícone 👁 na linha da execução
2. A tela de detalhe exibe:
   - **Informações gerais:** pipeline, status, duração, quem disparou
   - **Inputs:** parâmetros enviados ao Kestra
   - **Outputs:** dados retornados pelo flow
   - **Logs:** saída completa do Kestra (sincronizada via API)
   - **Mensagem de erro:** se falhou, exibe o stack trace do Kestra

> O status é sincronizado automaticamente com o Kestra ao abrir o detalhe.

---

### 26. Observabilidade dos Serviços

**Menu:** Operações → Observabilidade

Verifica a saúde de todos os serviços em tempo real. A página faz uma verificação ao carregar.

#### O que é verificado por serviço

| Serviço | Verificação | O que indica problema |
|---|---|---|
| Symfony | Memória PHP atual | > 128MB pode indicar leak |
| PostgreSQL | `SELECT version()` + contagem de tabelas | Timeout = banco inacessível |
| Kestra | `GET /api/v1/flows` | Kestra offline ou perfil `ops` não iniciado |
| Ollama | `GET /api/tags` | Ollama offline ou perfil `ai` não iniciado |
| Qdrant | `GET /health` | Qdrant offline |
| Metabase | `GET /api/health` | Metabase offline ou não configurado |
| Storage | Verifica diretórios `storage/raw` e `storage/staging` | Disco cheio ou permissão negada |

#### Status possíveis

- 🟢 **healthy** — serviço respondendo normalmente
- 🟡 **degraded** — respondendo mas com aviso
- 🔴 **down** — serviço inacessível

---

### 27. Gerenciar Alertas

**Menu:** Operações → Alertas

Alertas são gerados automaticamente pelo sistema em caso de falha de pipeline, serviço offline ou anomalia detectada.

#### Colunas da listagem

| Coluna | Descrição |
|---|---|
| Nível | `info` (azul), `warning` (amarelo), `critical` (vermelho) |
| Tipo | Categoria do alerta |
| Título | Descrição resumida |
| Status | `active`, `acknowledged`, `resolved` |
| Criado em | Data/hora do alerta |

#### Tipos de alerta comuns

| Tipo | Gerado quando |
|---|---|
| `pipeline_failed` | Uma execução falhou |
| `pipeline_stuck` | Execução sem atualização por muito tempo |
| `service_offline` | Serviço não responde na observabilidade |
| `data_quality_low` | Score de qualidade abaixo do limite |
| `storage_full` | Espaço em disco crítico |

#### Fluxo de tratamento

1. Alerta é criado com status `active`
2. Responsável clica em **✓ Reconhecer** → status muda para `acknowledged`
   - Registra quem reconheceu e quando
3. Após resolver o problema, clica em **✓✓ Resolver** → status muda para `resolved`
   - Registra a data/hora de resolução

> Alertas `critical` ficam destacados no topo e aparecem no card da **Visão Geral de Operações**.

---

### 28. Governança de Dados (LGPD)

**Menu:** Governança → Dados

Registre a política de governança de cada dataset conforme a LGPD.

#### Como criar um registro

1. Acesse **Governança → Dados → Novo Registro**
2. Preencha:

| Campo | Obrigatório | Descrição |
|---|---|---|
| Nome do Dataset | Sim | Identificação (ex: `agencias_turismo`) |
| Classificação | Sim | Nível de acesso |
| Sensibilidade | Sim | Impacto se exposto |
| Aplica-se LGPD | — | Marcar se contém dados pessoais |
| Base Legal LGPD | Se LGPD = sim | Fundamento jurídico |
| Responsável (Owner) | Não | Gestor responsável |
| Steward | Não | Responsável técnico pelos dados |
| Retenção (dias) | Não | Política de retenção (ex: 365) |
| Descrição | Não | Observações adicionais |

#### Classificações

| Nível | Descrição |
|---|---|
| `publico` | Pode ser publicado sem restrição |
| `interno` | Uso interno da prefeitura |
| `restrito` | Acesso limitado a equipes específicas |
| `sensivel` | Dados pessoais ou estratégicos — máxima proteção |

#### Sensibilidade

| Nível | Descrição |
|---|---|
| `none` | Sem impacto se exposto |
| `low` | Impacto baixo |
| `medium` | Impacto moderado |
| `high` | Impacto alto — pode violar LGPD ou segurança |

#### Bases legais LGPD

- Consentimento do titular
- Obrigação legal
- Interesse legítimo
- Execução de contrato
- Proteção à vida
- Tutela da saúde

---

### 29. Rastrear Custos de Serviços

**Menu:** Governança → Custos

Painel de custos financeiros de serviços externos (principalmente OpenAI).

#### O que é exibido

| Seção | Descrição |
|---|---|
| Total do mês atual | Soma em USD de todos os serviços no mês corrente |
| Breakdown por serviço | Custo separado por `openai`, `azure_openai`, `other` |
| Série diária | Gráfico dos últimos 30 dias |

#### Serviços e custos

| Serviço | Custo |
|---|---|
| Ollama (local) | $0.00 — sempre |
| Qdrant (local) | $0.00 — sempre |
| OpenAI GPT-4o-mini | ~$0.15/1M tokens |
| OpenAI GPT-4o | ~$5.00/1M tokens |

> Os custos são registrados automaticamente a cada interação com o assistente de IA.

---

### 30. Consultar Trilha de Auditoria

**Menu:** Governança → Auditoria

Registro imutável de todas as ações administrativas na plataforma.

#### Filtros disponíveis

- **Por ação:** selecione o tipo de ação
- **Por usuário:** busca pelo e-mail ou identificador

#### Tipos de ação registrados

| Ação | Quando ocorre |
|---|---|
| `pipeline_run` | Pipeline disparado |
| `config_change` | Configuração alterada |
| `ai_query` | Consulta ao assistente de IA |
| `ai_model_created` | Novo modelo de IA cadastrado |
| `data_ingestion` | Ingestão de dados executada |
| `user_login` | Login de usuário |
| `admin_action` | Ação administrativa diversa |

#### Colunas da trilha

| Coluna | Descrição |
|---|---|
| Data/Hora | Quando ocorreu |
| Ação | Tipo da ação |
| Usuário | Quem realizou |
| Entidade | Objeto afetado (pipeline, modelo, dataset) |
| Descrição | Detalhe textual da ação |
| IP | Endereço IP da requisição |

> A trilha de auditoria é somente leitura — nenhum registro pode ser alterado ou excluído pelo portal.

---

### 31. Governança IA

**Menu:** Governança → Governança IA

Painel de uso de IA separado por provedor: **local (Ollama)** vs. **externo (OpenAI)**.

#### O que é exibido

- Total de interações por provedor (últimos 30 dias)
- Taxa de uso local vs. externo (percentual)
- Custo acumulado de provedores externos
- Modelos mais utilizados
- Falhas por provedor

> O objetivo é maximizar o uso local (soberania de dados) e minimizar o uso externo (custo e privacidade).

---

## Resumo do Fluxo Completo (Fases 1–6)

```
[1]  Cadastrar Provedor CKAN
     ↓ sincronizar pacotes
[2]  Selecionar Pacotes para Monitoramento
     ↓ baixar arquivos
[3]  Executar Ingestão → storage/raw/
     ↓ preview
[4]  Preview do Dataset (schema detectado)
     ↓ mapear colunas
[5]  Configurar Mapeamento de Colunas
     ↓ transformar
[6]  Executar Transformação → STAGING
     ↓ verificar qualidade
[7]  Verificar Qualidade dos Dados (score 0–100%)
     ↓ catálogo
[8]  Visualizar Catálogo de Datasets
     ↓ modelo analítico
[9]  Criar Modelo Analítico
     ↓ executar ▶
[10] Executar Transformação → WAREHOUSE
     ↓ conectar BI
[11] Configurar Integração Metabase
     ↓ dashboards
[12] Registrar Dashboards
[13] Abrir Dashboard Incorporado (iframe)
[14] Visualizar Indicadores Executivos
[15] Usar APIs Analíticas (/api/analytics/*)
[16] Visualizar Linhagem de Dados

── FASE 5 — INTELIGÊNCIA ARTIFICIAL ──────────────────────

[17] Usar Assistente de IA (pergunta em português)
[18] Configurar Modelos (Ollama local / OpenAI externo)
[19] Configurar Contextos (quais dados a IA pode ver)
[20] Gerenciar Templates de Prompts ({{variáveis}})
[21] Gerenciar Agentes Especializados (turismo, executivo...)
[22] Consultar Logs de IA (custo, tokens, auditoria)

── FASE 6 — OPERAÇÕES E GOVERNANÇA ───────────────────────

[23] Visão Geral de Operações (KPIs + saúde dos serviços)
[24] Gerenciar Pipelines Kestra (CRUD + disparar + pausar)
[25] Acompanhar Execuções (detalhe + logs Kestra)
[26] Observabilidade dos Serviços (saúde em tempo real)
[27] Gerenciar Alertas (reconhecer + resolver)
[28] Governança de Dados LGPD (classificação + retenção)
[29] Rastrear Custos (OpenAI e serviços externos)
[30] Trilha de Auditoria (filtro por ação e usuário)
[31] Governança IA (local vs. externo)
```
