<?php

namespace App\DataFixtures;

use App\Entity\Accommodation;
use App\Entity\BatchRoutine;
use App\Entity\DataProvider;
use App\Entity\DataSource;
use App\Entity\Indicator;
use App\Entity\Organization;
use App\Entity\TourismEvent;
use App\Entity\TouristGuide;
use App\Entity\TouristSpot;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * Loads seed data for the Olinda360 prototype.
     */
    public function load(ObjectManager $manager): void
    {
        $this->loadUsers($manager);
        $organizations = $this->loadOrganizations($manager);
        $this->loadTouristSpots($manager, $organizations);
        $this->loadTourismEvents($manager, $organizations);
        $this->loadAccommodations($manager, $organizations);
        $this->loadTouristGuides($manager, $organizations);
        $this->loadIndicators($manager);
        $this->loadDataSources($manager);
        $this->loadDataProviders($manager);
        $this->loadBatchRoutines($manager);

        $manager->flush();
    }

    /**
     * Creates default login users expected by the login screen.
     */
    private function loadUsers(ObjectManager $manager): void
    {
        $items = [
            ['username' => 'Kadmin', 'fullName' => 'KFlow Administrator', 'email' => 'kadmin@united.local', 'roles' => [User::ROLE_ADMIN]],
        ];

        foreach ($items as $item) {
            $user = new User();
            $user->setUsername($item['username']);
            $user->setFullName($item['fullName']);
            $user->setEmail($item['email']);
            $user->setRoles($item['roles']);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'K4dm1n'));

            $manager->persist($user);
        }
    }

    /**
     * Creates organization seed records.
     *
     * @return array<string, Organization>
     */
    private function loadOrganizations(ObjectManager $manager): array
    {
        $items = [
            ['tourism-office', 'Secretaria de Turismo de Olinda', 'secretaria-de-turismo-de-olinda', 'orgao-publico', 'contato@turismo.olinda.pe.gov.br'],
            ['guides-association', 'Associacao de Guias Historicos de Olinda', 'associacao-de-guias-historicos-de-olinda', 'associacao', 'guias@olinda360.org'],
            ['hospitality-network', 'Rede de Hospedagem Sitio Historico', 'rede-de-hospedagem-sitio-historico', 'rede-hotelaria', 'rede@hoteisolinda.com'],
        ];

        $organizations = [];
        foreach ($items as [$key, $name, $slug, $type, $email]) {
            $organization = new Organization();
            $organization->name = $name;
            $organization->slug = $slug;
            $organization->type = $type;
            $organization->email = $email;
            $organization->phone = '(81) 90000-0000';
            $organization->district = 'Sitio Historico';
            $organization->active = true;

            $manager->persist($organization);
            $organizations[$key] = $organization;
        }

        return $organizations;
    }

    /**
     * Creates tourist spot seed records.
     *
     * @param array<string, Organization> $organizations
     */
    private function loadTouristSpots(ObjectManager $manager, array $organizations): void
    {
        $items = [
            ['Alto da Se', 'Mirante e feira de artesanato com vista panoramica.', 'Cultural', '-8.00844500', '-34.85535400'],
            ['Mosteiro de Sao Bento', 'Importante patrimonio barroco do seculo XVI.', 'Historico', '-8.01564600', '-34.85285000'],
            ['Igreja da Se', 'Marco religioso com arquitetura colonial.', 'Religioso', '-8.00803900', '-34.85576500'],
            ['Mercado da Ribeira', 'Centro de compras de artesanato local.', 'Compras', '-8.01630200', '-34.85383900'],
            ['Quatro Cantos', 'Ponto tradicional de encontro no Carnaval.', 'Cultural', '-8.01514900', '-34.85540200'],
        ];

        foreach ($items as [$name, $description, $category, $lat, $lng]) {
            $spot = new TouristSpot();
            $spot->name = $name;
            $spot->slug = $this->slugify($name);
            $spot->description = $description;
            $spot->category = $category;
            $spot->latitude = $lat;
            $spot->longitude = $lng;
            $spot->district = 'Sitio Historico';
            $spot->organization = $organizations['tourism-office'];
            $spot->active = true;

            $manager->persist($spot);
        }
    }

    /**
     * Creates tourism event seed records.
     *
     * @param array<string, Organization> $organizations
     */
    private function loadTourismEvents(ObjectManager $manager, array $organizations): void
    {
        $items = [
            ['Ensaio Aberto de Maracatu', 'Cultural', '2026-06-10 19:00:00', 'Mercado Eufrasio Barbosa'],
            ['Circuito Gastronomico de Olinda', 'Gastronomia', '2026-07-15 18:00:00', 'Sitio Historico'],
            ['Festival de Cultura Popular', 'Festival', '2026-08-05 16:00:00', 'Praca do Carmo'],
        ];

        foreach ($items as [$title, $category, $startDate, $location]) {
            $event = new TourismEvent();
            $event->title = $title;
            $event->slug = $this->slugify($title);
            $event->description = $title.' com atracoes para moradores e turistas.';
            $event->category = $category;
            $event->startDate = new \DateTime($startDate);
            $event->location = $location;
            $event->district = 'Sitio Historico';
            $event->isFree = true;
            $event->organization = $organizations['tourism-office'];

            $manager->persist($event);
        }
    }

    /**
     * Creates accommodation seed records.
     *
     * @param array<string, Organization> $organizations
     */
    private function loadAccommodations(ObjectManager $manager, array $organizations): void
    {
        $items = [
            ['Pousada Varanda Colonial', 'Pousada', 'Rua do Amparo, 120'],
            ['Hotel Mirante de Olinda', 'Hotel', 'Rua de Sao Bento, 45'],
            ['Hostel Arte & Mar', 'Hostel', 'Rua do Sol, 88'],
        ];

        foreach ($items as [$name, $category, $address]) {
            $accommodation = new Accommodation();
            $accommodation->name = $name;
            $accommodation->slug = $this->slugify($name);
            $accommodation->category = $category;
            $accommodation->address = $address;
            $accommodation->district = 'Sitio Historico';
            $accommodation->rooms = 20;
            $accommodation->beds = 40;
            $accommodation->wifi = true;
            $accommodation->parking = false;
            $accommodation->accessibility = true;
            $accommodation->organization = $organizations['hospitality-network'];

            $manager->persist($accommodation);
        }
    }

    /**
     * Creates tourist guide seed records.
     *
     * @param array<string, Organization> $organizations
     */
    private function loadTouristGuides(ObjectManager $manager, array $organizations): void
    {
        $items = [
            ['Maria da Guia', ['Portugues', 'Ingles'], ['Patrimonio Historico']],
            ['Joao Frevo', ['Portugues', 'Espanhol'], ['Carnaval', 'Cultura Popular']],
            ['Ana Recife', ['Portugues', 'Frances'], ['Roteiro Religioso']],
        ];

        foreach ($items as [$name, $languages, $specialties]) {
            $guide = new TouristGuide();
            $guide->name = $name;
            $guide->cadasturCode = 'CAD-'.strtoupper(substr(md5($name), 0, 6));
            $guide->languages = $languages;
            $guide->specialties = $specialties;
            $guide->phone = '(81) 98888-0000';
            $guide->email = $this->slugify($name).'@guias.olinda360.org';
            $guide->serviceRegion = 'Olinda e Recife';
            $guide->organization = $organizations['guides-association'];

            $manager->persist($guide);
        }
    }

    /**
     * Creates indicator seed records.
     */
    private function loadIndicators(ObjectManager $manager): void
    {
        $items = [
            ['Fluxo turistico mensal', 'Demanda', 48000, 'visitantes/mes', '2026-04-30', 'Secretaria de Turismo'],
            ['Taxa media de ocupacao', 'Hospedagem', 72.5, '%', '2026-04-30', 'Rede Hoteleira Local'],
            ['Tempo medio de permanencia', 'Demanda', 2.8, 'dias', '2026-04-30', 'Pesquisa de Campo'],
            ['Indice de satisfacao do visitante', 'Qualidade', 8.7, 'nota', '2026-04-30', 'Ouvidoria do Turismo'],
            ['Eventos ativos no mes', 'Oferta', 14, 'eventos', '2026-04-30', 'Calendario Municipal'],
        ];

        foreach ($items as [$title, $category, $value, $unit, $referenceDate, $source]) {
            $indicator = new Indicator();
            $indicator->title = $title;
            $indicator->slug = $this->slugify($title);
            $indicator->category = $category;
            $indicator->value = (float) $value;
            $indicator->unit = $unit;
            $indicator->referenceDate = new \DateTime($referenceDate);
            $indicator->source = $source;

            $manager->persist($indicator);
        }
    }

    /**
     * Creates data source seed records.
     */
    private function loadDataSources(ObjectManager $manager): void
    {
        $items = [
            ['Cadastur', 'Registro oficial de prestadores de servico turistico.', 'Cadastro Oficial', 'https://cadastur.turismo.gov.br'],
            ['FNRH', 'Ficha Nacional de Registro de Hospedes.', 'Hospedagem', null],
            ['OpenStreetMap', 'Dados geoespaciais colaborativos.', 'Geoespacial', 'https://www.openstreetmap.org'],
            ['Dados Abertos Recife', 'Base aberta de indicadores regionais.', 'Dados Abertos', 'https://dados.recife.pe.gov.br'],
            ['IBGE', 'Estatisticas demograficas e economicas.', 'Estatistica Oficial', 'https://www.ibge.gov.br'],
        ];

        foreach ($items as [$name, $description, $type, $url]) {
            $source = new DataSource();
            $source->name = $name;
            $source->slug = $this->slugify($name);
            $source->description = $description;
            $source->type = $type;
            $source->url = $url;
            $source->updateFrequency = 'Mensal';

            $manager->persist($source);
        }
    }

    /**
     * Creates CKAN provider seed records.
     */
    private function loadDataProviders(ObjectManager $manager): void
    {
        $provider = (new DataProvider())
            ->setName('Ministério do Turismo')
            ->setType(DataProvider::TYPE_CKAN)
            ->setBaseUrl('https://dados.turismo.gov.br')
            ->setPackageListPath('/api/3/action/package_list')
            ->setPackageShowPath('/api/3/action/package_show?id={package_id}')
            ->setIsActive(true)
            ->setNotes('Provedor inicial de demonstração para ingestão automatizada de datasets públicos via CKAN.')
        ;

        $manager->persist($provider);
    }

    /**
     * Creates mock monitoring routines for the batch observability panel.
     */
    private function loadBatchRoutines(ObjectManager $manager): void
    {
        $items = [
            [
                'name' => 'Coleta Cadastur',
                'slug' => 'coleta-cadastur',
                'sourceName' => 'Ministerio do Turismo / Cadastur',
                'sourceType' => 'API Publica',
                'description' => 'Sincroniza prestadores e cadastros oficiais para abastecer a camada de governanca turistica.',
                'apiUrl' => 'https://cadastur.turismo.gov.br/',
                'cronExpression' => '0 2 * * *',
                'scheduledTime' => '02:00',
                'status' => BatchRoutine::STATUS_SUCCESS,
                'lastExecutionAt' => $this->createRoutineDate('yesterday', '02:00'),
                'nextExecutionAt' => $this->createRoutineDate('today', '02:00'),
                'recordsProcessed' => 320,
                'recordsCreated' => 18,
                'recordsUpdated' => 42,
                'recordsFailed' => 0,
                'executionTimeSeconds' => 32.5,
                'lastMessage' => 'Coleta concluida com consolidacao de cadastros ativos.',
            ],
            [
                'name' => 'Coleta FNRH',
                'slug' => 'coleta-fnrh',
                'sourceName' => 'Ficha Nacional de Registro de Hospedes',
                'sourceType' => 'API Restrita',
                'description' => 'Preparada para integrar dados de hospedagem e fluxo de visitantes mediante credenciais futuras.',
                'apiUrl' => null,
                'cronExpression' => '0 3 * * *',
                'scheduledTime' => '03:00',
                'status' => BatchRoutine::STATUS_PENDING,
                'lastExecutionAt' => null,
                'nextExecutionAt' => $this->createRoutineDate('today', '03:00'),
                'recordsProcessed' => 0,
                'recordsCreated' => 0,
                'recordsUpdated' => 0,
                'recordsFailed' => 0,
                'executionTimeSeconds' => null,
                'lastMessage' => 'Aguardando implementacao da autenticacao segura da fonte restrita.',
            ],
            [
                'name' => 'Coleta OpenStreetMap',
                'slug' => 'coleta-openstreetmap',
                'sourceName' => 'OpenStreetMap / Overpass API',
                'sourceType' => 'API Publica Geografica',
                'description' => 'Atualiza camadas geoespaciais para pontos de interesse, mobilidade e referencias territoriais.',
                'apiUrl' => 'https://overpass-api.de/api/interpreter',
                'cronExpression' => '30 1 * * *',
                'scheduledTime' => '01:30',
                'status' => BatchRoutine::STATUS_SUCCESS,
                'lastExecutionAt' => $this->createRoutineDate('yesterday', '01:30'),
                'nextExecutionAt' => $this->createRoutineDate('today', '01:30'),
                'recordsProcessed' => 1280,
                'recordsCreated' => 76,
                'recordsUpdated' => 210,
                'recordsFailed' => 2,
                'executionTimeSeconds' => 84.7,
                'lastMessage' => 'Dois elementos foram descartados por geometria incompleta.',
            ],
            [
                'name' => 'Coleta IBGE',
                'slug' => 'coleta-ibge',
                'sourceName' => 'IBGE Cidades',
                'sourceType' => 'API Publica',
                'description' => 'Sincroniza referencias territoriais e indicadores cadastrais usados pelo modulo de inteligencia.',
                'apiUrl' => 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios',
                'cronExpression' => '0 4 * * *',
                'scheduledTime' => '04:00',
                'status' => BatchRoutine::STATUS_SUCCESS,
                'lastExecutionAt' => $this->createRoutineDate('yesterday', '04:00'),
                'nextExecutionAt' => $this->createRoutineDate('today', '04:00'),
                'recordsProcessed' => 45,
                'recordsCreated' => 0,
                'recordsUpdated' => 8,
                'recordsFailed' => 0,
                'executionTimeSeconds' => 12.1,
                'lastMessage' => 'Base territorial sincronizada com sucesso.',
            ],
            [
                'name' => 'Coleta Dados Abertos Recife',
                'slug' => 'coleta-dados-abertos-recife',
                'sourceName' => 'Portal de Dados Abertos Recife',
                'sourceType' => 'CSV/JSON',
                'description' => 'Consolida datasets regionais complementares para enriquecer contexto urbano e turistico.',
                'apiUrl' => 'https://dados.recife.pe.gov.br/',
                'cronExpression' => '45 2 * * *',
                'scheduledTime' => '02:45',
                'status' => BatchRoutine::STATUS_WARNING,
                'lastExecutionAt' => $this->createRoutineDate('yesterday', '02:45'),
                'nextExecutionAt' => $this->createRoutineDate('today', '02:45'),
                'recordsProcessed' => 560,
                'recordsCreated' => 12,
                'recordsUpdated' => 35,
                'recordsFailed' => 6,
                'executionTimeSeconds' => 41.3,
                'lastMessage' => 'Alguns registros vieram sem geolocalizacao.',
            ],
            [
                'name' => 'Coleta Eventos Culturais',
                'slug' => 'coleta-eventos-culturais',
                'sourceName' => 'Cadastro manual/API futura',
                'sourceType' => 'Formulario/API',
                'description' => 'Estrutura preparada para ingestao editorial e futura sincronizacao com agendas culturais externas.',
                'apiUrl' => null,
                'cronExpression' => '0 5 * * *',
                'scheduledTime' => '05:00',
                'status' => BatchRoutine::STATUS_RUNNING,
                'lastExecutionAt' => new \DateTime(),
                'nextExecutionAt' => $this->createRoutineDate('tomorrow', '05:00'),
                'recordsProcessed' => 90,
                'recordsCreated' => 5,
                'recordsUpdated' => 14,
                'recordsFailed' => 0,
                'executionTimeSeconds' => null,
                'lastMessage' => 'Execucao em andamento. Persistencia incremental sera acoplada aos comandos Symfony.',
            ],
        ];

        foreach ($items as $item) {
            $routine = new BatchRoutine();
            $routine->setName($item['name']);
            $routine->setSlug($item['slug']);
            $routine->setSourceName($item['sourceName']);
            $routine->setSourceType($item['sourceType']);
            $routine->setDescription($item['description']);
            $routine->setApiUrl($item['apiUrl']);
            $routine->setCronExpression($item['cronExpression']);
            $routine->setScheduledTime($item['scheduledTime']);
            $routine->setStatus($item['status']);
            $routine->setLastExecutionAt($item['lastExecutionAt']);
            $routine->setNextExecutionAt($item['nextExecutionAt']);
            $routine->setRecordsProcessed($item['recordsProcessed']);
            $routine->setRecordsCreated($item['recordsCreated']);
            $routine->setRecordsUpdated($item['recordsUpdated']);
            $routine->setRecordsFailed($item['recordsFailed']);
            $routine->setExecutionTimeSeconds($item['executionTimeSeconds']);
            $routine->setLastMessage($item['lastMessage']);
            $routine->setActive(true);

            $manager->persist($routine);
        }
    }

    /**
     * Creates a mutable date at a fixed day/time combination for fixtures.
     */
    private function createRoutineDate(string $dayModifier, string $time): \DateTime
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));
        $date = (new \DateTimeImmutable($dayModifier))->setTime($hours, $minutes);

        return \DateTime::createFromImmutable($date);
    }

    /**
     * Creates URL-friendly slugs for fixture records.
     */
    private function slugify(string $text): string
    {
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $slug = false === $slug ? $text : $slug;
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';

        return trim($slug, '-');
    }
}
