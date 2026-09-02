---
description: "Use when creating Kestra flow YAML files in future/kestra/flows/. Applies Kestra flow conventions for Plataforma360: namespace plataforma360, PostgreSQL connection via kestra-postgres, Python tasks for data processing, schedule triggers and error handling."
applyTo: "future/kestra/flows/**/*.yml"
---

# Kestra Flows — Convenções da Plataforma360

## Estrutura Base

```yaml
id: nome-do-flow          # kebab-case, único no namespace
namespace: plataforma360
description: "Descrição clara do objetivo"
labels:
  type: ingestion         # ingestion | transformation | warehouse_load | quality_check

inputs:
  - name: parametro
    type: STRING
    defaults: "valor_padrao"
```

## Conexão PostgreSQL

```yaml
# Banco principal da plataforma
url: "jdbc:postgresql://postgres:5432/app"
username: "{{ envs.DB_USER }}"
password: "{{ envs.DB_PASS }}"
```

## Tarefas Comuns

### Script Python

```yaml
- id: processar_dados
  type: io.kestra.plugin.scripts.python.Script
  containerImage: python:3.11-slim
  beforeCommands:
    - pip install requests pandas psycopg2-binary -q
  script: |
    import pandas as pd
    # código aqui
  outputFiles:
    - "*.csv"
```

### Query PostgreSQL

```yaml
- id: carregar_staging
  type: io.kestra.plugin.jdbc.postgresql.Query
  url: "jdbc:postgresql://postgres:5432/app"
  username: "{{ envs.DB_USER }}"
  password: "{{ envs.DB_PASS }}"
  sql: |
    INSERT INTO staging.tabela (col1, col2)
    VALUES (:val1, :val2)
    ON CONFLICT (id) DO UPDATE SET col2 = EXCLUDED.col2
```

### HTTP Request (CKAN API)

```yaml
- id: buscar_ckan
  type: io.kestra.plugin.core.http.Request
  uri: "{{ inputs.ckan_url }}/api/3/action/package_list"
  method: GET
```

## Tratamento de Erros

```yaml
errors:
  - id: notificar_falha
    type: io.kestra.plugin.core.log.Log
    message: "Flow falhou: {{ error.message }}"
```

## Triggers

```yaml
triggers:
  # Cron diário às 2h
  - id: diario
    type: io.kestra.core.models.triggers.types.Schedule
    cron: "0 2 * * *"
    
  # Manual apenas
  # (sem triggers = somente manual)
```

## Regras Obrigatórias

- Sempre incluir `description` no flow e nos inputs
- Usar `ON CONFLICT DO UPDATE` em inserts para idempotência
- Nunca hardcodar senhas — sempre via `{{ envs.VARIAVEL }}`
- Flows de ingestão devem gravar em `staging.*`, nunca direto em `warehouse.*`
- Nomear tarefas com verbos: `buscar_`, `transformar_`, `carregar_`, `validar_`
