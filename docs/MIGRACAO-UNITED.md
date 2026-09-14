# United Ati: ambiente local e publicação

## Local

Execute `scripts/start-local.ps1` com Docker Desktop em execução. United: http://localhost:4300/unitedati. O KFlow continua na porta 3000 e utiliza outro volume de banco. Não executar `docker compose down -v`.

O login mostra Ambiente Local mesmo quando APP_ENV=prod é usado para desempenho. A indicação depende de APP_DEPLOYMENT_ENV; o override local fixa local e o de produção fixa production.

## VPS informada

Ubuntu 22.04, IP 143.95.167.97, SSH 22022. Domínio definitivo: united.atisolution.com.br. Ainda é necessário informar usuário SSH, chave disponível e provedor DNS. Este documento prepara a publicação; não confirma uma implantação realizada.

1. No DNS de atisolution.com.br, criar registro A, nome united, valor 143.95.167.97, TTL 300. Conferir se existe AAAA antigo antes de habilitar IPv6. Aguardar a resolução pública.
2. Acessar com `ssh -p 22022 USUARIO@143.95.167.97`. Instalar Docker Engine e Compose conforme https://docs.docker.com/engine/install/ubuntu/ e Nginx no host. Manter SSH 22022 permitido e liberar HTTP 80/HTTPS 443. Não publicar o PostgreSQL na internet.
3. Clonar https://github.com/atisolutionbr/united.git na branch kflow360, por exemplo em /opt/united. Preparar .env privado: PROJECT_NAME=united, APP_ENV=prod, APP_DEBUG=0, APP_DEPLOYMENT_ENV=production, DEFAULT_URI=https://united.atisolution.com.br, POSTGRES_DB/USER/PASSWORD e DATABASE_URL coerentes e senha exclusiva. Nunca versionar .env.
4. Para transportar vínculos já configurados, exportar o PostgreSQL **do United** com pg_dump, copiar por canal seguro e restaurar somente na base nova da VPS. Preservar APP_SECRET por transferência segura, pois as credenciais dos ERPs estão criptografadas com ele. Não sobrescrever uma base existente sem backup. Garantir conectividade da VPS com o ERP; acesso liberado para o PC não implica acesso liberado para a VPS.
5. Executar os comandos abaixo dentro de /opt/united. O override de produção expõe a aplicação apenas em 127.0.0.1:18080 e marca Ambiente Produção.

```sh
test -f apps/core/.env || touch apps/core/.env
docker compose -f docker-compose.yml -f compose.production.yaml up -d --build postgres php nginx
docker compose -f docker-compose.yml -f compose.production.yaml exec -T php php bin/console doctrine:migrations:migrate --env=prod --no-interaction
docker compose -f docker-compose.yml -f compose.production.yaml exec -T php php bin/console asset-map:compile --env=prod
docker compose -f docker-compose.yml -f compose.production.yaml exec -T php php bin/console cache:clear --env=prod
```

6. Instalar infra/nginx/host/united.atisolution.com.br.conf em sites-available do Nginx, ativar o site, executar `sudo nginx -t` e recarregar. Após o DNS apontar à VPS, emitir certificado com Certbot Nginx seguindo https://certbot.eff.org/instructions?os=snap&ws=nginx, usando `sudo certbot --nginx -d united.atisolution.com.br`. Conferir renovação com `sudo certbot renew --dry-run`.
7. Validar HTTPS, login, Ambiente Produção, assets, leituras do ERP e backup antes de ativar deploy automático. Testes de gravação no ERP devem usar registros de homologação e os vínculos reais definidos pelo responsável.

## Versionamento e deploy

Preservados repositório united e branch kflow360. O workflow deploy-kflow360.yml exige DEPLOY_ENABLED=true e ambiente GitHub production. Configurar variáveis DEPLOY_HOST=143.95.167.97, DEPLOY_PORT=22022, DEPLOY_USER, DEPLOY_PATH e segredo DEPLOY_SSH_KEY. Deixar desabilitado até a primeira implantação ser validada. Antes de atualizar produção, fazer backup do PostgreSQL. Para reversão, recuperar o commit anterior; migrações e dados exigem avaliação específica.

## Vínculos e processos

Cadastros: vínculos salvos mostram tabela e quantidade de campos. Todos podem ser excluídos; excluir um vínculo não apaga a tabela do ERP. Formulários adicionais podem ser abertos individualmente. Quando existe apenas um formulário adicional vinculado de um cadastro, ele é usado na tela desse cadastro.

Requisições e solicitações: Produto utiliza o cadastro de Produtos; pesquisar código, nome e código de barras exige os respectivos campos vinculados. Em Vínculos de listas, configurar origem BD/API/WebService de Solicitante, Projeto, Fase, Depósito e outros campos textuais. Selecionar uma opção válida antes de salvar. Não são inventadas tabelas de ERP.

Aprovações: configurar origem, chave do registro, campos, situação pendente e retorno aprovado/reprovado por categoria. Listagem de 15 por página; ações registradas em auditoria. Solicitações/requisições têm rascunho e envio explícito ao ERP. O acompanhamento das etapas é local; geração de OC/NF depende dos serviços e contratos do ERP configurados, não ocorre implicitamente.

Sanitização: análise de duplicidades, descrição e consistência dos campos fiscais vinculados. A aplicação de padronização é explícita e limita-se à descrição. NCM sozinho não determina a tributação; a análise não substitui classificação fiscal validada com as tabelas oficiais.

