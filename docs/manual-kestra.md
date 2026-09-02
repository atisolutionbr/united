# Manual do Kestra na Plataforma360

## O que e o Kestra no contexto da Plataforma360

O Kestra é a camada de orquestração de dados e automação de pipelines da Plataforma360. Ele permite que uma prefeitura agende, execute, monitore e versione fluxos de dados sem acoplar essas rotinas ao portal central em Symfony.

A partir da **Fase 6**, o Kestra passou a ser integrado diretamente ao `docker-compose.yml` principal com o perfil Docker `ops`, compartilhando a rede `plataforma360` com os demais containers. Isso permite que o portal Symfony dispare e acompanhe execuções diretamente via `KestraService`.

## Papel do Kestra na arquitetura

Na arquitetura da Plataforma360, o Kestra opera como modulo separado da aplicacao principal. Seu papel e:

- receber dados de fontes municipais, arquivos, APIs e coletas futuras;
- armazenar dados brutos como etapa inicial do pipeline;
- transformar e preparar cargas para PostgreSQL;
- registrar execucoes, tentativas e historico operacional;
- integrar APIs, bancos, arquivos e servicos externos;
- manter a automacao tecnica fora do portal central de governanca;
- ser acionado pelo portal Symfony via API REST (Fase 6).

O Airflow nao e dependencia inicial. Ele permanece apenas como opcao futura avancada para municipios que precisarem de uma camada adicional de orquestracao complexa.

## Limites de responsabilidade do Kestra

O Kestra deve ser usado para fluxos tecnicos de dados e automacao da data platform. Ele nao deve assumir papeis de:

- interface de usuario final;
- dashboards executivos;
- atendimento digital via chatbot;
- automacoes conversacionais;
- observabilidade tecnica central.

## Diferenca entre Kestra e os demais componentes

- `Kestra`: orquestra fluxos, automacoes, ingestao e jobs.
- `Symfony`: entrega o portal central de governanca, APIs, telas administrativas, permissoes e configuracoes do ecossistema. Na Fase 6, passou a disparar e monitorar execucoes Kestra via `KestraService`.
- `PostgreSQL`: persiste dados relacionais, metadados, configuracoes e dados estruturados tratados. Um banco PostgreSQL separado (`kestra-postgres`) é usado internamente pelo Kestra.
- `Metabase`: consome dados tratados para dashboards, indicadores e analytics de negocio.
- `Ollama`: modelos de IA local (LLM). Perfil Docker `ai`.
- `Qdrant`: banco vetorial para embeddings e busca semântica. Perfil Docker `ai`.
- `Grafana`: monitora logs, metricas, alertas e saude tecnica da infraestrutura (futuro).
- `n8n`: opera webhooks, automacoes operacionais, IA e integracoes conversacionais no AI Hub (futuro).

## Como subir o Kestra no Docker

O Kestra está integrado ao `docker-compose.yml` principal através do perfil `ops`. Não existe mais um arquivo separado `docker-compose.kestra.yml`.

Ambiente recomendado no Windows: executar os comandos dentro do WSL Ubuntu.

```bash
cd /mnt/c/Plataforma360
docker compose --profile ops up -d
```

Isso sobe dois containers: `plataforma360-kestra-postgres` (banco exclusivo do Kestra) e `plataforma360-kestra`.

Para subir toda a plataforma com Kestra e IA juntos:

```bash
docker compose --profile ops --profile ai up -d
```

Para parar apenas o Kestra:

```bash
docker compose --profile ops down
```

## Como acessar a interface web

A interface web do Kestra fica exposta em:

- http://localhost:8082/ui/

Ao acessar pela primeira vez em ambiente local, o redirecionamento HTTP para `/ui/` indica que o servico esta ativo.

## Como criar ou importar um fluxo

Existem duas abordagens recomendadas:

1. Versionar o fluxo em YAML dentro de `future/kestra/flows/`.
2. Importar o YAML pela interface web do Kestra ou por API quando a equipe operacional estiver pronta para executar.

Fluxo inicial versionado neste projeto:

- `future/kestra/flows/olinda360_primeira_ingestao.yml`

Arquivo de exemplo usado pelo fluxo:

- `future/kestra/examples/pontos_turisticos_olinda.csv`

## Como executar o primeiro fluxo de ingestao

Fluxo: `olinda360_primeira_ingestao`

Objetivo do fluxo:

- simular ingestao de dados turisticos de Olinda;
- preparar um CSV bruto para armazenamento inicial;
- gerar uma base de transformacao para posterior carga estruturada.

