# United · Ati Solution

Plataforma de integração e governança cadastral da Ati Solution.

O United · Ati Solution conecta empresas e ERPs por banco de dados, API ou WebService, organiza os vínculos por formulário e oferece fluxos para produtos, clientes, fornecedores, transportadoras, requisições e aprovações.

## Ambiente local

```powershell
docker compose up -d --build
```

Após iniciar os serviços, acesse `http://localhost:3000`.

## Verificações

```powershell
docker compose exec php php bin/console lint:twig templates/kflow
docker compose exec php php /tmp/verify_ati_branding.php --database --http=http://nginx/login
```

O guard de marca também é executado no CI antes de qualquer publicação. Ele verifica fonte, documentação, ativos próprios, logs, banco e resposta HTTP para impedir que material externo de desenvolvimento seja entregue com o produto.

## Ati Solution

Site: [atisolution.com.br](https://atisolution.com.br)
