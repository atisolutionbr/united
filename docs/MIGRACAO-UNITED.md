# United · Ati Solution

Domínio de produção: https://united.atioslution.com.br

Repositório: https://github.com/atisolutionbr/united

O histórico e a branch `kflow360` foram preservados. A versão continua no formato mês.ano e o build sequencial continua em `KFlowRelease`; esta entrega incrementa o build de 58 para 59. As alterações locais presentes no projeto de origem também integram esta cópia.

## Nova VPS

O deploy permanece desativado até configurar o ambiente `production` no GitHub. Cadastre `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PORT`, `DEPLOY_PATH`, o segredo `DEPLOY_SSH_KEY` e, somente quando a VPS estiver pronta, `DEPLOY_ENABLED=true`.

Crie o `.env` a partir de `.env.example` e defina segredos exclusivos para a nova instalação. Em produção use `APP_ENV=prod`, `APP_DEBUG=0`, `DEFAULT_URI=https://united.atioslution.com.br` e `CORS_ALLOW_ORIGIN=^https://united\.atioslution\.com\.br$`. Configure as credenciais do banco e as integrações na nova instância.

Instale Docker e Compose na VPS. O proxy de host em `infra/nginx/host/united.atioslution.com.br.conf` encaminha para a porta local 18080; configure `APP_PORT=127.0.0.1:18080`. Aponte o DNS para o novo IP e emita o certificado TLS desse domínio antes da liberação de produção.

Arquivos de segredos `.env` da instalação antiga não são publicados. Banco em execução e volumes Docker externos ao código precisam de exportação/importação na migração da VPS. Dependências e caches são reconstruídos pelos procedimentos existentes.