## Implantação em 13/09/2026

VPS provisionada em /opt/united com Docker Engine, Compose, PostgreSQL/PostGIS, PHP, Nginx e Certbot. Domínio HTTPS configurado com renovação automática. Banco da aplicação restaurado da cópia consistente do United local; APP_SECRET preservado. Login admin.ati validado em HTTPS. Serviços locais permanecem separados.

Diagnóstico do Senior: o vínculo usa host.docker.internal:1433 e base sapiens. A instância Windows SQL Server (SQLEXPRESS) estava parada; a outra instância MSSQLSERVER não foi interrompida. Iniciar SQLEXPRESS é requisito para recuperar as consultas. Uma falha de comunicação não significa tabela vazia.

Acesso da VPS ao Senior local: preparado usuário SSH restrito united-tunnel, limitado à escuta 172.17.0.1:21433, sem shell ou senha. O início do túnel e a reconexão automática no Windows aguardam autorização explícita. O script scripts/senior-tunnel.ps1 usa chave local privada não versionada. Não executar sem essa autorização. Após habilitar, a origem na VPS deve usar host.docker.internal:21433; a origem local continua na porta 1433. A disponibilidade do ERP depende do PC ligado e do SQL Express em execução. Nenhuma cópia independente do banco ERP foi criada na VPS.

## Revisão 66 — listas pesquisáveis e relacionamentos

Produto nas telas Requisição e Solicitação tem botão Lista, pesquisa digitada por código/nome/código de barras e seleção explícita do resultado. Consultas antigas são descartadas quando o usuário altera a pesquisa. Em Vínculos de listas, a tabela Relacionamentos dos campos mostra origem e mapeamento de cada campo. A lista de Produtos pode acompanhar os campos de um formulário de Produtos em BD, API ou WebService; alterações de mapeamento são aplicadas às duas telas. APIs/WS ainda exigem operação e caminho da coleção compatíveis com o serviço do ERP.

O usuário definiu que configurará Senior por WebService na VPS; o túnel SQL não será iniciado. Os vínculos existentes foram preservados. A integração real aguarda a configuração desse WebService.

## Revisão 67 — desempenho

- Localhost atendido em IPv4 e IPv6: removida a tentativa frustrada de IPv6 antes de cada conexão. Medição inicial dos arquivos: cerca de 2 s; após ajuste: 0–0,02 s. Login local medido em 0,44 s após a correção da porta.
- Consultas liberam o bloqueio da sessão antes do acesso externo, permitindo navegar enquanto o ERP responde.
- SQL Server usa TDS 7.4 explícito, login de 3 s e limite de consulta de 8 s. O teste com servidor que aceita conexão sem responder passou em aproximadamente 3 s; outra tentativa durante a pausa de 20 s retorna imediatamente. Novos endpoints/credenciais têm outra chave e não herdam a pausa.
- Conexão PDO reutilizada durante a requisição; metadados por conexão em cache por 5 minutos, falhas por 10 s. Resultados com erro ficam 20 s em cache para evitar uma sequência de consultas ao mesmo ERP indisponível.
- Painel mostra os indicadores disponíveis em cache e consulta o ERP quando o usuário clica Atualizar indicadores; não inicia consultas externas em cada abertura.
- Service worker atualizado: não intercepta navegação nem substitui páginas por um aviso local. Falhas de gravação no cache não impedem carregar arquivos da rede.
- OPcache revalida arquivos a cada 30 s. Os scripts de inicialização/deploy recarregam o PHP-FPM de forma graciosa após publicar a versão, aplicando código e configurações sem esperar esse intervalo.

Os limites de resposta evitam esperas prolongadas, mas não tornam um ERP desligado acessível. Operações externas sem confirmação continuam exigindo conferência de resultado antes de uma nova tentativa.

## Revisão 68 — runtime local

O código executado pelo PHP e Nginx locais passa a residir no volume Linux exclusivo united-ati-local-runtime. O projeto editável continua no diretório Windows. Após alterações, executar scripts/sync-local.ps1; scripts/start-local.ps1 também sincroniza automaticamente. A sincronização valida o volume de destino, preserva vendor, var e arquivos de ambiente, compila os assets e recarrega o PHP-FPM. Os volumes de dados e serviços do KFlow permanecem separados.

Chamadas API de processos têm limite total de 10 segundos; SOAP usa conexão de 3 segundos e leitura de 8 segundos. Uma falha ou timeout de envio não confirma que o ERP deixou de receber a operação: conferir o resultado antes de repetir.

Na verificação de 14/09, o Windows estava com aproximadamente 500 MB de memória física disponível, o que também afeta o desempenho do Docker.
A inicialização reutiliza a imagem local quando existente. Ao alterar dependências PHP ou o Dockerfile, reconstruir explicitamente com docker compose -p united-ati -f docker-compose.yml -f compose.local.yaml build php antes de iniciar novamente.
Para mudanças exclusivamente PHP, com assets já compilados, sync-local.ps1 -SkipAssets evita recompilar Sass. Em 14/09 foi aplicado limite de 1,5 CPU ao container de relatórios plataforma360-metabase, que chegou a consumir quase três núcleos; ele permaneceu ativo. Esse ajuste é do container atual e não altera o projeto KFlow.
