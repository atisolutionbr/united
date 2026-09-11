# United Ati

Plataforma de integração e governança cadastral da Ati Solution.

O United Ati conecta empresas e ERPs por banco de dados, API ou WebService, organiza os vínculos por formulário e oferece fluxos para produtos, clientes, fornecedores, transportadoras, requisições e aprovações.

## Ambiente local

No Windows, com o Docker Desktop iniciado e o `.env` local configurado, execute:

```powershell
./scripts/start-local.ps1
```

Abra http://localhost:4300/login. Na primeira execução, o script exporta o PostgreSQL do KFlow em execução e importa uma cópia consistente para um volume exclusivo do United Ati, preservando usuários, empresas e configurações criptografadas de conexão com o ERP Senior. O KFlow permanece em execução durante todo o procedimento. A conexão ao banco do ERP continua sendo a mesma; suas credenciais não são versionadas. O arquivo `compose.local.yaml` é exclusivo desse procedimento local.

| Serviço | KFlow | United Ati |
| --- | --- | --- |
| Aplicação | 3000 | 4300 |
| Metabase | 3001 | 4301 |
| Adminer | 8081 | 4381 |
| PostgreSQL | 5432 | 5433 |
| Kestra (opcional) | 8082 | 4382 |
| Ollama (opcional) | 11434 | 21434 |
| Qdrant (opcional) | 6333 | 16333 |

Containers, redes e volumes do United são exclusivos. O PostgreSQL interno do KFlow não é compartilhado nem interrompido; somente a conexão externa com o ERP Senior é reaproveitada. A importação inicial usa `pg_dump` com o KFlow online. O `.env` local preserva a chave de criptografia necessária para abrir as credenciais importadas.

Para uma instalação nova independente:

```powershell
docker compose up -d --build
```

Após iniciar os serviços, acesse `http://localhost:4300`.

## Verificações

```powershell
docker compose exec php php bin/console lint:twig templates/kflow
docker compose exec php php /tmp/verify_ati_branding.php --database --http=http://nginx/login
```

O guard de marca também é executado no CI antes de qualquer publicação. Ele verifica fonte, documentação, ativos próprios, logs, banco e resposta HTTP para impedir que material externo de desenvolvimento seja entregue com o produto.

## Ati Solution

Site: [atisolution.com.br](https://atisolution.com.br)
