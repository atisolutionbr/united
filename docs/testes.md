# Cenários de Teste — Plataforma360

Documento de referência para testes manuais e orientação do Copilot nos testes automatizados. Cobre os módulos das Fases 1 a 6.

**Legenda:**
- ✅ Happy path — fluxo esperado com dados válidos
- ❌ Negativo — dados inválidos ou estado incorreto
- ⚠️ Edge case — limite, caso extremo ou comportamento inesperado

---

## Índice

1. [Autenticação e Segurança](#1-autenticação-e-segurança)
2. [Dashboard e Home](#2-dashboard-e-home)
3. [Provedores de Dados CKAN](#3-provedores-de-dados-ckan)
4. [Pacotes e Ingestão CKAN](#4-pacotes-e-ingestão-ckan)
5. [Arquivos RAW](#5-arquivos-raw)
6. [Mapeamento de Colunas](#6-mapeamento-de-colunas)
7. [Staging e Transformação](#7-staging-e-transformação)
8. [Qualidade de Dados](#8-qualidade-de-dados)
9. [Catálogo de Datasets](#9-catálogo-de-datasets)
10. [Modelos Analíticos e Warehouse](#10-modelos-analíticos-e-warehouse)
11. [Integração Metabase](#11-integração-metabase)
12. [Dashboards e Indicadores](#12-dashboards-e-indicadores)
13. [APIs Analíticas Públicas](#13-apis-analíticas-públicas)
14. [Assistente de IA](#14-assistente-de-ia)
15. [Modelos de IA](#15-modelos-de-ia)
16. [Contextos de IA](#16-contextos-de-ia)
17. [Agentes de IA](#17-agentes-de-ia)
18. [Templates de Prompts](#18-templates-de-prompts)
19. [Logs de IA](#19-logs-de-ia)
20. [Visão Geral de Operações](#20-visão-geral-de-operações)
21. [Pipelines](#21-pipelines)
22. [Execuções de Pipeline](#22-execuções-de-pipeline)
23. [Observabilidade](#23-observabilidade)
24. [Alertas](#24-alertas)
25. [Governança de Dados LGPD](#25-governança-de-dados-lgpd)
26. [Trilha de Auditoria](#26-trilha-de-auditoria)
27. [Rastreamento de Custos](#27-rastreamento-de-custos)
28. [Governança de IA](#28-governança-de-ia)
29. [Gestão de Usuários](#29-gestão-de-usuários)
30. [Healthcheck e APIs de Status](#30-healthcheck-e-apis-de-status)

---

## 1. Autenticação e Segurança

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| AUTH-01 | ✅ | Login com credenciais válidas | Usuário admin cadastrado | 1. Acessar `/admin/login` 2. Informar usuario e senha corretos 3. Clicar em Entrar | Redireciona para `/admin` com navbar visível |
| AUTH-02 | ❌ | Login com senha incorreta | Usuário cadastrado | 1. Informar usuario correto e senha errada | Exibe mensagem de erro genérica em portugues e permanece em `/admin/login` |
| AUTH-03 | ❌ | Login com usuário inexistente | — | 1. Informar usuario não cadastrado | Exibe mensagem de erro genérica (não revela se o usuário existe) |
| AUTH-04 | ✅ | Logout | Usuário logado | 1. Clicar em "Logout" no menu de conta do navbar | Redireciona para `/admin/login`, sessão encerrada |
| AUTH-05 | ❌ | Acesso a rota admin sem autenticação | Usuário não logado | 1. Acessar `/admin` diretamente | Redireciona para `/admin/login` |
| AUTH-06 | ❌ | Acesso a rota admin com usuário sem ROLE_ADMIN | Usuário com ROLE_USER | 1. Logar com usuário sem permissão 2. Tentar acessar `/admin/operations/pipelines` | Retorna HTTP 403 Forbidden |
| AUTH-07 | ⚠️ | Sessão expirada durante uso | Usuário logado com sessão antiga | 1. Manter sessão inativa além do timeout 2. Tentar acessar página admin | Redireciona para login com flash "Sessão expirada" |
| AUTH-08 | ❌ | Campo de senha vazio | — | 1. Submeter formulário de login sem senha | Validação HTML5 impede submit |
| AUTH-09 | ✅ | Preenchimento rápido com usuário de exemplo | Tela de login aberta | 1. Clicar em um dos botões de acesso rápido abaixo de Entrar | Campo de usuário é preenchido automaticamente |

---

## 2. Dashboard e Home

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| DASH-01 | ✅ | Acessar dashboard admin | Usuário admin logado | 1. Acessar `/admin` | Exibe cards de KPI, links para módulos |
| DASH-02 | ✅ | Acessar homepage pública | — | 1. Acessar `/` | Exibe página institucional com cards e postagens públicas |
| DASH-03 | ✅ | Healthcheck da aplicação | Symfony e PostgreSQL ativos | 1. Acessar `/health` | Retorna JSON `{"status":"ok"}` com HTTP 200 |
| DASH-04 | ✅ | Navbar: menus aparecem corretamente | Admin logado | 1. Inspecionar navbar | Menus: Início, Inteligência, Dados, Integrações, IA, Operações, Governança, Plataforma |
| DASH-05 | ✅ | Menu ativo destacado | Admin em `/admin/operations/overview` | 1. Navegar para qualquer rota de operações | Menu "Operações" aparece com destaque ativo |
| DASH-06 | ✅ | Publicações aparecem depois dos cards da home | Existem posts publicados | 1. Acessar `/` 2. Rolar após os cards institucionais | A seção de publicações aparece abaixo dos cards |
| DASH-07 | ✅ | Abrir postagem pública pela home | Existe post publicado | 1. Clicar em "Ler postagem" | Abre `/blog/posts/{slug}` sem exigir login |

---

## 3. Provedores de Dados CKAN

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| CKAN-01 | ✅ | Cadastrar provedor CKAN válido | Admin logado | 1. Acessar Dados → Provedores 2. Novo Provedor 3. Preencher nome e URL válida 4. Salvar | Provedor criado, aparece na listagem |
| CKAN-02 | ❌ | Cadastrar provedor com URL inválida | Admin logado | 1. Preencher URL sem http:// 2. Salvar | Validação impede save, exibe erro |
| CKAN-03 | ❌ | Cadastrar provedor com nome vazio | Admin logado | 1. Deixar nome em branco 2. Salvar | Validação impede save |
| CKAN-04 | ✅ | Sincronizar pacotes de provedor ativo | Provedor cadastrado | 1. Clicar em Sincronizar | Status muda para "sincronizando", lista de pacotes aparece |
| CKAN-05 | ❌ | Sincronizar provedor com URL inacessível | Provedor com URL offline | 1. Clicar em Sincronizar | Flash de erro com mensagem de falha de conexão |
| CKAN-06 | ✅ | Editar provedor existente | Provedor cadastrado | 1. Clicar em Editar 2. Alterar nome 3. Salvar | Dados atualizados na listagem |
| CKAN-07 | ✅ | Desativar provedor | Provedor ativo | 1. Desmarcar campo "Ativo" 2. Salvar | Status muda para inativo, não aparece em sincronizações automáticas |
| CKAN-08 | ⚠️ | Sincronizar provedor com muitos pacotes (>500) | Provedor com grande catálogo | 1. Sincronizar | Todos os pacotes são salvos sem timeout |

---

## 4. Pacotes e Ingestão CKAN

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| PKG-01 | ✅ | Listar pacotes sincronizados | Provedor sincronizado | 1. Acessar Dados → Pacotes CKAN | Lista todos os pacotes com status |
| PKG-02 | ✅ | Ativar monitoramento de pacote | Pacote listado | 1. Clicar em Monitorar | Status muda para "monitorado" |
| PKG-03 | ✅ | Ver detalhe do pacote (resources) | Pacote monitorado | 1. Clicar em Detalhar | Lista recursos (CSV, XLSX) com URLs |
| PKG-04 | ✅ | Executar ingestão manual | Pacote monitorado com resources | 1. Acessar Dados → Ingestão 2. Clicar em Baixar Arquivos | Status muda para `running` → `success`, arquivos em `storage/raw/` |
| PKG-05 | ❌ | Ingestão com resource URL inválida | Resource com URL quebrada | 1. Executar ingestão | Status final `failed`, erro registrado no `ingestion_run` |
| PKG-06 | ⚠️ | Ingestão de arquivo muito grande (>100MB) | Resource > 100MB | 1. Executar ingestão | Download concluído sem timeout, arquivo salvo corretamente |
| PKG-07 | ⚠️ | Re-ingesta de arquivo já baixado | Resource já ingerido | 1. Executar ingestão novamente | Arquivo é sobrescrito ou novo `ingestion_run` criado |

---

## 5. Arquivos RAW

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| RAW-01 | ✅ | Listar arquivos RAW | Ingestão executada | 1. Acessar Dados → Arquivos RAW | Lista arquivos com nome, tamanho e data |
| RAW-02 | ✅ | Preview de arquivo CSV | Arquivo CSV ingerido | 1. Clicar em Preview | Exibe primeiras linhas, colunas detectadas e tipos inferidos |
| RAW-03 | ✅ | Preview de arquivo XLSX | Arquivo XLSX ingerido | 1. Clicar em Preview | Exibe dados da primeira aba |
| RAW-04 | ❌ | Preview de arquivo corrompido | Arquivo com encoding inválido | 1. Clicar em Preview | Exibe mensagem de erro amigável, não quebra a tela |
| RAW-05 | ⚠️ | Preview de arquivo com 100k+ linhas | Arquivo grande | 1. Clicar em Preview | Exibe apenas as primeiras N linhas com aviso de truncamento |

---

## 6. Mapeamento de Colunas

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| MAP-01 | ✅ | Criar mapeamento de colunas | Arquivo RAW com schema detectado | 1. Acessar Dados → Mapeamento 2. Selecionar arquivo 3. Mapear colunas 4. Salvar | Mapeamento salvo, colunas com tipos definidos |
| MAP-02 | ✅ | Aplicar regra de normalização `trim` | Coluna com espaços | 1. Selecionar regra `trim` na coluna | Preview mostra valor sem espaços |
| MAP-03 | ✅ | Marcar coluna como obrigatória | Qualquer coluna | 1. Marcar campo "Obrigatório" | Validação de staging rejeita linhas com campo nulo |
| MAP-04 | ❌ | Salvar mapeamento sem definir nenhuma coluna | — | 1. Tentar salvar sem mapear colunas | Mensagem de erro: mínimo 1 coluna mapeada |
| MAP-05 | ⚠️ | Coluna do arquivo renomeada após mapeamento | Arquivo re-ingerido com cabeçalho diferente | 1. Re-executar transformação | Erro de mapeamento com indicação da coluna faltante |

---

## 7. Staging e Transformação

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| STG-01 | ✅ | Executar transformação RAW → STAGING | Mapeamento configurado | 1. Clicar em Transformar no arquivo RAW | Dados aparecem em `staging.*`, status `success` |
| STG-02 | ✅ | Preview do staging | Transformação executada | 1. Acessar Dados → Preview Staging | Exibe dados normalizados da tabela staging |
| STG-03 | ❌ | Transformação com campo obrigatório nulo | Linhas com campo obrigatório vazio | 1. Executar transformação | Linhas inválidas rejeitadas, relatório de erros gerado |
| STG-04 | ⚠️ | Transformação com caracteres especiais (UTF-8) | CSV com acentos e ç | 1. Executar transformação | Dados preservados sem corrupção de encoding |
| STG-05 | ⚠️ | Re-transformação de arquivo já em staging | Staging já populado | 1. Re-executar transformação | Dados atualizados (upsert ou truncate+insert) sem duplicatas |

---

## 8. Qualidade de Dados

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| QLD-01 | ✅ | Visualizar score de qualidade | Dataset em staging | 1. Acessar Dados → Qualidade | Score 0–100% por dataset, contagem de linhas válidas/inválidas |
| QLD-02 | ✅ | Dataset com qualidade alta (>90%) | Dados limpos | 1. Ver relatório | Badge verde, sem alertas |
| QLD-03 | ✅ | Dataset com qualidade baixa (<70%) | Dados com muitos nulos | 1. Ver relatório | Badge vermelho, lista de erros por campo |
| QLD-04 | ⚠️ | Dataset com linhas duplicadas | Arquivo com duplicatas | 1. Ver relatório | Duplicatas identificadas, contagem exibida |

---

## 9. Catálogo de Datasets

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| CAT-01 | ✅ | Listar datasets no catálogo | Datasets em staging | 1. Acessar Dados → Catálogo | Lista datasets com nome, tipo, provedor e data |
| CAT-02 | ✅ | Buscar dataset por nome | Catálogo populado | 1. Digitar nome no campo de busca | Lista filtrada com resultados correspondentes |
| CAT-03 | ✅ | Ver schema de um dataset | Dataset no catálogo | 1. Clicar em dataset | Exibe colunas, tipos e exemplos de valores |
| CAT-04 | ❌ | Busca sem resultados | Catálogo populado | 1. Buscar por termo inexistente | Exibe estado vazio com mensagem "Nenhum dataset encontrado" |

---

## 10. Modelos Analíticos e Warehouse

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| WH-01 | ✅ | Criar modelo analítico | Dataset em staging | 1. Novo Modelo 2. Definir tabela origem, destino, dimensões e métricas 3. Salvar | Modelo criado com status "aguardando execução" |
| WH-02 | ✅ | Executar transformação warehouse | Modelo analítico criado | 1. Clicar em ▶ (executar) | Tabela criada em `warehouse.*`, status `Pronto` |
| WH-03 | ✅ | Visualizar tabelas do warehouse | Execução concluída | 1. Acessar Dados → Data Warehouse | Lista tabelas, contagem de linhas e última atualização |
| WH-04 | ❌ | Executar modelo com tabela origem vazia | Staging sem dados | 1. Executar transformação | Status `failed` com mensagem "Tabela origem sem dados" |
| WH-05 | ❌ | Criar modelo com tabela destino já existente (nome conflito) | Modelo já executado | 1. Criar modelo com mesmo nome destino | Aviso de conflito ou sobrescrita confirmada |
| WH-06 | ⚠️ | Execução com dataset de 1M+ linhas | Dataset grande | 1. Executar transformação | Concluído sem timeout, contagem correta |

---

## 11. Integração Metabase

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| MB-01 | ✅ | Configurar integração Metabase | Metabase rodando | 1. Acessar Integrações → Metabase 2. Informar URL, usuário e senha 3. Salvar | Configuração salva |
| MB-02 | ✅ | Testar conexão com Metabase | Configuração salva | 1. Clicar em "Testar Conexão" | Flash verde "Conexão estabelecida com sucesso" |
| MB-03 | ❌ | Testar conexão com Metabase offline | Metabase desligado | 1. Clicar em "Testar Conexão" | Flash vermelho "Não foi possível conectar ao Metabase" |
| MB-04 | ❌ | Salvar configuração com URL inválida | — | 1. Informar URL sem protocolo | Validação impede save |

---

## 12. Dashboards e Indicadores

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| DSH-01 | ✅ | Registrar dashboard Metabase | Metabase configurado, dashboard criado no Metabase | 1. Novo Dashboard 2. Preencher nome e URL de embed 3. Salvar | Dashboard registrado na listagem |
| DSH-02 | ✅ | Abrir dashboard incorporado | Dashboard registrado | 1. Clicar em 👁 (Abrir) | iframe do Metabase carregado dentro da plataforma |
| DSH-03 | ❌ | Registrar dashboard com URL de embed inválida | — | 1. Informar URL quebrada 2. Salvar 3. Abrir | iframe exibe erro ou mensagem de carregamento falhou |
| DSH-04 | ✅ | Visualizar indicadores executivos | Warehouse populado | 1. Acessar Inteligência → Indicadores | KPIs calculados: total de agências, estados, municípios |
| DSH-05 | ⚠️ | Indicadores com warehouse vazio | Nenhuma execução de modelo | 1. Acessar Indicadores | Exibe zeros ou mensagem "Sem dados disponíveis" sem quebrar |

---

## 13. APIs Analíticas Públicas

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| API-01 | ✅ | GET /api/analytics/indicadores | Warehouse com dados | 1. Acessar endpoint | JSON com KPIs: total, estados, municípios, tendência |
| API-02 | ✅ | GET /api/analytics/turismo/agencias | Warehouse com dados | 1. Acessar endpoint | JSON com lista de agências e ranking |
| API-03 | ✅ | GET /api/analytics/lineage | Qualquer dado ingerido | 1. Acessar endpoint | JSON com contagens por camada: CKAN, RAW, STAGING, WAREHOUSE |
| API-04 | ✅ | GET /health | Symfony e PostgreSQL ativos | 1. GET /health | `{"status":"ok"}` HTTP 200 |
| API-05 | ⚠️ | API com warehouse vazio | Nenhum dado no warehouse | 1. Acessar /api/analytics/indicadores | JSON com zeros, HTTP 200 (não 500) |
| API-06 | ✅ | GET /api/docs (OpenAPI) | — | 1. Acessar /api/docs | Documentação Swagger carregada |

---

## 14. Assistente de IA

> **Pré-condição geral:** perfil `ai` ativo (`make up-ai`), modelo Ollama cadastrado e baixado.

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| AI-01 | ✅ | Pergunta simples ao assistente local | Ollama online, modelo padrão cadastrado | 1. Acessar IA → Assistente 2. Digitar pergunta genérica 3. Enviar | Resposta gerada em português, badge "Local" |
| AI-02 | ✅ | Pergunta sobre dados do warehouse | Warehouse com dados, contexto configurado | 1. Selecionar contexto com `warehouse` 2. Perguntar "Quantas agências existem por estado?" | Resposta com dados reais do warehouse |
| AI-03 | ✅ | Quick prompt de análise de indicadores | Contexto de indicadores disponível | 1. Clicar no quick prompt "Análise de indicadores" | Assistente gera análise automática dos KPIs |
| AI-04 | ❌ | Pergunta sem contexto configurado | Nenhum contexto selecionado | 1. Enviar pergunta sem selecionar contexto | Assistente responde sem dados, avisa ausência de contexto |
| AI-05 | ❌ | Pergunta com Ollama offline | Perfil `ai` não iniciado | 1. Tentar enviar mensagem | Flash de erro "Assistente de IA indisponível", não quebra a tela |
| AI-06 | ✅ | Selecionar agente especializado | Agente de turismo cadastrado | 1. Selecionar agente "Analista de Turismo" 2. Fazer pergunta setorial | Resposta focada no domínio de turismo |
| AI-07 | ⚠️ | Pergunta em linguagem natural convertida para SQL | Contexto `warehouse`, modelo NL-to-SQL | 1. Perguntar "Liste os 5 estados com mais agências" | SQL gerado é válido SELECT, resultado correto |
| AI-08 | ❌ | Tentativa de injeção SQL via assistente | — | 1. Digitar "DROP TABLE warehouse.dw_agencias_turismo" | Bloqueado pelo NL-to-SQL, resposta de segurança |
| AI-09 | ❌ | Contexto com `allowedForExternal=false` usando OpenAI | Contexto interno configurado | 1. Selecionar modelo OpenAI com contexto interno 2. Enviar | Erro: "Contexto não permitido para provedores externos" |
| AI-10 | ⚠️ | Resposta muito longa (>4000 tokens) | Modelo com max_tokens alto | 1. Pedir relatório completo | Resposta truncada ou completa, sem erro de memória |

---

## 15. Modelos de IA

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| MOD-01 | ✅ | Cadastrar modelo Ollama local | Ollama online | 1. Novo Modelo 2. Provedor: `local_ollama`, modelo: `llama3` 3. Salvar | Modelo na listagem, badge "Local" |
| MOD-02 | ✅ | Cadastrar modelo OpenAI | API Key válida | 1. Provedor: `openai`, modelo: `gpt-4o-mini`, API Key preenchida 3. Salvar | Modelo salvo, API Key não aparece em texto claro |
| MOD-03 | ❌ | Cadastrar modelo sem nome do modelo | — | 1. Deixar "Nome do Modelo" vazio 2. Salvar | Validação impede save |
| MOD-04 | ❌ | Cadastrar dois modelos com mesmo slug | — | 1. Criar modelo com slug já existente | Erro de unicidade, mensagem amigável |
| MOD-05 | ✅ | Definir modelo como padrão | Modelo cadastrado | 1. Editar 2. Marcar "Modelo Padrão" 3. Salvar | Badge "Padrão" na listagem, selecionado automaticamente no assistente |
| MOD-06 | ✅ | Desativar modelo | Modelo ativo | 1. Editar 2. Desmarcar "Ativo" | Modelo não aparece no seletor do assistente |
| MOD-07 | ⚠️ | Editar modelo OpenAI sem alterar API Key | Modelo com API Key salva | 1. Editar apenas o nome 2. Salvar | API Key original preservada (campo vazio = não alterar) |
| MOD-08 | ❌ | Temperature fora do range 0–1 | — | 1. Informar temperature 1.5 | Validação impede save |

---

## 16. Contextos de IA

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| CTX-01 | ✅ | Criar contexto com fonte warehouse | — | 1. Novo Contexto 2. Selecionar fonte `warehouse` 3. Informar tabela `warehouse.dw_agencias_turismo` 4. Salvar | Contexto disponível no assistente |
| CTX-02 | ✅ | Criar contexto bloqueado para externo | — | 1. Desmarcado "Permitido para externo" 2. Salvar | Badge "Interno" na listagem |
| CTX-03 | ❌ | Criar contexto sem nenhuma fonte | — | 1. Não selecionar fontes 2. Salvar | Aviso ou contexto válido mas sem dados |
| CTX-04 | ⚠️ | Contexto com max_rows_context = 1 | — | 1. Salvar com limite 1 2. Usar no assistente | Apenas 1 linha carregada, assistente avisa sobre limitação |

---

## 17. Agentes de IA

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| AGT-01 | ✅ | Criar agente especializado | Modelo e contexto cadastrados | 1. Novo Agente 2. Tipo: `turismo` 3. Associar modelo e contexto 4. Salvar | Agente na listagem, disponível no assistente |
| AGT-02 | ❌ | Criar agente sem nome | — | 1. Deixar nome vazio 2. Salvar | Validação impede save |
| AGT-03 | ✅ | Usar agente com ferramentas | Agente com `buscar_indicadores` | 1. Selecionar agente 2. Perguntar sobre indicadores | Ferramenta é invocada, resultado vem da API de indicadores |
| AGT-04 | ✅ | Desativar agente | Agente ativo | 1. Editar 2. Desmarcar ativo | Não aparece no seletor do assistente |

---

## 18. Templates de Prompts

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| PRM-01 | ✅ | Criar template com variáveis | — | 1. Novo Template 2. Escrever prompt com `{{estado}}` 3. Salvar | Template salvo, variáveis detectadas |
| PRM-02 | ✅ | Usar template no assistente | Template cadastrado | 1. Selecionar template no assistente 2. Preencher variável `{{estado}}` = "PE" 3. Enviar | Variável substituída antes de enviar ao modelo |
| PRM-03 | ❌ | Criar template com corpo vazio | — | 1. Deixar promptTemplate vazio 2. Salvar | Validação impede save |
| PRM-04 | ⚠️ | Template com variável não preenchida | Template com `{{municipio}}` | 1. Usar template sem preencher variável | Placeholder enviado como está ou aviso ao usuário |

---

## 19. Logs de IA

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| LOG-01 | ✅ | Visualizar histórico de interações | Interações realizadas | 1. Acessar IA → Logs | Lista com usuário, modelo, tokens, custo, duração e status |
| LOG-02 | ✅ | Identificar interações com provedor externo | Interação via OpenAI realizada | 1. Acessar logs | Linha com badge "Externo" (vermelho) |
| LOG-03 | ✅ | Interação com custo zero (Ollama) | Interação via Ollama | 1. Acessar logs | Coluna custo exibe `$0.000000` |
| LOG-04 | ✅ | Interação com custo > 0 (OpenAI) | Interação via OpenAI | 1. Acessar logs | Coluna custo exibe valor em USD |
| LOG-05 | ⚠️ | Log de interação que falhou | Ollama offline durante uso | 1. Acessar logs | Status `failed` com mensagem de erro registrada |

---

## 20. Visão Geral de Operações

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| OPS-01 | ✅ | Acessar visão geral | Fase 6 ativa | 1. Acessar Operações → Visão Geral | Cards de KPI: pipelines ativos, falhas hoje, alertas críticos, saúde |
| OPS-02 | ✅ | KPI de falhas reflete execuções reais | Execução com `status=failed` | 1. Acessar Visão Geral | Contador de falhas > 0 |
| OPS-03 | ✅ | Saúde geral `healthy` | Todos os serviços respondendo | 1. Acessar Visão Geral | Badge verde "healthy" |
| OPS-04 | ⚠️ | Saúde geral `degraded` | Kestra offline, restante ok | 1. Parar Kestra 2. Acessar Visão Geral | Badge amarelo "degraded", card do Kestra vermelho |

---

## 21. Pipelines

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| PIP-01 | ✅ | Cadastrar pipeline manual | — | 1. Novo Pipeline 2. Nome, tipo `ingestion`, trigger `manual`, namespace e flow ID 3. Salvar | Pipeline na listagem com status ativo |
| PIP-02 | ✅ | Cadastrar pipeline com cron | — | 1. Trigger `cron`, cron expression `0 2 * * *` 2. Salvar | Próxima execução calculada e exibida |
| PIP-03 | ❌ | Cadastrar pipeline sem Kestra Namespace | — | 1. Deixar namespace vazio 2. Salvar | Validação impede save |
| PIP-04 | ❌ | Cadastrar pipeline sem Kestra Flow ID | — | 1. Deixar flow ID vazio 2. Salvar | Validação impede save |
| PIP-05 | ✅ | Disparar pipeline manualmente | Kestra online, flow existente | 1. Clicar ▶ | Flash de sucesso, execução aparece em Execuções |
| PIP-06 | ❌ | Disparar pipeline com Kestra offline | Kestra desligado | 1. Clicar ▶ | Flash de erro "Kestra indisponível", nenhuma execução criada |
| PIP-07 | ❌ | Disparar pipeline com flow inexistente no Kestra | Flow ID errado | 1. Clicar ▶ | Flash de erro com código HTTP do Kestra |
| PIP-08 | ✅ | Pausar pipeline | Pipeline ativo, Kestra online | 1. Clicar ⏸ | Status muda para `paused` |
| PIP-09 | ✅ | Visualizar YAML do pipeline | Pipeline com YAML salvo | 1. Clicar em 📄 YAML | Modal com YAML formatado |
| PIP-10 | ✅ | Editar pipeline | Pipeline cadastrado | 1. Editar nome 2. Salvar | Dados atualizados |
| PIP-11 | ✅ | Excluir pipeline | Pipeline sem execuções recentes | 1. Clicar em excluir 2. Confirmar | Pipeline removido da listagem |
| PIP-12 | ⚠️ | Salvar YAML com script malicioso | — | 1. Colar YAML com `<script>alert(1)</script>` 2. Salvar | Tags HTML removidas pelo `strip_tags()`, XSS bloqueado |

---

## 22. Execuções de Pipeline

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| EXE-01 | ✅ | Listar execuções | Pipelines disparados | 1. Acessar Operações → Execuções | Lista com pipeline, status, trigger, duração |
| EXE-02 | ✅ | Ver detalhe de execução com sucesso | Execução `SUCCESS` | 1. Clicar em 👁 | Detalhes: inputs, outputs, duração, logs do Kestra |
| EXE-03 | ✅ | Ver detalhe de execução com falha | Execução `FAILED` | 1. Clicar em 👁 | Mensagem de erro, stack trace do Kestra |
| EXE-04 | ✅ | Status sincronizado com Kestra | Execução `RUNNING` | 1. Abrir detalhe | Status atualizado automaticamente ao abrir |
| EXE-05 | ⚠️ | Detalhe com Kestra offline | Kestra parado após execução registrada | 1. Abrir detalhe | Dados locais exibidos, warning "Kestra indisponível para sync" |
| EXE-06 | ✅ | Filtrar execuções por status | Múltiplas execuções | 1. Filtrar por `FAILED` | Apenas execuções falhas exibidas |

---

## 23. Observabilidade

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| OBS-01 | ✅ | Verificar saúde de todos os serviços | Core online | 1. Acessar Operações → Observabilidade | Grid com status de Symfony, PostgreSQL, Kestra, Ollama, Qdrant, Metabase, Storage |
| OBS-02 | ✅ | Symfony healthy | — | 1. Ver card Symfony | Verde, memória PHP em MB |
| OBS-03 | ✅ | PostgreSQL healthy | Banco online | 1. Ver card PostgreSQL | Verde, versão e contagem de tabelas |
| OBS-04 | ⚠️ | Kestra down (perfil ops não iniciado) | Kestra parado | 1. Ver card Kestra | Vermelho "down" |
| OBS-05 | ⚠️ | Ollama down (perfil ai não iniciado) | Ollama parado | 1. Ver card Ollama | Vermelho "down" |
| OBS-06 | ⚠️ | Storage sem permissão de escrita | — | 1. Ver card Storage | Amarelo ou vermelho indicando problema |

---

## 24. Alertas

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| ALT-01 | ✅ | Listar alertas ativos | Alertas criados (seed ou por falha) | 1. Acessar Operações → Alertas | Lista com nível, tipo, título, status |
| ALT-02 | ✅ | Reconhecer alerta | Alerta com status `active` | 1. Clicar em ✓ Reconhecer | Status muda para `acknowledged`, usuário e data registrados |
| ALT-03 | ✅ | Resolver alerta reconhecido | Alerta `acknowledged` | 1. Clicar em ✓✓ Resolver | Status muda para `resolved` |
| ALT-04 | ❌ | Resolver alerta ainda `active` (sem reconhecer) | Alerta `active` | 1. Tentar resolver direto | Fluxo permite ou exige reconhecimento primeiro (conforme regra) |
| ALT-05 | ✅ | Alerta `critical` aparece no dashboard | Alerta crítico ativo | 1. Acessar Visão Geral | Card de alertas críticos > 0, destaque vermelho |
| ALT-06 | ⚠️ | Múltiplos alertas críticos simultâneos | 10+ alertas críticos | 1. Acessar listagem | Todos exibidos, paginação funciona |

---

## 25. Governança de Dados LGPD

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| GOV-01 | ✅ | Criar registro de governança | — | 1. Governança → Dados → Novo 2. Preencher dataset, classificação `publico`, sensibilidade `none` 3. Salvar | Registro na listagem |
| GOV-02 | ✅ | Criar registro com LGPD ativo | — | 1. Marcar "Aplica-se LGPD" 2. Selecionar base legal 3. Salvar | Badge LGPD na listagem |
| GOV-03 | ❌ | Criar registro com LGPD sem base legal | — | 1. Marcar LGPD 2. Não selecionar base legal 3. Salvar | Validação: base legal obrigatória quando LGPD marcado |
| GOV-04 | ✅ | Criar registro com classificação `sensivel` | — | 1. Classificação `sensivel`, sensibilidade `high` 2. Salvar | Badge vermelho "Sensível" |
| GOV-05 | ✅ | Editar registro existente | Registro cadastrado | 1. Editar retenção de 365 para 730 dias 2. Salvar | Dado atualizado |
| GOV-06 | ⚠️ | Registro com retenção expirada | Registro com data passada | 1. Listar registros | Dataset indicado como "retencão expirada" ou badge de aviso |

---

## 26. Trilha de Auditoria

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| AUD-01 | ✅ | Ação administrativa gera log | Qualquer ação admin (ex: criar pipeline) | 1. Criar pipeline 2. Acessar Governança → Auditoria | Registro com ação `pipeline_run` ou equivalente |
| AUD-02 | ✅ | Filtrar auditoria por ação | Logs de diferentes ações | 1. Filtrar por `pipeline_run` | Apenas registros de execução de pipeline |
| AUD-03 | ✅ | Filtrar auditoria por usuário | Logs de diferentes usuários | 1. Filtrar por e-mail do usuário | Apenas ações do usuário filtrado |
| AUD-04 | ✅ | Log registra IP do usuário | — | 1. Realizar ação admin 2. Ver log | IP da requisição registrado |
| AUD-05 | ❌ | Tentar excluir registro de auditoria | — | 1. Buscar botão de delete na auditoria | Botão não existe — auditoria é somente leitura |
| AUD-06 | ⚠️ | Auditoria com 10k+ registros | Log volumoso | 1. Acessar listagem | Paginação funciona, página carrega em tempo aceitável |

---

## 27. Rastreamento de Custos

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| CST-01 | ✅ | Visualizar custos do mês | Interações OpenAI realizadas | 1. Governança → Custos | Total em USD, breakdown por serviço |
| CST-02 | ✅ | Custo zero para Ollama | Apenas Ollama usado | 1. Ver total do mês | $0.00 ou serviço `local_ollama` não aparece |
| CST-03 | ✅ | Série diária de custos | Histórico de 30 dias | 1. Ver gráfico/tabela diária | Valores por dia, soma correta |
| CST-04 | ⚠️ | Mês sem nenhum custo | Sem interações externas | 1. Ver painel | Exibe $0.00 sem quebrar |

---

## 28. Governança de IA

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| GAI-01 | ✅ | Visualizar uso local vs externo | Interações de ambos os provedores | 1. Governança → Governança IA | Percentual local vs. externo, total de interações |
| GAI-02 | ✅ | Modelos mais utilizados | Múltiplas interações | 1. Ver painel | Ranking de modelos por número de requisições |
| GAI-03 | ⚠️ | 100% de uso local | Nenhuma interação externa | 1. Ver painel | 100% local, $0.00 de custo externo |

---

## 29. Gestão de Usuários

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| USR-01 | ✅ | Criar usuário admin | Admin logado | 1. Acessar admin de usuários 2. Novo usuário 3. Preencher nome, e-mail, senha e ROLE_ADMIN | Usuário criado, aparece na listagem |
| USR-02 | ❌ | Criar usuário com e-mail já existente | Usuário cadastrado | 1. Criar usuário com mesmo e-mail | Erro de unicidade |
| USR-03 | ❌ | Criar usuário com senha fraca | — | 1. Informar senha curta (< 8 chars) | Validação de força de senha |
| USR-04 | ✅ | Alterar senha de usuário | Admin logado | 1. Editar usuário 2. Nova senha 3. Salvar | Usuário consegue logar com nova senha |
| USR-05 | ✅ | Desativar usuário | Usuário ativo | 1. Desmarcar "Ativo" 2. Salvar | Usuário não consegue logar |

---

## 30. Healthcheck e APIs de Status

| ID | Tipo | Cenário | Pré-condição | Passos | Resultado Esperado |
|---|---|---|---|---|---|
| HLT-01 | ✅ | GET /health retorna ok | Symfony e PostgreSQL ativos | 1. GET /health | `{"status":"ok"}` HTTP 200 |
| HLT-02 | ✅ | GET /api retorna documentação OpenAPI | — | 1. GET /api | JSON OpenAPI com todos os endpoints |
| HLT-03 | ⚠️ | GET /health com banco indisponível | PostgreSQL parado | 1. GET /health | HTTP 503 com status `degraded` ou `down` |

---

## Cenários de Segurança Transversais

| ID | Tipo | Cenário | Passos | Resultado Esperado |
|---|---|---|---|---|
| SEC-01 | ❌ | XSS em campo de texto | 1. Inserir `<script>alert(1)</script>` em qualquer campo de formulário | HTML escapado no output, nenhum alert executado |
| SEC-02 | ❌ | CSRF — submeter form sem token | 1. Remover `_token` do form 2. Submeter | HTTP 419 ou redirecionamento com erro |
| SEC-03 | ❌ | Path traversal em upload/preview | 1. Informar `../../etc/passwd` em campo de arquivo | Erro ou sanitização, nunca leitura do arquivo do sistema |
| SEC-04 | ❌ | SQL Injection em campos de filtro | 1. Inserir `' OR 1=1 --` em campo de busca | Sem resultado ou erro controlado, nunca dump do banco |
| SEC-05 | ❌ | Acesso direto a arquivos de storage | 1. Tentar acessar `http://localhost/storage/raw/arquivo.csv` | 404 ou 403 — arquivos não servidos diretamente |
| SEC-06 | ❌ | API Key visível em logs ou respostas | 1. Criar modelo com API Key 2. Inspecionar HTML/JSON das respostas | Campo vazio ou mascarado (`***`) — nunca em claro |
