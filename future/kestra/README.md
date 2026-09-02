# Kestra na Plataforma360

Este diretorio concentra a primeira camada de ingestao, automacao de pipelines e orquestracao de dados da Plataforma360.

## Conteudo

- `application.yml`: configuracao local do Kestra para ambiente Docker.
- `flows/`: fluxos versionados em YAML para ingestao e automacao.
- `examples/`: arquivos de exemplo usados nos fluxos iniciais.

## Objetivo

O Kestra entra como modulo de data platform desacoplado do core Symfony. A pilha principal da aplicacao continua em `docker-compose.yml`, enquanto a operacao de pipelines fica em `docker-compose.kestra.yml`.

## Primeiro fluxo

O fluxo `olinda360_primeira_ingestao` demonstra a ingestao inicial de pontos turisticos de Olinda a partir de um CSV local. Ele prepara artefatos para a futura carga em schema `raw` no PostgreSQL/PostGIS.

## Operacao local

```bash
docker compose -f docker-compose.kestra.yml up -d
docker compose -f docker-compose.kestra.yml logs -f --tail=200
```

Interface web local:

- http://localhost:8082/ui/

Documentacao operacional completa:

- `docs/manual-kestra.md`