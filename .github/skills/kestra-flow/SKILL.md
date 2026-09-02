---
name: kestra-flow
description: "Use to create a new Kestra flow YAML and register it as a pipeline in the Plataforma360 portal. Triggers: create flow, new pipeline, kestra flow, criar flow, novo pipeline Kestra, novo fluxo de dados."
argument-hint: "Descreva o objetivo do flow: fonte dos dados, transformações, destino, agendamento"
---

# Criar Flow Kestra + Registrar Pipeline

## Quando Usar

- Automatizar ingestão de dados de uma nova fonte
- Criar pipeline de transformação STAGING → WAREHOUSE
- Agendar geração de embeddings para IA
- Qualquer automação de dados que o Kestra deve orquestrar

## Procedimento

### 1. Decidir o Tipo de Flow

| Tipo | Prefix sugerido | Namespace |
|---|---|---|
| Ingestão de dados | `ingestao-` | `plataforma360` |
| Transformação ETL | `transformacao-` | `plataforma360` |
| Carga Warehouse | `carga-warehouse-` | `plataforma360` |
| Geração Embeddings | `embeddings-` | `plataforma360` |
| Verificação Qualidade | `qualidade-` | `plataforma360` |

### 2. Criar o Arquivo YAML

Localização: `future/kestra/flows/<id-do-flow>.yml`

Ler `future/kestra/flows/olinda360_primeira_ingestao.yml` como referência antes de criar.

Estrutura obrigatória:
```yaml
id: ingestao-nome-da-fonte
namespace: plataforma360
description: "Descreva o que o flow faz"
labels:
  type: ingestion

inputs:
  - name: limite_registros
    type: INT
    defaults: 1000

tasks:
  - id: buscar_dados
    type: ...
    
  - id: processar
    type: ...
    
  - id: carregar_staging
    type: io.kestra.plugin.jdbc.postgresql.Query
    url: "jdbc:postgresql://postgres:5432/app"
    username: "{{ envs.DB_USER }}"
    password: "{{ envs.DB_PASS }}"
    sql: |
      INSERT INTO staging.tabela ...
      ON CONFLICT (id) DO UPDATE ...

errors:
  - id: log_erro
    type: io.kestra.plugin.core.log.Log
    message: "Falha no flow: {{ error.message }}"
```

### 3. Verificar a Conexão com o Banco

O Kestra usa a rede Docker `plataforma360`. O hostname correto do banco é `postgres` (não `localhost`).

```yaml
url: "jdbc:postgresql://postgres:5432/app"
```

### 4. Importar o Flow no Kestra (ambiente local)

Com o Kestra rodando (`make up-ops`):

```bash
# Via API REST
wsl -e bash -c "curl -X POST http://localhost:8082/api/v1/flows/import \
  -H 'Content-Type: application/x-yaml' \
  --data-binary @/mnt/c/Plataforma360/future/kestra/flows/nome-do-flow.yml"
```

Ou pela interface: `http://localhost:8082/ui/` → Flows → Import

### 5. Registrar no Portal como Pipeline

**Menu:** Operações → Pipelines → Novo Pipeline

| Campo | Valor |
|---|---|
| Nome | Nome legível (ex: "Ingestão CKAN - Turismo Nordeste") |
| Tipo | `ingestion` / `transformation` / etc. |
| Trigger | `manual` / `cron` / `event` |
| Cron Expression | Se agendado (ex: `0 2 * * *`) |
| Kestra Namespace | `plataforma360` |
| Kestra Flow ID | ID do flow (ex: `ingestao-ckan-turismo`) |
| Kestra YAML | Cole o conteúdo do arquivo YAML |

### 6. Testar o Disparo

1. No portal: **Operações → Pipelines** → botão ▶
2. Verificar em: **Operações → Execuções**
3. Verificar logs em: **Operações → Logs**

### 7. Verificar Dados no Staging

```bash
wsl -e bash -c "cd /mnt/c/Plataforma360 && docker compose exec postgres psql -U app -c 'SELECT COUNT(*) FROM staging.tabela'"
```