Passos operacionais:

1. Suba o stack do Kestra.
2. Abra http://localhost:8082/ui/.
3. Importe o arquivo `future/kestra/flows/olinda360_primeira_ingestao.yml` no namespace `plataforma360.turismo`.
4. Execute o fluxo pela UI.
5. Verifique nos logs do fluxo a preparacao dos artefatos, a contagem de registros e os passos de transformacao.

## Como o Kestra se conecta ao PostgreSQL

No estado atual, o Kestra usa um PostgreSQL próprio (`kestra-postgres`) para metadados e fila interna. Em fluxos de dados, os pipelines devem gravar nos destinos corretos:

- `PostgreSQL principal` (`postgres`) para dados estruturados prontos para consumo;
- tabelas `staging.*` e `warehouse.*` do banco principal como destino dos pipelines;
- `Metabase` como camada de leitura analítica sobre dados tratados.

A conexão nos flows Kestra com o banco principal pode ser feita via JDBC com as credenciais de `POSTGRES_DB=plataforma360` disponíveis no `docker-compose.yml`.

## Integração com o Portal Symfony (Fase 6)

A partir da Fase 6, o portal Symfony se integra ao Kestra via `KestraService`:

- **Navegação por hub:** o menu **Operações** abre um hub do módulo, de onde a equipe acessa Visão Geral, Pipelines, Execuções, Observabilidade, Alertas, Métricas IA e Logs.
- **Disparar execução:** menu **Operações → Pipelines → botão ▶** dispara o flow no namespace/flow configurado no cadastro do pipeline.
- **Acompanhar status:** **Operações → Execuções** mostra o status em tempo real sincronizado com a API do Kestra.
- **Ver logs:** detalhe de cada execução exibe logs retornados pela API do Kestra.
- **Observabilidade:** **Operações → Observabilidade** inclui o status de saúde do Kestra.

Para que a integração funcione, o Kestra precisa estar na mesma rede Docker (`plataforma360`) que o container PHP — o que é garantido pelo perfil `ops` no `docker-compose.yml`.

## Como o módulo CKAN do Core prepara a integração com o Kestra

O Core Symfony mantém uma camada administrativa para provedores CKAN que prepara os insumos que o Kestra vai consumir:

- cadastro do provedor em `data_providers`;
- sincronização da lista de pacotes em `provider_packages` via `package_list`;
- sincronização dos arquivos de cada dataset em `dataset_resources` via `package_show`;
- trilha operacional em `ingestion_runs` para status, mensagens e logs.

O fluxo futuro recomendado é:

1. o Core identifica quais `provider_packages` estão monitorados;
2. dispara um pipeline via Kestra pela interface de Operações;
3. o Kestra baixa os arquivos e grava na zona `data/raw`;
4. o pipeline promove o dado para `staging` e `warehouse`;
5. o Core atualiza a trilha de execução via sincronização de status.

## Boas práticas para instalar localmente

- usar WSL Ubuntu no Windows para execução dos comandos Docker;
- armazenar fluxos em controle de versão em `future/kestra/flows/`;
- começar por ingestões simples e auditáveis;
- separar dados brutos de dados prontos para consumo analítico;
- evitar misturar regras de pipeline dentro do Core Symfony;
- registrar credenciais reais em variáveis de ambiente e nunca em arquivos versionados.

## Comandos úteis de Docker

```bash
# Subir apenas o Kestra
docker compose --profile ops up -d

# Subir Kestra + IA (Ollama + Qdrant)
docker compose --profile ops --profile ai up -d

# Parar
docker compose --profile ops down

# Ver status
docker compose ps

# Logs do Kestra
docker compose logs -f kestra

# Testar se está respondendo
curl -I http://localhost:8082
```

## Troubleshooting básico

**Porta 8082 em uso**
- ajuste `KESTRA_PORT` no `.env` antes de subir.

**Docker não encontrado no Windows**
- execute os comandos via WSL Ubuntu.

**Kestra não conecta no PostgreSQL**
- verifique `future/kestra/application.yml` e os logs com `docker compose logs -f kestra`.

**Interface não abre**
- confirme com `docker compose ps` se a porta `8082->8080` está publicada;
- teste `curl -I http://localhost:8082` e espere redirecionamento para `/ui/`.

**Portal Symfony não consegue disparar pipelines**
- confirme que o Kestra está rodando com o perfil `ops`;
- confirme que ambos estão na rede `plataforma360` (`docker network inspect plataforma360-network`);
- verifique o endpoint configurado em `KestraService` (padrão: `http://kestra:8080`).
