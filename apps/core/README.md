Olinda 360
==========

Protótipo funcional da plataforma "Olinda 360 — Plataforma de Governança e Inteligência Turística", construído com Symfony, API Platform, Doctrine ORM e MySQL.

Instalação
----------

```bash
symfony new olinda360-platform --demo
cd olinda360-platform
composer require symfony/orm-pack
composer require --dev symfony/maker-bundle
composer require api
composer require symfony/serializer-pack
composer require nelmio/cors-bundle
composer require --dev orm-fixtures
```

Configuração do banco
---------------------

```dotenv
DATABASE_URL="mysql://root:@127.0.0.1:3306/olinda360?serverVersion=8.0&charset=utf8mb4"
```

Criar banco
-----------

```bash
php bin/console doctrine:database:create
```

Criar migration
---------------

```bash
php bin/console make:migration
```

Executar migration
------------------

```bash
php bin/console doctrine:migrations:migrate
```

Carregar dados
--------------

```bash
php bin/console doctrine:fixtures:load
```

Rodar servidor
--------------

```bash
symfony serve
```

Ou:

```bash
symfony server:start
```

Ou:

```bash
php -S localhost:8000 -t public/
```

Acessar no navegador
--------------------

```text
https://127.0.0.1:8000
http://127.0.0.1:8000
```

Recursos principais
-------------------

- API pública com recursos de organizações, pontos turísticos, eventos, hospedagens, guias, indicadores e fontes de dados.
- Exportação CSV para os principais conjuntos de dados.
- Dashboard inicial com totais consolidados.
- Página inicial de apresentação para demonstração do protótipo.

Monitoramento Batch
-------------------

O Olinda 360 já possui uma camada inicial de monitoramento para futuras rotinas batch de coleta automatizada. Essa área foi pensada para sustentar governança de dados, rastreabilidade operacional e observabilidade de integrações com fontes externas como Cadastur, FNRH, OpenStreetMap, IBGE e Dados Abertos Recife.

Como funciona
-------------

- As rotinas batch serão executadas em janelas noturnas via cron.
- Cada rotina possuirá status operacional, métricas de processamento e mensagens de execução.
- A implementação atual usa dados mockados/fixtures para antecipar a experiência administrativa e servir de base para as integrações reais.

Acessar a tela
--------------

- Menu superior: Monitoramento Batch
- URL direta: `/batch-monitor`
- Detalhes de uma rotina: `/batch-monitor/{id}`
- Exportação CSV: `/batch-monitor/export.csv`

Rodar comandos manualmente
--------------------------

```bash
php bin/console olinda360:collect:cadastur
php bin/console olinda360:collect:fnrh
php bin/console olinda360:collect:osm
php bin/console olinda360:collect:ibge
php bin/console olinda360:collect:recife-open-data
```

Esses comandos ainda são skeletons. Eles simulam a execução, exibem mensagens operacionais e deixam explícito que a atualização real da entidade `BatchRoutine` será integrada posteriormente.

Configurar cron futuramente
---------------------------

Exemplo de agendamento das futuras rotinas:

```cron
0 2 * * * php /caminho/olinda360-platform/bin/console olinda360:collect:cadastur
0 3 * * * php /caminho/olinda360-platform/bin/console olinda360:collect:fnrh
30 1 * * * php /caminho/olinda360-platform/bin/console olinda360:collect:osm
0 4 * * * php /caminho/olinda360-platform/bin/console olinda360:collect:ibge
45 2 * * * php /caminho/olinda360-platform/bin/console olinda360:collect:recife-open-data
```

Fluxo operacional esperado
--------------------------

- O cron dispara cada comando Symfony no horário configurado.
- O comando coleta dados da fonte externa e normaliza o payload.
- Os resultados passam a atualizar a entidade `BatchRoutine` com datas, contadores, status e mensagens.
- A área de Monitoramento Batch se torna o ponto central de governança e observabilidade do pipeline de dados turísticos.

Gestão de Dados
---------------

O Olinda360 agora possui uma área administrativa visual específica para manutenção manual, revisão e higienização das suas bases estratégicas. Essa camada reforça o princípio de que a plataforma não depende apenas de ingestões automáticas: ela também possui governança humana, curadoria e correções editoriais.

Como acessar
------------

- Menu superior: Gestão de Dados
- URL direta: `/admin/data-management`
- A página inicial agora envia o botão `Cadastrar Dados` para essa área administrativa.

O que existe na área
--------------------

- Painel com cards por entidade para Organizações, Pontos Turísticos, Eventos, Hospedagens, Guias Turísticos, Indicadores, Fontes de Dados e Rotinas Batch.
- CRUD visual em Twig com listagem, busca simples, paginação, visualização de detalhes, criação, edição e exclusão.
- Indicadores de higienização para registros incompletos, sem coordenadas, sem descrição, sem fonte e inativos quando aplicável.
- Ações rápidas para operações frequentes de curadoria.

Diferença entre as camadas
--------------------------

- `/admin/data-management`: área visual para manutenção manual, revisão, curadoria e higienização dos dados.
- `/api`: API interna/de integração, voltada a operações sistêmicas, automações e integrações autorizadas.
- `/public-api/v1`: API pública somente leitura para dados abertos turísticos.

