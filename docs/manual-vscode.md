# Ambiente de Desenvolvimento — VS Code

Guia de configuração do ambiente de desenvolvimento local para a Plataforma360 usando o VS Code.

**Documentação relacionada:**
- [Instalação e Docker](instalacao.md)
- [Arquitetura da plataforma](arquitetura.md)
- [Manual técnico do Kestra](manual-kestra.md)
- [Manual técnico da IA (Ollama / Qdrant)](manual-ia.md)
- [Agentes e skills do Copilot](manual-agentes.md)
- [Cenários de teste](testes.md)

---

## Índice

1. [Pré-requisitos](#1-pré-requisitos)
2. [Extensões recomendadas](#2-extensões-recomendadas)
3. [Configurações do workspace](#3-configurações-do-workspace)
4. [Configuração de debug (Xdebug)](#4-configuração-de-debug-xdebug)
5. [Tasks integradas](#5-tasks-integradas)
6. [Integração com WSL (Windows)](#6-integração-com-wsl-windows)
7. [GitHub Copilot — Agentes e Skills](#7-github-copilot--agentes-e-skills)
8. [Fluxo de trabalho recomendado](#8-fluxo-de-trabalho-recomendado)
9. [Solução de problemas](#9-solução-de-problemas)

---

## 1. Pré-requisitos

Antes de abrir o projeto no VS Code, confirme que o ambiente base está funcionando:

| Ferramenta | Verificação | Versão mínima |
|---|---|---|
| VS Code | `code --version` | 1.90+ |
| Docker Desktop | `docker --version` | 24+ |
| WSL Ubuntu (Windows) | `wsl --version` | 2.x |
| Git | `git --version` | 2.40+ |
| Make (via WSL) | `wsl -e bash -c "make --version"` | 4.x |

> **Windows:** Abra o VS Code com `code .` a partir do terminal PowerShell dentro da pasta `C:\Plataforma360`. Todos os comandos Docker e Make devem rodar via WSL (veja [seção 6](#6-integração-com-wsl-windows)).

---

## 2. Extensões recomendadas

### Essenciais

| Extensão | ID | Para que serve |
|---|---|---|
| **PHP Intelephense** | `bmewburn.vscode-intelephense-client` | Autocomplete, definições e referências PHP |
| **PHP Debug** | `xdebug.php-debug` | Debug com Xdebug via Docker |
| **Twig Language 2** | `mblode.twig-language-2` | Syntax highlight e snippets para templates Twig |
| **YAML** | `redhat.vscode-yaml` | Lint e autocomplete para YAML (flows Kestra, docker-compose) |
| **Docker** | `ms-azuretools.vscode-docker` | Gerenciar containers, visualizar logs, explorar imagens |
| **Remote - WSL** | `ms-vscode-remote.remote-wsl` | Abrir o projeto diretamente no filesystem do WSL (Windows) |
| **GitLens** | `eamodio.gitlens` | Histórico de commits, blame inline, comparações de branch |
| **GitHub Copilot** | `github.copilot` | Sugestões de código com IA |
| **GitHub Copilot Chat** | `github.copilot-chat` | Agentes, skills e chat com contexto do projeto |

### Recomendadas

| Extensão | ID | Para que serve |
|---|---|---|
| **SQLTools** | `mtxr.sqltools` | Query no PostgreSQL diretamente do VS Code |
| **SQLTools PostgreSQL** | `mtxr.sqltools-driver-pg` | Driver PostgreSQL para o SQLTools |
| **DotENV** | `mikestead.dotenv` | Syntax highlight para arquivos `.env` |
| **Better Comments** | `aaron-bond.better-comments` | Colorir comentários TODO, FIXME, NOTE |
| **Error Lens** | `usernamehw.errorlens` | Erros e warnings inline no editor |
| **Prettier** | `esbenp.prettier-vscode` | Formatação de JS, JSON, YAML |
| **EditorConfig** | `editorconfig.editorconfig` | Normalizar indentação e charset entre editores |

### Instalação em lote

Cole no terminal para instalar todas de uma vez:

```bash
code --install-extension bmewburn.vscode-intelephense-client
code --install-extension xdebug.php-debug
code --install-extension mblode.twig-language-2
code --install-extension redhat.vscode-yaml
code --install-extension ms-azuretools.vscode-docker
code --install-extension ms-vscode-remote.remote-wsl
code --install-extension eamodio.gitlens
code --install-extension github.copilot
code --install-extension github.copilot-chat
code --install-extension mtxr.sqltools
code --install-extension mtxr.sqltools-driver-pg
code --install-extension mikestead.dotenv
code --install-extension usernamehw.errorlens
```

---

## 3. Configurações do workspace

Crie o arquivo `.vscode/settings.json` na raiz do projeto com as configurações abaixo:

```json
{
  "php.validate.executablePath": "/usr/bin/php",
  "intelephense.environment.phpVersion": "8.3",
  "intelephense.files.exclude": [
    "**/vendor/**",
    "**/var/cache/**",
    "**/var/log/**",
    "**/node_modules/**"
  ],
  "[php]": {
    "editor.formatOnSave": false,
    "editor.defaultFormatter": "bmewburn.vscode-intelephense-client"
  },
  "[twig]": {
    "editor.formatOnSave": false
  },
  "[yaml]": {
    "editor.insertSpaces": true,
    "editor.tabSize": 2,
    "editor.autoIndent": "advanced"
  },
  "files.associations": {
    "*.env*": "dotenv",
    "*.html.twig": "twig"
  },
  "files.exclude": {
    "**/var/cache": true,
    "**/vendor": false
  },
  "search.exclude": {
    "**/var": true,
    "**/vendor": true,
    "**/node_modules": true,
    "**/storage": true
  },
  "docker.context": "default",
  "sqltools.connections": [
    {
      "name": "Plataforma360 Local",
      "driver": "PostgreSQL",
      "server": "localhost",
      "port": 5432,
      "database": "plataforma360",
      "username": "plataforma360",
      "password": "plataforma360"
    }
  ],
  "editor.rulers": [120],
  "editor.tabSize": 4,
  "editor.insertSpaces": true,
  "terminal.integrated.defaultProfile.windows": "PowerShell"
}
```

> **Nota:** O PostgreSQL fica exposto na porta `5432` do host quando os containers estão rodando (`make up`). Os valores padrao usados no projeto sao `database=plataforma360`, `username=plataforma360` e `password=plataforma360`, salvo customizacao no `.env`.

---

## 4. Configuração de debug (Xdebug)

O container PHP já inclui o Xdebug. Crie o arquivo `.vscode/launch.json`:

```json
{
  "version": "0.2.0",
  "configurations": [
    {
      "name": "Listen for Xdebug (Docker)",
      "type": "php",
      "request": "launch",
      "port": 9003,
      "pathMappings": {
        "/var/www/html": "${workspaceFolder}/apps/core"
      },
      "log": false,
      "ignore": [
        "**/vendor/**/*.php"
      ]
    }
  ]
}
```

### Como usar

1. Pressione `F5` (ou `Run → Start Debugging`) para iniciar o listener.
2. Coloque um breakpoint em qualquer arquivo PHP em `apps/core/src/`.
3. Acesse a URL correspondente no browser — o VS Code vai pausar na linha marcada.

### Verificar se o Xdebug está ativo no container

```bash
wsl -e bash -c "cd /mnt/c/Plataforma360 && docker compose exec php php -m | grep xdebug"
```

---

## 5. Tasks integradas

Crie `.vscode/tasks.json` para executar os comandos `make` diretamente pelo VS Code (`Ctrl+Shift+P` → `Run Task`):

```json
{
  "version": "2.0.0",
  "tasks": [
    {
      "label": "🐳 make up",
      "type": "shell",
      "command": "wsl -e bash -c \"cd /mnt/c/Plataforma360 && make up\"",
      "group": "build",
      "presentation": { "reveal": "always", "panel": "shared" }
    },
    {
      "label": "🛑 make down",
      "type": "shell",
      "command": "wsl -e bash -c \"cd /mnt/c/Plataforma360 && make down\"",
      "group": "build",
      "presentation": { "reveal": "always", "panel": "shared" }
    },
    {
      "label": "🤖 make up-ai",
      "type": "shell",
      "command": "wsl -e bash -c \"cd /mnt/c/Plataforma360 && make up-ai\"",
      "group": "build",
      "presentation": { "reveal": "always", "panel": "shared" }
    },
    {
      "label": "⚙️ make up-ops",
      "type": "shell",
      "command": "wsl -e bash -c \"cd /mnt/c/Plataforma360 && make up-ops\"",
      "group": "build",
      "presentation": { "reveal": "always", "panel": "shared" }
    },
    {
      "label": "🚀 make up-all",
      "type": "shell",
      "command": "wsl -e bash -c \"cd /mnt/c/Plataforma360 && make up-all\"",
      "group": "build",
      "presentation": { "reveal": "always", "panel": "shared" }
    },
    {
      "label": "🔄 make migrate",
      "type": "shell",
      "command": "wsl -e bash -c \"cd /mnt/c/Plataforma360 && make migrate\"",
      "group": "build",
      "presentation": { "reveal": "always", "panel": "shared" }
    },
    {
      "label": "📋 make logs",
      "type": "shell",
      "command": "wsl -e bash -c \"cd /mnt/c/Plataforma360 && make logs\"",
      "group": "none",
      "presentation": { "reveal": "always", "panel": "dedicated" },
      "isBackground": true
    },
    {
      "label": "🐚 make bash",
      "type": "shell",
      "command": "wsl -e bash -c \"cd /mnt/c/Plataforma360 && make bash\"",
      "group": "none",
      "presentation": { "reveal": "always", "panel": "dedicated" }
    },
    {
      "label": "✅ make test",
      "type": "shell",
      "command": "wsl -e bash -c \"cd /mnt/c/Plataforma360 && make test\"",
      "group": "test",
      "presentation": { "reveal": "always", "panel": "shared" }
    }
  ]
}
```

### Como usar

- `Ctrl+Shift+P` → `Tasks: Run Task` → selecionar a tarefa desejada.
- Para build rápido: `Ctrl+Shift+B` executa o grupo `build` padrão.

---

## 6. Integração com WSL (Windows)

No Windows, o projeto roda dentro do WSL (Ubuntu). Há duas formas de trabalhar:

### Opção A — Abrir o projeto pelo Windows (C:\Plataforma360)

O VS Code abre os arquivos direto em `C:\Plataforma360`. Os comandos Docker precisam ser prefixados com `wsl -e bash -c "..."`.

```powershell
# No PowerShell do VS Code terminal:
wsl -e bash -c "cd /mnt/c/Plataforma360 && make migrate"
```

### Opção B — Abrir pelo filesystem do WSL (recomendado para performance)

```bash
# No WSL:
cd /mnt/c/Plataforma360
code .
```

O VS Code abre com a extensão **Remote - WSL** e os comandos `make` rodam nativamente no terminal integrado sem prefixo.

> **Performance:** Manipular arquivos PHP/Twig pelo filesystem WSL (`/mnt/c/`) é significativamente mais rápido do que pelo Windows (`C:\`) para operações de escrita.

### Path mapping no Docker

Independente da opção escolhida, o Docker monta o volume como:

```yaml
# docker-compose.yml
volumes:
  - ./apps/core:/var/www/html
```

O Xdebug usa o `pathMappings` do `launch.json` para mapear `/var/www/html` → `apps/core`.

---

## 7. GitHub Copilot — Agentes e Skills

O projeto inclui agentes e skills do Copilot configurados em `.github/`. Eles conhecem a arquitetura, entidades e padrões da Plataforma360.

### Agentes disponíveis

| Agente | Quando usar |
|---|---|
| `@Plataforma360` | Qualquer tarefa geral da plataforma |
| `@Backend Symfony` | Entidades PHP, services, controllers, migrations |
| `@Frontend Twig` | Templates, Bootstrap, navbar, formulários |
| `@Dados e Pipeline` | CKAN, warehouse, flows Kestra, ETL |
| `@Módulo IA` | Ollama, OpenAI, Qdrant, embeddings |
| `@Operações e Governança` | Pipelines, alertas, auditoria, LGPD |

### Skills disponíveis

| Skill | Quando usar |
|---|---|
| `/nova-entidade` | Criar entidade Doctrine + repository + migration |
| `/novo-modulo` | Criar módulo CRUD completo (entidade + controller + templates) |
| `/kestra-flow` | Criar flow YAML Kestra + registrar pipeline no portal |

### Exemplos de uso

```
# No chat do Copilot:
@Backend Symfony criar uma entidade Municipio com campos nome, uf, ibgeCode e populacao

@Frontend Twig criar template de listagem para a entidade Municipio com busca por nome

/novo-modulo criar módulo de Projetos com campos titulo, status e responsavel

/kestra-flow criar flow de ingestão diária de dados do CKAN de turismo
```

> Manual completo: [manual-agentes.md](manual-agentes.md)

### Instructions automáticas

O Copilot aplica instruções automaticamente conforme o arquivo aberto:

| Arquivo editado | Instrução aplicada |
|---|---|
| `apps/core/src/**/*.php` | Symfony 7.4, PHP 8.3, Doctrine ORM 3.2 |
| `apps/core/templates/**/*.twig` | Bootstrap 5.3, Bootstrap Icons, padrão visual da plataforma |
| `future/kestra/flows/**/*.yml` | Kestra: namespace plataforma360, PostgreSQL, Python tasks |

No frontend, o padrao atual carregado pelo Copilot inclui:

- uso obrigatorio de `{% block body %}`;
- padding lateral de `20px` alinhado ao navbar;
- preferencia por hub pages no lugar de dropdowns grandes;
- home publica e login seguindo o padrao definido em `.github/agents/frontend.agent.md`.

---

## 8. Fluxo de trabalho recomendado

### Novo desenvolvimento

```
1. make up              → sobe o core
2. F5 no VS Code        → ativa o listener Xdebug
3. Abrir terminal WSL   → usar make bash para entrar no container
4. Criar entidade       → @Backend Symfony ou /nova-entidade
5. make migrate         → aplicar migrations
6. Criar tela           → @Frontend Twig
7. Testar no browser    → http://localhost
8. Verificar cenários   → docs/testes.md
```

### Trabalho com IA

```
1. make up-ai           → sobe Ollama + Qdrant
2. docker compose exec ollama ollama pull llama3
3. Acessar IA → Modelos no portal → cadastrar modelo
4. Usar @Módulo IA para desenvolver contextos, agentes ou prompts
```

### Trabalho com Kestra / Pipelines

```
1. make up-ops          → sobe Kestra (http://localhost:8082/ui/)
2. Usar /kestra-flow ou @Dados e Pipeline
3. Testar flow no Kestra UI
4. Registrar como pipeline em Operações → Pipelines
```

---

## 9. Solução de problemas

### VS Code não encontra PHP

Confirme o PHP no container:
```bash
wsl -e bash -c "docker compose exec php php --version"
```
Ajuste `php.validate.executablePath` no `settings.json` se necessário.

### Xdebug não conecta

1. Verifique se o listener está ativo (`F5`).
2. Confirme a porta 9003 no `launch.json`.
3. Teste se o Xdebug está carregado:
   ```bash
   wsl -e bash -c "cd /mnt/c/Plataforma360 && docker compose exec php php -m | grep xdebug"
   ```

### SQLTools não conecta ao PostgreSQL

1. Confirme que os containers estão rodando (`make up`).
2. Verifique se a porta 5432 está exposta:
   ```bash
   docker compose ps
   ```
3. Credenciais padrão: servidor `localhost:5432`, banco/usuário/senha `app`.

### Copilot Chat não usa os agentes do projeto

1. Confirme que a extensão **GitHub Copilot Chat** está instalada e atualizada.
2. Verifique que os arquivos `.github/agents/*.agent.md` existem no workspace.
3. No chat, use `@` para invocar agentes: `@Plataforma360`.
4. Se os agentes não aparecem, feche e reabra o VS Code.

### Performance lenta no Windows

Abra o terminal do VS Code, execute:
```bash
wsl --shutdown
```
Depois reabra o VS Code com o projeto pelo WSL (`code .` dentro do Ubuntu).

---

*Plataforma360 — configuração de ambiente v1.0 — Maio/2026*