Objetivo estratégico
--------------------

- Permitir governança humana sobre os dados turísticos do município.
- Dar autonomia para cadastro e correção editorial sem depender de pipelines automáticos.
- Sustentar rotinas de qualidade e revisão antes da exposição externa via APIs.

Camada MCP / IA
---------------

O Olinda360 agora possui uma camada inicial de MCP (Model Context Protocol) em `/mcp/v1`, pensada como gateway de contexto, recursos e ferramentas para integração futura com IA generativa, agentes inteligentes e assistentes turísticos urbanos.

Objetivo da camada MCP
----------------------

- Expor recursos turísticos e indicadores em um formato previsível para LLMs e agentes.
- Disponibilizar ferramentas invocáveis para busca, recomendação e leitura contextual de dados urbanos e turísticos.
- Entregar pacotes de contexto que apoiem respostas mais úteis, consistentes e interoperáveis.
- Tornar explícito que a arquitetura do Olinda360 é AI-ready e preparada para orquestração inteligente.

Endpoints principais
--------------------

- Home MCP: `/mcp/v1`
- Documentação: `/mcp/v1/docs`
- Recursos: `/mcp/v1/resources/*`
- Ferramentas: `/mcp/v1/tools/*`
- Contextos: `/mcp/v1/context/*`

Recursos expostos
-----------------

- `/mcp/v1/resources/tourist-spots`
- `/mcp/v1/resources/events`
- `/mcp/v1/resources/accommodations`
- `/mcp/v1/resources/guides`
- `/mcp/v1/resources/indicators`

Ferramentas iniciais
--------------------

- `POST /mcp/v1/tools/search-tourism`
- `POST /mcp/v1/tools/search-events`
- `POST /mcp/v1/tools/recommend-itinerary`
- `POST /mcp/v1/tools/get-city-indicators`

Contextos expostos
------------------

- `GET /mcp/v1/context/tourism`
- `GET /mcp/v1/context/city`

Arquitetura AI-ready
--------------------

- A API interna em `/api` continua responsável por integração operacional e fluxos sistêmicos.
- A API pública em `/public-api/v1` continua dedicada a dados abertos somente leitura.
- A camada MCP em `/mcp/v1` funciona como gateway semântico para LLMs, agentes e assistentes, combinando descoberta, contexto e ferramentas em um contrato consistente.
- Isso prepara o Olinda360 para interoperabilidade inteligente, copilotos institucionais e assistentes urbanos orientados a contexto.

API Pública de Dados Abertos
----------------------------

O Olinda360 agora possui duas camadas distintas de API:

- `/api`: API interna/de integração, mantida pelo API Platform, com operações completas para uso da plataforma e integrações autorizadas.
- `/public-api/v1`: API pública, somente leitura, voltada a dados abertos turísticos para consumo externo comum.

Diferença entre as APIs
-----------------------

- A API interna pode evoluir conforme as necessidades operacionais da plataforma.
- A API pública expõe apenas dados seguros e públicos, sem contatos privados, dados pessoais ou informações administrativas.
- A API pública aceita apenas `GET` e responde com JSON padronizado com `data` e `meta`.

Exemplos de endpoints públicos
-----------------------------

```text
GET /public-api/v1/status
GET /public-api/v1/openapi.json
GET /public-api/v1/tourist-spots
GET /public-api/v1/tourist-spots/1
GET /public-api/v1/events
GET /public-api/v1/accommodations
GET /public-api/v1/guides
GET /public-api/v1/indicators
GET /public-api/v1/data-sources
GET /public-api/v1/docs
```

Exemplos de filtros
-------------------

```text
/public-api/v1/tourist-spots?category=Cultural&district=Sitio Historico
/public-api/v1/events?search=carnaval&page=1&limit=5
/public-api/v1/indicators?source=Secretaria de Turismo
/public-api/v1/guides?search=olinda
```

Formato de resposta
-------------------

```json
{
	"data": [],
	"meta": {
		"page": 1,
		"limit": 20,
		"total": 100,
		"source": "Olinda360",
		"license": "Dados abertos para uso publico",
		"version": "v1"
	}
}
```

Política de uso
---------------

- A API pública é destinada a transparência, pesquisa, inovação e reaproveitamento cívico dos dados.
- Apenas registros ativos são expostos quando o recurso possui controle de ativação.
- Campos sensíveis, como email, telefone e informações internas, são excluídos da resposta pública.

Especificação OpenAPI pública
-----------------------------

- A especificação simplificada e separada da API pública está disponível em `/public-api/v1/openapi.json`.
- Esse documento descreve a camada pública versionada, os endpoints de consulta, parâmetros comuns e o formato base de resposta JSON.
- O objetivo é facilitar integrações externas sem expor detalhes da API interna em `/api`.

Recomendação de versionamento
-----------------------------

- Mantenha consumidores externos sempre apontando para um prefixo versionado, como `/public-api/v1`.
- Mudanças incompatíveis devem ser publicadas em uma nova versão, preservando estabilidade para clientes existentes.
