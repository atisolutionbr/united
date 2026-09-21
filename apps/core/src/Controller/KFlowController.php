<?php

namespace App\Controller;

use App\Entity\ErpConnection;
use App\Entity\ErpConnectionLog;
use App\Entity\Company;
use App\Entity\CompanyMembership;
use App\Entity\User;
use App\Service\ErpCatalog;
use App\Service\ErpConnectionProfile;
use App\Service\DatabaseSchemaInspector;
use App\Service\ConnectionSecretCipher;
use App\Service\CnpjWsRegistry;
use App\Service\KFlowAccess;
use App\Service\KFlowRelease;
use App\Service\ProductFiscalIntelligence;
use App\Service\ProductSanitizationService;
use App\Service\SeniorCustomerCatalog;
use App\Service\SeniorPartyCatalog;
use App\Service\SeniorProductCatalog;
use App\Service\SeniorWebServiceCatalog;
use App\Service\SeniorWebServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/unitedati')]
#[IsGranted(User::ROLE_USER)]
final class KFlowController extends AbstractController
{
    /** @var array<string, array{title: string, description: string, icon: string}> */
    private const MODULES = [
        'cliente' => ['title' => 'Cliente', 'description' => 'Gestão de cadastros e relacionamento com clientes.', 'icon' => 'people'],
        'fornecedor' => ['title' => 'Fornecedor', 'description' => 'Cadastro e gestão de fornecedores.', 'icon' => 'truck'],
        'transportador' => ['title' => 'Transportador', 'description' => 'Cadastro e gestão de transportadores.', 'icon' => 'truck-flatbed'],
        'requisicao' => ['title' => 'Requisição', 'description' => 'Criação e acompanhamento de requisições.', 'icon' => 'clipboard2-plus'],
        'aprovacao' => ['title' => 'Aprovação', 'description' => 'Central de aprovações dos fluxos do United Ati.', 'icon' => 'check2-square'],
        'aprovacao-requisicao' => ['title' => 'Aprovação Requisição', 'description' => 'Fluxo de aprovação de requisições.', 'icon' => 'check2-square'],
        'solicitacao' => ['title' => 'Solicitação', 'description' => 'Solicitações operacionais da plataforma.', 'icon' => 'send'],
        'aprovacao-solicitacao' => ['title' => 'Aprovação Solicitação', 'description' => 'Fluxo de aprovação de solicitações.', 'icon' => 'check2-circle'],
        'ordem-compra' => ['title' => 'Ordem de Compra', 'description' => 'Emissão e acompanhamento de ordens de compra.', 'icon' => 'bag-check'],
        'aprovacao-ordem-compra' => ['title' => 'Aprovação Ordem de Compra', 'description' => 'Fluxo de aprovação das ordens de compra.', 'icon' => 'patch-check'],
        'financeiro' => ['title' => 'Financeiro', 'description' => 'Consolidação financeira e rotinas de fechamento.', 'icon' => 'cash-stack'],
        'contas-pagar' => ['title' => 'Contas a Pagar', 'description' => 'Programação e controle dos pagamentos.', 'icon' => 'arrow-down-circle'],
        'aprovacao-financeira-cp' => ['title' => 'Aprovação Financeira CP', 'description' => 'Aprovação de compromissos financeiros a pagar.', 'icon' => 'shield-check'],
        'contas-receber' => ['title' => 'Contas a Receber', 'description' => 'Acompanhamento de recebíveis.', 'icon' => 'arrow-up-circle'],
        'aprovacao-financeira-cr' => ['title' => 'Aprovação Financeira CR', 'description' => 'Aprovação de registros financeiros a receber.', 'icon' => 'shield-plus'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ErpCatalog $erpCatalog,
        private readonly ErpConnectionProfile $connectionProfile,
        private readonly ConnectionSecretCipher $secretCipher,
        private readonly DatabaseSchemaInspector $databaseSchemaInspector,
        private readonly SeniorProductCatalog $seniorProductCatalog,
        private readonly SeniorCustomerCatalog $seniorCustomerCatalog,
        private readonly SeniorPartyCatalog $seniorPartyCatalog,
        private readonly \App\Service\RemoteFormCatalog $remoteFormCatalog,
        private readonly \App\Service\ProcessGateway $processGateway,
        private readonly SeniorWebServiceCatalog $seniorWebServiceCatalog,
        private readonly SeniorWebServiceManager $seniorWebServiceManager,
        private readonly ProductFiscalIntelligence $fiscalIntelligence,
        private readonly ProductSanitizationService $sanitizationService,
        private readonly KFlowAccess $access,
        private readonly KFlowRelease $release,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly CnpjWsRegistry $cnpjWsRegistry,
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        #[Autowire('%env(METABASE_URL)%')]
        private readonly string $metabaseUrl,
    ) {
    }

    #[Route('', name: 'kflow_dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        $this->requireMenu('dashboard');
        $connection = $this->activeConnection();
        $activeErp = $connection?->getErpName();
        if ($request->query->getBoolean('refresh')) $request->getSession()->save();
        $products = ['recordCount' => 0, 'products' => []];
        $customers = ['recordCount' => 0, 'missingAddressCount' => 0, 'staleCount' => 0];
        $suppliers = ['recordCount' => 0, 'missingAddressCount' => 0, 'staleCount' => 0];
        if ($connection instanceof ErpConnection) {
            $products = $this->cachedResult('products', 1, $connection, $request->query->getBoolean('refresh'));
            $customers = $this->cachedResult('customers', 1, $connection, $request->query->getBoolean('refresh'));
            $suppliers = $this->cachedResult('suppliers', 1, $connection, $request->query->getBoolean('refresh'));
        }
        $productSample = $products['products'] ?? [];
        $sanitization = $this->sanitizationService->analyze($productSample);
        $fiscalPending = count(array_filter($productSample, static fn (array $product): bool => '' === trim((string) ($product['Ncm'] ?? ''))));

        return $this->render('kflow/dashboard.html.twig', [
            'activeErp' => $activeErp,
            'connectionMethod' => $connection?->getConnectionMethod() ?? 'database',
            'metricsError' => !empty($products['error']) || !empty($customers['error']) || !empty($suppliers['error']),
            'pendingMetrics' => ['products' => $products['pending'] ?? false, 'customers' => $customers['pending'] ?? false, 'suppliers' => $suppliers['pending'] ?? false],
            'erpCount' => count($this->erpCatalog->all()),
            'metrics' => [
                'duplicates' => count($sanitization['duplicates']),
                'fiscalPending' => $fiscalPending,
                'products' => (int) ($products['recordCount'] ?? 0),
                'clientsMissingAddress' => (int) ($customers['missingAddressCount'] ?? 0),
                'clientsStale' => (int) ($customers['staleCount'] ?? 0),
                'suppliersMissingAddress' => (int) ($suppliers['missingAddressCount'] ?? 0),
                'suppliersStale' => (int) ($suppliers['staleCount'] ?? 0),
            ],
        ]);
    }

    #[Route('/about', name: 'kflow_about', methods: ['GET'])]
    public function about(): Response
    {
        $user = $this->currentUser();
        $company = $this->access->activeCompany($user);

        return $this->render('kflow/about.html.twig', [
            'release' => $this->release->current(),
            'activeCompany' => $company,
            'activeErp' => $this->activeErp(),
            'isPlatformAdmin' => $this->access->isPlatformAdmin($user),
        ]);
    }

    #[Route('/module/{module}', name: 'kflow_module', methods: ['GET'])]
    public function module(string $module): Response
    {
        if ('aprovacao' === $module) return $this->redirectToRoute('united_approvals');
        if (in_array($module, ['requisicao', 'solicitacao'], true)) return $this->redirectToRoute('united_documents', ['kind' => 'requisicao' === $module ? 'requisitions' : 'requests']);
        if (!isset(self::MODULES[$module])) {
            throw $this->createNotFoundException();
        }
        $this->requireMenu($this->menuForModule($module));

        return $this->render('kflow/module.html.twig', self::MODULES[$module]);
    }

    #[Route('/products', name: 'kflow_products', methods: ['GET'])]
    public function products(Request $request): Response
    {
        $this->requireMenu('products');
        $connection = $this->activeConnection();
        $activeErp = $connection?->getErpName();
        $productData = [
            'configured' => false,
            'products' => [],
            'error' => null,
            'sourceTable' => 'E075PRO',
            'recordCount' => 0,
            'page' => 1,
            'perPage' => 10,
            'pageCount' => 1,
            'ncmUpdateAvailable' => false,
        ];
        $mapping = [];

        if ($connection instanceof ErpConnection) {
            $mapping = $connection->getConnectionMethod() === 'database' ? $this->effectiveMapping($connection) : ($this->partyBinding($connection, 'products')['mapping'] ?? []);
            $productData = $this->cachedResult('products', max(1, $request->query->getInt('page', 1)), $connection);
        }

        $products = $productData['products'];
        $firstProduct = $products[0] ?? [];

        return $this->render('kflow/products.html.twig', [
            'activeErp' => $activeErp,
            'connectionMethod' => $connection?->getConnectionMethod() ?? 'database',
            'productData' => $productData,
            'fiscalReview' => $this->fiscalIntelligence->review($firstProduct['Ncm'] ?? null),
            'sanitization' => $this->sanitizationService->analyze($products),
            'fields' => [
                ['key' => 'CodEmp', 'label' => 'Empresa'],
                ['key' => 'CodPro', 'label' => 'Código do Produto (ERP)'],
                ['key' => 'DesPro', 'label' => 'Descrição do Produto'],
                ['key' => 'CplPro', 'label' => 'Complemento da Descrição'],
                ['key' => 'DesNFv', 'label' => 'Descrição para Nota Fiscal'],
                ['key' => 'CodFam', 'label' => 'Código da Família'],
                ['key' => 'UniMed', 'label' => 'Unidade de Medida de Vendas'],
                ['key' => 'TipPro', 'label' => 'Tipo do Produto'],
                ['key' => 'CodOri', 'label' => 'Origem (Grupo)'],
                ['key' => 'Ncm', 'label' => 'NCM', 'fiscal' => true],
                ['key' => 'CstPis', 'label' => 'CST PIS', 'fiscal' => true],
                ['key' => 'CstCofins', 'label' => 'CST COFINS', 'fiscal' => true],
                ['key' => 'CstIcms', 'label' => 'CST ICMS', 'fiscal' => true],
                ['key' => 'X_ibs_rate', 'label' => 'Alíquota IBS', 'fiscal' => true],
                ['key' => 'X_cbs_rate', 'label' => 'Alíquota CBS', 'fiscal' => true],
                ['key' => 'X_cst_ibs_cbs', 'label' => 'CST IBS/CBS', 'fiscal' => true],
                ['key' => 'X_cclass_trib', 'label' => 'cClassTrib', 'fiscal' => true],
                ['key' => 'X_tax_selective', 'label' => 'Imposto Seletivo', 'fiscal' => true],
            ],
            'extraFields' => \App\Service\IntegrationFields::forForm($connection?->getSettingsForMethod($connection->getConnectionMethod() ?? '') ?? [], $this->partyBinding($connection, 'products')['form'] ?? 'products', []),
            'mapping' => $mapping,
            'fiscalWriteAvailable' => $activeErp === 'Senior' && $connection instanceof ErpConnection && $this->seniorWebServiceManager->isProductUpdateAvailable($connection),
        ]);
    }

    #[Route('/clients', name: 'kflow_clients', methods: ['GET'])]
    public function clients(Request $request): Response
    {
        $this->requireMenu('clients');
        $connection = $this->activeConnection();
        $activeErp = $connection?->getErpName();
        $customerData = [
            'configured' => false,
            'customers' => [],
            'error' => null,
            'sourceTable' => 'E085CLI',
            'recordCount' => 0,
            'page' => 1,
            'perPage' => 10,
            'pageCount' => 1,
            'missingAddressCount' => 0,
            'staleCount' => 0,
        ];
        if ($connection instanceof ErpConnection) {
            $customerData = $this->cachedResult('customers', max(1, $request->query->getInt('page', 1)), $connection);
        }

        return $this->render('kflow/clients.html.twig', [
            'activeErp' => $activeErp,
            'connectionMethod' => $connection?->getConnectionMethod() ?? 'database',
            'customerData' => $customerData,
            'extraFields' => \App\Service\IntegrationFields::forForm($connection?->getSettingsForMethod($connection->getConnectionMethod() ?? '') ?? [], $this->partyBinding($connection, 'customers')['form'] ?? 'customers', []),
        ]);
    }

    #[Route('/suppliers', name: 'kflow_suppliers', methods: ['GET'])]
    public function suppliers(Request $request): Response
    {
        return $this->partyPage($request, 'suppliers', 'Fornecedores', 'fornecedor', 'truck', 'Fornecedor');
    }

    #[Route('/carriers', name: 'kflow_carriers', methods: ['GET'])]
    public function carriers(Request $request): Response
    {
        return $this->partyPage($request, 'carriers', 'Transportadoras', 'transportador', 'truck-flatbed', 'Transportadora');
    }

    #[Route('/products/senior/fiscal', name: 'kflow_senior_product_fiscal', methods: ['POST'])]
    public function updateSeniorProductFiscal(Request $request): Response
    {
        $this->requireMenu('products');
        $connection = $this->activeConnection();
        if ('Senior' !== $connection?->getErpName() || !$this->isCsrfTokenValid('senior-product-fiscal', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $result = $this->seniorWebServiceManager->updateProductFiscal(
            $connection,
            [
                'company' => trim((string) $request->request->get('company')),
                'product_code' => trim((string) $request->request->get('product_code')),
                'product_name' => (string) $request->request->get('product_name'),
                'unit' => (string) $request->request->get('unit'),
                'origin_code' => (string) $request->request->get('origin_code'),
                'family' => (string) $request->request->get('family'),
                'ncm' => (string) $request->request->get('ncm'),
                'cst_pis' => (string) $request->request->get('cst_pis'),
                'cst_cofins' => (string) $request->request->get('cst_cofins'),
                'cst_icms' => (string) $request->request->get('cst_icms'),
            ],
        );
        $this->addFlash($result['updated'] ? 'success' : 'warning', $result['message']);

        return $this->redirectToRoute('kflow_products');
    }

    #[Route('/companies', name: 'kflow_companies', methods: ['GET'])]
    public function companies(): Response
    {
        $this->requirePlatformAdmin();
        return $this->render('kflow/companies.html.twig', ['companies' => $this->access->companies($this->currentUser()), 'activeCompany' => $this->currentCompany()]);
    }

    #[Route('/companies', name: 'kflow_companies_create', methods: ['POST'])]
    public function createCompany(Request $request): Response
    {
        $this->requirePlatformAdmin();
        if (!$this->isCsrfTokenValid('create-company', (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
        $name = mb_substr(trim((string) $request->request->get('name')), 0, 160);
        if ('' === $name) { $this->addFlash('warning', 'Informe o nome da empresa.'); return $this->redirectToRoute('kflow_companies'); }
        $company = (new Company($name))->setLegalName(mb_substr(trim((string) $request->request->get('legal_name')), 0, 220) ?: null)->setDocument(mb_substr(trim((string) $request->request->get('document')), 0, 24) ?: null)->setHoldingName(mb_substr(trim((string) $request->request->get('holding_name')), 0, 160) ?: null);
        $this->entityManager->persist($company);
        $this->entityManager->flush();
        $request->getSession()->set('kflow_company_id', $company->getId());
        $this->addFlash('success', sprintf('%s criada e selecionada.', $company->getName()));
        return $this->redirectToRoute('kflow_erp_index');
    }

    #[Route('/companies/{company}/select', name: 'kflow_company_select', methods: ['POST'])]
    public function selectCompany(Company $company, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('select-company-'.$company->getId(), (string) $request->request->get('_token')) || (!$this->access->isPlatformAdmin($this->currentUser()) && !in_array($company, $this->access->companies($this->currentUser()), true))) throw $this->createAccessDeniedException();
        $request->getSession()->set('kflow_company_id', $company->getId());
        return $this->redirect((string) $request->request->get('redirect', $this->generateUrl('kflow_dashboard')));
    }

    #[Route('/companies/cnpj/{document}', name: 'kflow_company_cnpj', methods: ['GET'])]
    public function companyByCnpj(string $document): JsonResponse
    {
        $this->requirePlatformAdmin();
        $result = $this->cnpjWsRegistry->lookup($document);
        if (!$result['ok']) return $this->json($result, false === ($result['supported'] ?? true) ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_BAD_GATEWAY);
        $registry = $result['registry'];

        return $this->json([
            'ok' => true,
            'legalName' => $registry['legalName'],
            'name' => $registry['tradeName'] ?: $registry['legalName'],
            'address' => trim(implode(', ', array_filter([$registry['address'], $registry['city'], $registry['state']]))),
            'phone' => $registry['phone'],
            'email' => $registry['email'],
        ]);
    }

    #[Route('/registration/cnpj/{document}', name: 'kflow_registration_cnpj', methods: ['GET'])]
    public function registrationByCnpj(string $document): JsonResponse
    {
        $this->requireRegistrationReviewAccess();
        $result = $this->cnpjWsRegistry->lookup($document);

        return $this->json($result, $result['ok'] ? Response::HTTP_OK : (false === ($result['supported'] ?? true) ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_BAD_GATEWAY));
    }

    #[Route('/users', name: 'kflow_users', methods: ['GET'])]
    public function users(): Response
    {
        $this->requirePlatformAdmin();
        return $this->render('kflow/users.html.twig', ['users' => $this->entityManager->getRepository(User::class)->findBy([], ['username' => 'ASC']), 'companies' => $this->access->companies($this->currentUser()), 'menuOptions' => $this->access->menuOptions()]);
    }

    #[Route('/users', name: 'kflow_users_save', methods: ['POST'])]
    public function saveUser(Request $request): Response
    {
        $this->requirePlatformAdmin();
        if (!$this->isCsrfTokenValid('save-platform-user', (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
        $username = mb_strtolower(mb_substr(trim((string) $request->request->get('username')), 0, 50));
        $email = mb_substr(trim((string) $request->request->get('email')), 0, 180);
        $name = mb_substr(trim((string) $request->request->get('full_name')), 0, 191);
        if ('' === $username || '' === $email || '' === $name) { $this->addFlash('warning', 'Preencha usuário, nome e e-mail.'); return $this->redirectToRoute('kflow_users'); }
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        if (!$user instanceof User) { $user = new User(); $user->setUsername($username); $this->entityManager->persist($user); }
        $user->setFullName($name); $user->setEmail($email); $user->setRoles([User::ROLE_USER]);
        $password = (string) $request->request->get('password');
        if ('' !== $password) { if (mb_strlen($password) < 8) { $this->addFlash('warning', 'A senha deve ter ao menos 8 caracteres.'); return $this->redirectToRoute('kflow_users'); } $user->setPassword($this->passwordHasher->hashPassword($user, $password)); }
        $companyIds = array_map('intval', is_array($request->request->all('companies')) ? $request->request->all('companies') : []);
        $permissions = array_values(array_intersect(array_keys($this->access->menuOptions()), array_map('strval', is_array($request->request->all('permissions')) ? $request->request->all('permissions') : [])));
        foreach ($this->access->companies($this->currentUser()) as $company) {
            $membership = $this->entityManager->getRepository(CompanyMembership::class)->findOneBy(['company' => $company, 'user' => $user]);
            if (in_array($company->getId(), $companyIds, true)) { if (!$membership instanceof CompanyMembership) { $membership = new CompanyMembership($company, $user); $this->entityManager->persist($membership); } $membership->setAccessOrigin('platform')->setMenuPermissions($permissions)->setIsActive(true); }
            elseif ($membership instanceof CompanyMembership && 'platform' === $membership->getAccessOrigin()) $membership->setIsActive(false);
        }
        $this->entityManager->flush(); $this->addFlash('success', 'Usuário e permissões salvos.'); return $this->redirectToRoute('kflow_users');
    }

    #[Route('/erp', name: 'kflow_erp_index', methods: ['GET'])]
    public function erps(): Response
    {
        $this->requireMenu('connections');
        $connections = [];
        foreach ($this->entityManager->getRepository(ErpConnection::class)->findBy(['company' => $this->currentCompany()]) as $connection) {
            $connections[$connection->getErpName()] = $connection;
        }
        return $this->render('kflow/erp/index.html.twig', [
            'erps' => $this->erpCatalog->all(),
            'activeErp' => $this->activeErp(),
            'connections' => $connections,
        ]);
    }

    #[Route('/erp/{erp}/select', name: 'kflow_erp_select', methods: ['POST'])]
    public function selectErp(string $erp, Request $request): Response
    {
        $this->requireMenu('connections');
        if (!$this->erpCatalog->supports($erp) || !$this->isCsrfTokenValid('select-erp-'.$erp, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $company = $this->currentCompany();
        $connection = $this->connectionFor($erp);
        $isNewConnection = !$connection instanceof ErpConnection;
        if ($isNewConnection) {
            $connection = new ErpConnection($company, $erp);
            $this->entityManager->persist($connection);
        }

        if (null === $connection->getConnectionMethod()) {
            $connection
                ->setConnectionMethod(ErpConnection::METHOD_DATABASE)
                ->setSettingsForMethod(ErpConnection::METHOD_DATABASE, array_replace($this->connectionProfile->defaultSettings($erp, ErpConnection::METHOD_DATABASE), $connection->getSettingsForMethod(ErpConnection::METHOD_DATABASE)))
                ->setProductMapping($this->connectionProfile->defaultProductMapping($erp));
        }

        if ($isNewConnection) $connection->setIsActive(true);
        $this->entityManager->flush();
        if ($connection->isActive()) $request->getSession()->set('kflow_selected_erp', $erp);

        return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp]);
    }

    #[Route('/erp/{erp}/active', name: 'kflow_erp_active', methods: ['POST'])]
    public function setErpActive(string $erp, Request $request): Response
    {
        $this->requireMenu('connections');
        if (!$this->erpCatalog->supports($erp) || !$this->isCsrfTokenValid('erp-active-'.$erp, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $connection = $this->connectionFor($erp);
        if (!$connection instanceof ErpConnection) {
            $connection = new ErpConnection($this->currentCompany(), $erp);
            $connection
                ->setConnectionMethod(ErpConnection::METHOD_DATABASE)
                ->setSettingsForMethod(ErpConnection::METHOD_DATABASE, $this->connectionProfile->defaultSettings($erp, ErpConnection::METHOD_DATABASE));
            $this->entityManager->persist($connection);
        }
        $active = '1' === (string) $request->request->get('active');
        $connection->setIsActive($active);
        $this->entityManager->flush();
        if (!$active && $request->getSession()->get('kflow_selected_erp') === $erp) $request->getSession()->remove('kflow_selected_erp');
        $this->addFlash('success', sprintf('%s marcado como %s.', $erp, $active ? 'ativo' : 'inativo'));

        return $this->redirectToRoute('kflow_erp_index');
    }

    #[Route('/erp/{erp}/connect', name: 'kflow_erp_connect', methods: ['GET'])]
    public function connectErp(string $erp, Request $request): Response
    {
        $this->requireMenu('connections');
        if (!$this->erpCatalog->supports($erp)) {
            throw $this->createNotFoundException();
        }

        $connection = $this->connectionFor($erp);
        $method = (string) $request->query->get('method', $connection?->getConnectionMethod() ?? ErpConnection::METHOD_DATABASE);
        if (!array_key_exists($method, $this->connectionProfile->connectionMethods())) {
            $method = ErpConnection::METHOD_DATABASE;
        }

        $settings = $this->settingsForConnection($erp, $method, $connection);
        $mapping = array_replace($this->connectionProfile->defaultProductMapping($erp), $connection?->getProductMapping() ?? []);
        $databaseStep = (string) $request->query->get('step', 'connection');
        $databaseStep = in_array($databaseStep, ['connection', 'table', 'mapping', 'users'], true) ? $databaseStep : 'connection';
        $integrationStep = (string) $request->query->get('step', 'connection');
        $integrationStep = in_array($integrationStep, ['connection', 'services', 'mapping', 'users'], true) ? $integrationStep : 'connection';
        $databaseForms = $this->connectionProfile->databaseForms();
        $configuredForms = $this->configuredForms($settings, $databaseForms);
        $defaultForm = $configuredForms[0] ?? ['id' => 'products', 'template' => 'products', 'label' => 'Produtos'];
        $selectedForm = (string) $request->query->get('form', $defaultForm['id']);
        $selectedFormConfig = $this->formConfig($configuredForms, $selectedForm) ?? $defaultForm;
        $selectedForm = $selectedFormConfig['id'];
        $selectedFormDefinition = $databaseForms[$selectedFormConfig['template']];
        $allFormFields = \App\Service\IntegrationFields::forForm(array_replace($settings, ['hidden_fields' => array_diff_key($settings['hidden_fields'] ?? [], [$selectedForm => true])]), $selectedForm, $selectedFormDefinition['fields']);
        $selectedFormDefinition['fields'] = \App\Service\IntegrationFields::forForm($settings, $selectedForm, $selectedFormDefinition['fields']);
        $bindings = is_array($settings['bindings'] ?? null) ? $settings['bindings'] : [];
        $webServices = $this->webServices($settings);
        $binding = is_array($bindings[$selectedForm] ?? null) ? $bindings[$selectedForm] : [];
        $selectedTable = (string) ($binding['table'] ?? ('products' === $selectedForm ? ($settings['table'] ?? '') : ''));
        if ('products' === $selectedForm && !isset($bindings['products']) && '' !== $selectedTable) $bindings['products'] = ['table' => $selectedTable, 'mapping' => $mapping];
        $tables = ['tables' => [], 'error' => null];
        $columns = ['columns' => [], 'error' => null];
        // Listing every ERP table is expensive and is only required while choosing
        // a table. Never let that discovery block the De/Para of an already bound
        // form: there we query the one selected table directly.
        if (ErpConnection::METHOD_DATABASE === $method && 'table' === $databaseStep) {
            $tables = $this->databaseSchemaInspector->tables($settings);
        }
        if (ErpConnection::METHOD_DATABASE === $method && 'mapping' === $databaseStep && '' !== $selectedTable) {
            $columns = $this->databaseSchemaInspector->columns($settings, $selectedTable);
        }
        // A form must remain configurable even if the ERP is temporarily offline.
        // Persist the discovered schema beside the binding and use that catalogue as
        // a fallback; it contains metadata only, never ERP records or credentials.
        $databaseColumnsCached = false;
        if (ErpConnection::METHOD_DATABASE === $method && 'mapping' === $databaseStep && '' !== $selectedTable) {
            $cachedColumns = array_values(array_filter(
                is_array($binding['schema_columns'] ?? null) ? $binding['schema_columns'] : [],
                static fn (mixed $column): bool => is_string($column) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $column) === 1,
            ));
            if ([] !== $columns['columns']) {
                $discoveredColumns = array_values(array_unique(array_map('strval', $columns['columns'])));
                if ($connection instanceof ErpConnection && $cachedColumns !== $discoveredColumns) {
                    $settings['bindings'][$selectedForm]['schema_columns'] = $discoveredColumns;
                    $connection->setSettingsForMethod($method, $settings);
                    $this->entityManager->flush();
                    $bindings = $settings['bindings'];
                    $binding = $bindings[$selectedForm];
                }
            } elseif ([] !== $cachedColumns) {
                $columns['columns'] = $cachedColumns;
                $databaseColumnsCached = true;
            }
        }
        $userAccess = is_array($settings['user_access'] ?? null) ? $settings['user_access'] : [];
        $userFieldDefinitions = [
            'password' => ['label' => 'Campo de senha', 'hint' => 'Senha da origem; nunca exibida na lista. Se indisponível, informe uma senha manual.'],
            'identifier' => ['label' => 'Campo de usuário', 'hint' => 'Identificador usado para login.'],
            'name' => ['label' => 'Campo de nome', 'hint' => 'Nome exibido no United Ati.'],
            'email' => ['label' => 'Campo de e-mail', 'hint' => 'E-mail do usuário.'],
        ];
        foreach ($userAccess['custom_fields'] ?? [] as $key => $field) {
            if (preg_match('/^extra_[a-f0-9]{12}$/D', (string) $key) && is_array($field)) $userFieldDefinitions[$key] = ['label' => (string) ($field['label'] ?? 'Campo adicional'), 'hint' => 'Campo adicional de usuário.'];
        }
        $allUserFields = $userFieldDefinitions;
        foreach ($userAccess['hidden_fields'] ?? [] as $field) unset($userFieldDefinitions[$field]);
        $userTable = (string) $request->query->get('user_table', $userAccess['table'] ?? '');
        $userAccess['table'] = $userTable;
        $userColumns = ['columns' => [], 'error' => null];
        $userRows = ['rows' => [], 'error' => null];
        if (ErpConnection::METHOD_DATABASE === $method && 'users' === $databaseStep && '' !== $userTable) {
            $userColumns = $this->databaseSchemaInspector->columns($settings, $userTable);
            $configuredUserColumns = array_values(array_filter(array_map('strval', $userAccess['mapping'] ?? [])));
            if ($userTable === ($settings['user_access']['table'] ?? '') && [] !== $configuredUserColumns) $userRows = $this->databaseSchemaInspector->rows($settings, $userTable, $configuredUserColumns);
        }

        if ($method !== 'database' && $integrationStep === 'users' && $connection && !empty($userAccess['endpoint'])) {
            try { $userRows['rows'] = $this->processGateway->readUsers($connection, $method, $userAccess); }
            catch (\InvalidArgumentException $e) { $userRows['error'] = $e->getMessage(); }
            catch (\Throwable $e) { $userRows['error'] = 'Não foi possível consultar usuários via '.($method === 'api' ? 'API' : 'WebService').'. Verifique a operação, a coleção e as credenciais.'; }
        }
        foreach ($bindings as $saved) if (!empty($saved['table'])) $tables['tables'][] = $saved['table'];
        if ($userTable !== '') $tables['tables'][] = $userTable;
        $tables['tables'] = array_values(array_unique($tables['tables']));
        if ($columns['error']) $columns['columns'] = array_values(array_unique([...$columns['columns'], ...array_filter($binding['mapping'] ?? [])]));
        if ($userColumns['error']) $userColumns['columns'] = array_values(array_unique([...$userColumns['columns'], ...array_filter($userAccess['mapping'] ?? [])]));
        return $this->render('kflow/erp/connect.html.twig', [
            'erp' => $erp,
            'method' => $method,
            'methods' => $this->connectionProfile->connectionMethods(),
            'databaseDrivers' => $this->connectionProfile->databaseDrivers(),
            'settings' => $settings,
            'productFields' => $this->connectionProfile->productFields(),
            'mapping' => $mapping,
            'availableColumns' => $this->connectionProfile->availableColumns($erp, $method),
            'databaseStep' => $databaseStep,
            'databaseForms' => $databaseForms,
            'configuredForms' => $configuredForms,
            'bindings' => $bindings,
            'selectedForm' => $selectedForm,
            'selectedFormDefinition' => $selectedFormDefinition,
            'allFormFields' => $allFormFields,
            'hiddenFields' => array_flip($settings['hidden_fields'][$selectedForm] ?? []),
            'selectedFormLabel' => $selectedFormConfig['label'],
            'selectedTable' => $selectedTable,
            'bindingMapping' => is_array($binding['mapping'] ?? null) ? $binding['mapping'] : ('products' === $selectedForm ? $mapping : []),
            'databaseTables' => $tables['tables'],
            'databaseTablesError' => $tables['error'],
            'databaseColumns' => $columns['columns'],
            'databaseColumnsError' => $columns['error'],
            'databaseColumnsCached' => $databaseColumnsCached,
            'integrationStep' => $integrationStep,
            'payloadMapping' => is_array(($settings['form_mappings'][$selectedForm] ?? null)) ? $settings['form_mappings'][$selectedForm] : [],
            'webServices' => $webServices,
            'selectedWebService' => (string) (($settings['form_services'][$selectedForm] ?? '') ?: ''),
            'isActive' => $connection?->isActive() ?? false,
            'seniorDatabaseAvailable' => ErpConnection::METHOD_DATABASE === $method && $this->databaseSchemaInspector->isConfigured($settings),
            'seniorWebServices' => 'Senior' === $erp ? $this->seniorWebServiceCatalog->all() : [],
            'webServiceLogs' => $connection instanceof ErpConnection
                ? $this->entityManager->getRepository(ErpConnectionLog::class)->findBy(['connection' => $connection], ['createdAt' => 'DESC'], 6)
                : [],
            'userAccess' => $userAccess,
            'userFields' => $userFieldDefinitions,
            'allUserFields' => $allUserFields,
            'userTables' => ErpConnection::METHOD_DATABASE === $method && $databaseStep === 'table' ? $tables['tables'] : [],
            'userColumns' => $userColumns['columns'],
            'userColumnsError' => $userColumns['error'],
            'erpUsers' => $this->normalizeErpUsers($userRows['rows'], $userAccess),
            'erpUsersError' => $userRows['error'],
            'companyMemberships' => $this->membershipsForCompany(),
            'menuOptions' => $this->access->menuOptions(),
        ]);
    }

    #[Route('/erp/{erp}/connect', name: 'kflow_erp_connect_save', methods: ['POST'])]
    public function saveErpConnection(string $erp, Request $request): Response
    {
        $this->requireMenu('connections');
        if (!$this->erpCatalog->supports($erp) || !$this->isCsrfTokenValid('configure-erp-'.$erp, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $method = (string) $request->request->get('method');
        if (!array_key_exists($method, $this->connectionProfile->connectionMethods())) {
            throw $this->createNotFoundException();
        }

        $company = $this->currentCompany();
        $connection = $this->connectionFor($erp);
        $isNewConnection = !$connection instanceof ErpConnection;
        if ($isNewConnection) {
            $connection = new ErpConnection($company, $erp);
            $this->entityManager->persist($connection);
        }

        $settingsInput = $request->request->all('settings');
        if ((string) $request->request->get('company_id') !== (string) $company->getId()) throw $this->createAccessDeniedException('A empresa mudou. Reabra a conexão da empresa desejada antes de salvar.');
        $existingSettings = $this->settingsForConnection($erp, $method, $connection);
        $settings = $this->connectionProfile->settingsFromInput(
            $method,
            is_array($settingsInput) ? $settingsInput : [],
            $existingSettings,
        );
        $mapping = $connection->getProductMapping();
        if (ErpConnection::METHOD_DATABASE === $method) {
            $mappingInput = $request->request->all('mapping');
            if (is_array($mappingInput) && [] !== $mappingInput) {
                $mapping = $this->connectionProfile->mappingFromInput(
                    $mappingInput,
                    $this->connectionProfile->availableColumns($erp, $method),
                );
            }

            // Saving configuration must not wait for, or depend on, the ERP.
            // A previous endpoint's validation is not valid for these settings.
            unset($settings['connection_validation']);
        }

        $connection
            ->setConnectionMethod($method)
            ->setSettingsForMethod($method, $settings)
            ->setProductMapping($mapping)
            ->setIsActive($isNewConnection || $connection->isActive())
            ->markConfigured();
        $this->entityManager->flush();
        if ($connection->isActive()) $request->getSession()->set('kflow_selected_erp', $erp);

        $this->addFlash('success', sprintf('Configuração de %s salva. Use Testar conexão para verificar o acesso ao ERP.', $erp));

        $nextStep = 'connection';
        return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp, 'method' => $method, 'step' => $nextStep]);
    }

    #[Route('/erp/{erp}/database/test', name: 'kflow_erp_database_test', methods: ['POST'])]
    public function testDatabaseConnection(string $erp, Request $request): JsonResponse
    {
        $this->requireMenu('connections');
        if (!$this->erpCatalog->supports($erp) || !$this->isCsrfTokenValid('test-erp-database-'.$erp, (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'message' => 'Não foi possível validar a solicitação.'], Response::HTTP_FORBIDDEN);
        }

        $connection = $this->connectionFor($erp);
        $input = $request->request->all('settings');
        $settings = $this->connectionProfile->settingsFromInput(
            ErpConnection::METHOD_DATABASE,
            is_array($input) ? $input : [],
            $this->settingsForConnection($erp, ErpConnection::METHOD_DATABASE, $connection),
        );
        $result = $this->databaseSchemaInspector->test($settings);

        return $this->json(['ok' => $result['connected'], 'message' => $result['message']], $result['connected'] ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Route('/erp/{erp}/database/binding', name: 'kflow_erp_database_binding', methods: ['POST'])]
    public function saveDatabaseBinding(string $erp, Request $request): Response
    {
        $this->requireMenu('connections');
        if (!$this->erpCatalog->supports($erp) || !$this->isCsrfTokenValid('database-binding-'.$erp, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $form = (string) $request->request->get('form');
        $forms = $this->connectionProfile->databaseForms();
        $connection = $this->connectionFor($erp);
        if (!$connection instanceof ErpConnection) {
            $this->addFlash('warning', 'Salve os dados da conexão antes de configurar o vínculo.');
            return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp, 'method' => ErpConnection::METHOD_DATABASE]);
        }
        $configuredForm = $this->formConfig($this->configuredForms($connection->getSettingsForMethod(ErpConnection::METHOD_DATABASE), $forms), $form);
        if (null === $configuredForm) {
            throw $this->createNotFoundException();
        }

        $settings = $connection->getSettingsForMethod(ErpConnection::METHOD_DATABASE);
        $bindings = is_array($settings['bindings'] ?? null) ? $settings['bindings'] : [];
        $table = trim((string) $request->request->get('table'));
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/D', $table)) {
            $this->addFlash('warning','Selecione uma tabela válida. O vínculo anterior foi preservado.');
            return $this->redirectToRoute('kflow_erp_connect',['erp'=>$erp,'method'=>'database','step'=>'table','form'=>$form]);
        }
        $inspection = $this->databaseSchemaInspector->columns($settings,$table);
        $mappingInput = $request->request->all('mapping');
        $columns = $inspection['columns'];
        // Stored selections remain editable while the ERP is temporarily offline.
        foreach ($mappingInput as $source) if (is_string($source) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D',$source)) $columns[]=$source;
        $columns=array_values(array_unique($columns));
        $fields = \App\Service\IntegrationFields::forForm($settings, $form, $forms[$configuredForm['template']]['fields']);
        $previous = ($bindings[$form]['table'] ?? '') === $table ? ($bindings[$form]['mapping'] ?? []) : [];
        if ('products' === $form && !isset($bindings[$form]) && ($settings['table'] ?? '') === $table) $previous = $connection->getProductMapping();
        $mapping = $request->request->has('mapping')
            ? $this->connectionProfile->mappingForFields(array_keys($fields), $mappingInput, $columns)
            : $previous;
        $previousSchema = is_array($bindings[$form]['schema_columns'] ?? null) ? $bindings[$form]['schema_columns'] : [];
        $bindings[$form] = [
            'table' => $table,
            'mapping' => $mapping,
            'validation' => $inspection['error'] ? 'pending' : 'validated',
            // Keep the most recently confirmed structure. This lets an operator
            // edit mappings while an ERP server, VPN or service is restarting.
            'schema_columns' => [] !== $inspection['columns']
                ? array_values(array_unique(array_map('strval', $inspection['columns'])))
                : $previousSchema,
        ];
        $settings['bindings'] = $bindings;
        $settings['table'] = 'products' === $form ? $table : (string) ($settings['table'] ?? '');
        $connection->setSettingsForMethod(ErpConnection::METHOD_DATABASE, $settings);
        if ('products' === $form) {
            $connection->setProductMapping($mapping);
        }
        $this->entityManager->flush();
        $this->addFlash($inspection['error'] ? 'warning' : 'success', sprintf('Vínculo de %s salvo para a tabela %s.', $configuredForm['label'], $table).($inspection['error'] ? ' A origem não respondeu; validação pendente, sem apagar o mapeamento.' : ''));

        return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp, 'method' => ErpConnection::METHOD_DATABASE, 'step' => 'mapping', 'form' => $form]);
    }

    #[Route('/erp/{erp}/payload-mapping', name: 'kflow_erp_payload_mapping', methods: ['POST'])]
    public function savePayloadMapping(string $erp, Request $request): Response
    {
        $this->requireMenu('connections');
        $method = (string) $request->request->get('method');
        if (!$this->erpCatalog->supports($erp)
            || !in_array($method, [ErpConnection::METHOD_API, ErpConnection::METHOD_WEBSERVICE], true)
            || !$this->isCsrfTokenValid('payload-mapping-'.$erp.'-'.$method, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $form = (string) $request->request->get('form');
        $forms = $this->connectionProfile->databaseForms();
        $connection = $this->connectionFor($erp);
        if (!$connection instanceof ErpConnection) {
            throw $this->createNotFoundException();
        }
        $configuredForm = $this->formConfig($this->configuredForms($connection->getSettingsForMethod($method), $forms), $form);
        if (null === $configuredForm) {
            throw $this->createNotFoundException();
        }

        $input = $request->request->all('mapping');
        $mapping = [];
        foreach (array_keys(\App\Service\IntegrationFields::forForm($connection->getSettingsForMethod($method), $form, $forms[$configuredForm['template']]['fields'])) as $field) {
            $source = trim((string) (is_array($input) ? ($input[$field] ?? '') : ''));
            $mapping[$field] = preg_match('/^[A-Za-z0-9_.\[\]-]{0,160}$/', $source) ? $source : '';
        }

        $settings = $connection->getSettingsForMethod($method);
        if (ErpConnection::METHOD_WEBSERVICE === $method) {
            $serviceId = (string) $request->request->get('webservice_id');
            if (!in_array($serviceId, array_column($this->webServices($settings), 'id'), true)) {
                $this->addFlash('warning', 'Selecione um WebService cadastrado para vincular o formulário.');
                return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp, 'method' => $method, 'step' => 'mapping', 'form' => $form]);
            }
        }
        if ($request->request->has('form_query')) {
            $query = $request->request->all('form_query');
            $query = array_map(static fn ($value) => trim((string) $value), array_intersect_key($query, array_flip(['operation', 'items_path', 'total_path', 'page_param', 'size_param', 'parameters', 'search_param', 'soap_user_path', 'soap_password_path'])));
            try {
                if (!empty($query['operation'])) {
                    if ($method === 'api' && (!str_starts_with($query['operation'], '/') || str_starts_with($query['operation'], '//') || str_contains($query['operation'], '://'))) throw new \InvalidArgumentException('A rota de consulta API deve iniciar com /.');
                    if ($method === 'webservice' && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $query['operation'])) throw new \InvalidArgumentException('Informe o nome da operação de leitura SOAP.');
                    foreach (['items_path', 'total_path', 'page_param', 'size_param'] as $key) {
                        if (empty($query[$key])) throw new \InvalidArgumentException('Preencha coleção, total e parâmetros de paginação da consulta.');
                        \App\Service\ProcessGateway::readPath([], $query[$key]);
                    }
                    $parameters = json_decode($query['parameters'] ?: '{}', true, 32, JSON_THROW_ON_ERROR);
                    if (!is_array($parameters) || ($parameters && array_is_list($parameters))) throw new \InvalidArgumentException('Parâmetros fixos devem ser um objeto JSON.');
                }
                $settings['form_queries'][$form] = $query;
            } catch (\InvalidArgumentException|\JsonException $e) {
                $this->addFlash('warning', $e instanceof \JsonException ? 'Parâmetros fixos: JSON inválido.' : $e->getMessage());
                return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp, 'method' => $method, 'step' => 'mapping', 'form' => $form]);
            }
        }
        $formMappings = is_array($settings['form_mappings'] ?? null) ? $settings['form_mappings'] : [];
        $formMappings[$form] = $mapping;
        $settings['form_mappings'] = $formMappings;
        if (ErpConnection::METHOD_WEBSERVICE === $method) {
            $settings['form_services'][$form] = (string) $request->request->get('webservice_id');
        }
        $connection->setSettingsForMethod($method, $settings);
        $this->entityManager->flush();
        $this->addFlash('success', sprintf('De/Para de %s salvo para %s.', $configuredForm['label'], $this->connectionProfile->connectionMethods()[$method]));

        return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp, 'method' => $method, 'step' => 'mapping', 'form' => $form]);
    }

    #[Route('/erp/{erp}/users', name: 'kflow_erp_users_save', methods: ['POST'])]
    public function saveErpUsers(string $erp, Request $request): Response
    {
        $this->requireMenu('connections');
        if ((string) $request->request->get('company_id') !== (string) $this->currentCompany()->getId()) throw $this->createAccessDeniedException('A empresa mudou. Reabra a tela de usuários antes de salvar.');
        $method = (string) $request->request->get('method');
        if (!$this->erpCatalog->supports($erp) || !array_key_exists($method, $this->connectionProfile->connectionMethods()) || !$this->isCsrfTokenValid('erp-users-'.$erp.'-'.$method, (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
        $connection = $this->connectionFor($erp);
        if (!$connection instanceof ErpConnection) { $this->addFlash('warning', 'Salve a conexão antes de vincular usuários.'); return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp, 'method' => $method]); }
        $settings = $connection->getSettingsForMethod($method);
        $accessInput = $request->request->all('user_access');
        $accessInput = is_array($accessInput) ? $accessInput : [];
        $customFields = is_array($settings['user_access']['custom_fields'] ?? null) ? $settings['user_access']['custom_fields'] : [];
        $newField = mb_substr(trim((string) ($accessInput['new_field_label'] ?? '')), 0, 80);
        if ('' !== $newField && count($customFields) < 50) $customFields['extra_'.bin2hex(random_bytes(6))] = ['label' => $newField];
        foreach ((array) ($accessInput['remove_fields'] ?? []) as $field) if (preg_match('/^extra_[a-f0-9]{12}$/D', (string) $field)) unset($customFields[$field]);
        $hiddenFields = array_values(array_unique(array_filter(array_map('strval', (array) ($accessInput['hidden_fields'] ?? [])), static fn (string $field): bool => in_array($field, array_merge(['identifier', 'name', 'email'], array_keys($customFields)), true))));
        $mapping = [];
        foreach (array_merge(['identifier', 'name', 'email', 'password'], array_keys($customFields)) as $field) {
            $source = (string) ($accessInput['mapping'][$field] ?? $settings['user_access']['mapping'][$field] ?? '');
            $mapping[$field] = preg_match('/^[A-Za-z_][A-Za-z0-9_.$\[\]-]{0,159}$/', $source) ? $source : '';
        }
        $settings['user_access'] = [
            'password_format' => in_array($accessInput['password_format'] ?? '', ['manual', 'plain', 'php_hash'], true) ? $accessInput['password_format'] : ($settings['user_access']['password_format'] ?? 'manual'),
            'table' => preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', (string) ($accessInput['table'] ?? '')) ? (string) $accessInput['table'] : '',
            'endpoint' => mb_substr(trim((string) ($accessInput['endpoint'] ?? '')), 0, 240),
            'items_path' => mb_substr(trim((string) ($accessInput['items_path'] ?? '')), 0, 160),
            'mapping' => $mapping,
            'custom_fields' => $customFields,
            'hidden_fields' => $hiddenFields,
        ];
        $connection->setSettingsForMethod($method, $settings);
        $selected = array_values(array_filter(array_map('strval', is_array($request->request->all('erp_users')) ? $request->request->all('erp_users') : [])));
        $this->entityManager->flush();
        $permissions = array_values(array_intersect(array_keys($this->access->menuOptions()), array_map('strval', is_array($request->request->all('permissions')) ? $request->request->all('permissions') : [])));
        try { $directory = $selected ? $this->erpUserDirectory($erp, $method, $settings) : []; }
        catch (\Throwable) {
            $this->addFlash('warning', 'Vínculo de usuários salvo nesta empresa. Não foi possível consultar o ERP para importar as contas.');
            return $this->redirectToRoute('kflow_erp_connect', ['erp'=>$erp, 'method'=>$method, 'step'=>'users']);
        }
        $selectedUsers = array_filter($directory, static fn (array $user): bool => in_array($user['identifier'], $selected, true));
        $initialPassword = (string) $request->request->get('initial_password');
        $company = $this->currentCompany();
        $created = 0;
        $linked = 0;
        $missingPassword = 0;
        $manualPasswords = $request->request->all('user_passwords');
        foreach ($selectedUsers as $erpUser) {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $erpUser['identifier']]);
            if (!$user instanceof User) {
                try {
                    $hash = \App\Service\ErpUserPassword::hash($erpUser['_password'] ?? '', $settings['user_access']['password_format'], (string) (($manualPasswords[$erpUser['identifier']] ?? '') ?: $initialPassword));
                } catch (\InvalidArgumentException) { ++$missingPassword; continue; }
                $user = new User();
                $user->setUsername($erpUser['identifier']);
                $user->setFullName($erpUser['name'] ?: $erpUser['identifier']);
                $user->setEmail($erpUser['email'] ?: sprintf('%s@kflow.local', strtolower(preg_replace('/[^a-z0-9]+/i', '.', $erpUser['identifier']) ?: 'usuario')));
                $user->setPassword($hash);
                $this->entityManager->persist($user);
                ++$created;
            }
            $membership = $this->entityManager->getRepository(CompanyMembership::class)->findOneBy(['company' => $company, 'user' => $user]);
            if (!$membership instanceof CompanyMembership) { $membership = new CompanyMembership($company, $user); $this->entityManager->persist($membership); }
            $membership->setErpIdentity($erpUser['identifier'], $erpUser['name'], $erpUser['email'])->setAccessOrigin('erp')->setMenuPermissions($permissions)->setIsActive(true);
            ++$linked;
        }
        $this->entityManager->flush();
        $this->addFlash('success', sprintf('Vínculo salvo nesta empresa. Acesso atualizado para %d usuário(s); %d conta(s) criada(s).', $linked, $created));
        if ($missingPassword) $this->addFlash('warning', sprintf('%d usuário(s) não importados: informe uma senha manual ou um campo de senha compatível.', $missingPassword));
        if ($selected && !$selectedUsers) $this->addFlash('warning', 'Não foi possível obter os usuários selecionados na origem. O vínculo foi salvo; confira a consulta ao ERP.');
        return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp, 'method' => $method, 'step' => 'users']);
    }

    #[Route('/erp/{erp}/fields/{field}/visibility', name: 'kflow_erp_fields_visibility', methods: ['POST'])]
    public function setIntegrationFieldVisibility(string $erp, string $field, Request $request): JsonResponse
    {
        $this->requireMenu('connections');
        $method = (string) $request->request->get('method');
        $form = (string) $request->request->get('form');
        if (!$this->erpCatalog->supports($erp) || !isset($this->connectionProfile->connectionMethods()[$method]) || !$this->isCsrfTokenValid('field-visibility-'.$erp.'-'.$method.'-'.$form.'-'.$field, (string) $request->request->get('_token'))) return $this->json(['ok' => false, 'message' => 'Não foi possível alterar o campo.'], Response::HTTP_FORBIDDEN);
        $connection = $this->connectionFor($erp);
        if (!$connection instanceof ErpConnection) return $this->json(['ok' => false, 'message' => 'Conexão do ERP não encontrada.'], Response::HTTP_NOT_FOUND);
        $settings = $connection->getSettingsForMethod($method);
        $formConfig = $this->formConfig($this->configuredForms($settings, $this->connectionProfile->databaseForms()), $form);
        if (!$formConfig) return $this->json(['ok' => false, 'message' => 'Formulário não encontrado.'], Response::HTTP_NOT_FOUND);
        $available = \App\Service\IntegrationFields::forForm(array_replace($settings, ['hidden_fields' => array_diff_key($settings['hidden_fields'] ?? [], [$form => true])]), $form, $this->connectionProfile->databaseForms()[$formConfig['template']]['fields']);
        if (!isset($available[$field])) return $this->json(['ok' => false, 'message' => 'Campo não encontrado.'], Response::HTTP_NOT_FOUND);
        $hidden = array_values(array_unique(array_filter($settings['hidden_fields'][$form] ?? [], static fn ($key) => $key !== $field)));
        if (!$request->request->getBoolean('visible')) $hidden[] = $field;
        $settings['hidden_fields'][$form] = array_values(array_unique($hidden));
        $connection->setSettingsForMethod($method, $settings);
        $this->entityManager->flush();
        return $this->json(['ok' => true, 'message' => $request->request->getBoolean('visible') ? 'Campo incluído no formulário.' : 'Campo removido da tela. O vínculo foi preservado para poder restaurar depois.']);
    }

    #[Route('/erp/{erp}/fields/{field}', name: 'kflow_erp_fields_delete', methods: ['POST'])]
    public function deleteIntegrationField(string $erp, string $field, Request $request): JsonResponse
    {
        $this->requireMenu('connections');
        $method = (string) $request->request->get('method');
        $form = (string) $request->request->get('form');
        if (!$this->erpCatalog->supports($erp) || !isset($this->connectionProfile->connectionMethods()[$method]) || !preg_match('/^extra_[a-f0-9]{12}$/D', $field) || !$this->isCsrfTokenValid('delete-integration-field-'.$erp.'-'.$method.'-'.$form.'-'.$field, (string) $request->request->get('_token'))) return $this->json(['ok' => false, 'message' => 'Não foi possível excluir o campo.'], Response::HTTP_FORBIDDEN);
        $connection = $this->connectionFor($erp);
        if (!$connection instanceof ErpConnection) return $this->json(['ok' => false, 'message' => 'Conexão do ERP não encontrada.'], Response::HTTP_NOT_FOUND);
        $settings = $connection->getSettingsForMethod($method);
        if (!isset($settings['custom_fields'][$form][$field])) return $this->json(['ok' => false, 'message' => 'Campo adicional não encontrado.'], Response::HTTP_NOT_FOUND);
        unset($settings['custom_fields'][$form][$field], $settings['bindings'][$form]['mapping'][$field], $settings['form_mappings'][$form][$field]);
        foreach ($settings['write_bindings'][$form] ?? [] as $action => $binding) if (is_array($binding)) unset($settings['write_bindings'][$form][$action]['mapping'][$field]);
        $connection->setSettingsForMethod($method, $settings);
        $this->entityManager->flush();
        return $this->json(['ok' => true, 'message' => 'Campo removido do formulário e de seus vínculos.']);
    }

    #[Route('/erp/{erp}/fields', name: 'kflow_erp_fields_create', methods: ['POST'])]
    public function createIntegrationField(string $erp, Request $request): Response
    {
        $this->requireMenu('connections');
        $method = (string) $request->request->get('method');
        $form = (string) $request->request->get('form');
        if (!$this->isCsrfTokenValid('field-'.$erp.'-'.$method.'-'.$form, (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
        if (!isset($this->connectionProfile->connectionMethods()[$method])) throw $this->createNotFoundException();
        $connection = $this->connectionFor($erp);
        if (!$connection) throw $this->createNotFoundException();
        $settings = $connection->getSettingsForMethod($method);
        if (!$this->formConfig($this->configuredForms($settings, $this->connectionProfile->databaseForms()), $form)) throw $this->createNotFoundException();
        $label = mb_substr(trim((string) $request->request->get('label')), 0, 80);
        $type = (string) $request->request->get('type', 'text');
        if ('' === $label || !in_array($type, ['text', 'number', 'date', 'checkbox', 'textarea'], true)) {
            $this->addFlash('warning', 'Informe o nome e o tipo do novo campo.');
        } elseif (count($settings['custom_fields'][$form] ?? []) >= 50) {
            $this->addFlash('warning', 'Este formulário já possui 50 campos adicionais.');
        } else {
            $key = 'extra_'.bin2hex(random_bytes(6));
            $settings['custom_fields'][$form][$key] = ['label' => $label, 'type' => $type, 'required' => $request->request->getBoolean('required')];
            $connection->setSettingsForMethod($method, $settings);
            $this->entityManager->flush();
            $this->addFlash('success', 'Campo criado. Selecione sua coluna ou propriedade e salve o De/Para.');
        }
        return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp, 'method' => $method, 'form' => $form, 'step' => 'mapping']);
    }

    #[Route('/erp/{erp}/forms', name: 'kflow_erp_forms_create', methods: ['POST'])]
    public function createIntegrationForm(string $erp, Request $request): Response
    {
        $this->requireMenu('connections');
        $method = (string) $request->request->get('method');
        if (!$this->erpCatalog->supports($erp)
            || !array_key_exists($method, $this->connectionProfile->connectionMethods())
            || !$this->isCsrfTokenValid('create-integration-form-'.$erp.'-'.$method, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $template = (string) $request->request->get('template');
        $definitions = $this->connectionProfile->databaseForms();
        if (!isset($definitions[$template])) {
            $this->addFlash('warning', 'Modelo de formulário inválido.');
            return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp, 'method' => $method]);
        }

        $connection = $this->connectionFor($erp);
        if (!$connection instanceof ErpConnection) {
            $this->addFlash('warning', 'Salve a conexão antes de criar formulários.');
            return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp, 'method' => $method]);
        }

        $settings = $connection->getSettingsForMethod($method);
        $forms = $this->configuredForms($settings, $definitions);
        $label = trim((string) $request->request->get('label'));
        $label = mb_substr($label, 0, 80);
        if ('' === $label || in_array(mb_strtolower($label), array_map(static fn (array $f): string => mb_strtolower($f['label']), $forms), true)) {
            $this->addFlash('warning', 'Informe um nome exclusivo para identificar o novo formulário.');
            return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp, 'method' => $method, 'step' => 'table']);
        }
        $form = ['id' => 'form-'.bin2hex(random_bytes(4)), 'label' => $label, 'template' => $template];
        $forms[] = $form;
        $settings['form_catalog'] = $forms;
        $connection->setSettingsForMethod($method, $settings);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Formulário %s criado. Selecione a tabela de origem.', $label));
        return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp, 'method' => $method, 'step' => ErpConnection::METHOD_DATABASE === $method ? 'table' : 'mapping', 'form' => $form['id']]);
    }

    #[Route('/erp/{erp}/forms/{form}', name: 'kflow_erp_forms_delete', methods: ['POST'])]
    public function deleteIntegrationForm(string $erp, string $form, Request $request): JsonResponse
    {
        $this->requireMenu('connections');
        $method = (string) $request->request->get('method');
        if (!$this->erpCatalog->supports($erp)
            || !array_key_exists($method, $this->connectionProfile->connectionMethods())
            || !$this->isCsrfTokenValid('delete-integration-form-'.$erp.'-'.$method.'-'.$form, (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'message' => 'Não foi possível excluir o formulário.'], Response::HTTP_FORBIDDEN);
        }

        $connection = $this->connectionFor($erp);
        if (!$connection instanceof ErpConnection) {
            return $this->json(['ok' => false, 'message' => 'Conexão do ERP não encontrada.'], Response::HTTP_NOT_FOUND);
        }

        $settings = $connection->getSettingsForMethod($method);
        if (!$this->formConfig($this->configuredForms($settings, $this->connectionProfile->databaseForms()), $form)) {
            return $this->json(['ok' => false, 'message' => 'Formulário não encontrado.'], 404);
        }
        $settings = \App\Service\FormBindingRegistry::remove($settings, $form);
        if ('database' === $method && 'products' === $form) $connection->setProductMapping([]);
        $connection->setSettingsForMethod($method, $settings);
        $this->entityManager->flush();

        return $this->json(['ok' => true, 'message' => 'Formulário e seus vínculos foram excluídos.']);
    }

    #[Route('/erp/{erp}/webservice/test', name: 'kflow_erp_webservice_test', methods: ['POST'])]
    public function testErpWebService(string $erp, Request $request): Response
    {
        $this->requireMenu('connections');
        if (!$this->erpCatalog->supports($erp) || !$this->isCsrfTokenValid('test-erp-webservice-'.$erp, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $connection = $this->connectionFor($erp);
        if (!$connection instanceof ErpConnection) {
            $this->addFlash('warning', 'Salve a conexão WebService antes de executar o teste.');

            return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp, 'method' => ErpConnection::METHOD_WEBSERVICE]);
        }

        $result = $this->testWebService($connection);
        $this->addFlash($result['success'] ? 'success' : 'warning', sprintf('%s Tempo: %d ms.', $result['message'], $result['responseTimeMs']));

        return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp, 'method' => ErpConnection::METHOD_WEBSERVICE]);
    }

    #[Route('/erp/{erp}/webservice/services', name: 'kflow_erp_webservice_service_create', methods: ['POST'])]
    public function createWebService(string $erp, Request $request): Response
    {
        $this->requireMenu('connections');
        if (!$this->erpCatalog->supports($erp) || !$this->isCsrfTokenValid('create-webservice-'.$erp, (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
        $connection = $this->connectionFor($erp);
        if (!$connection instanceof ErpConnection) { $this->addFlash('warning', 'Salve a conexão WebService antes de cadastrar serviços.'); return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp, 'method' => ErpConnection::METHOD_WEBSERVICE]); }
        $settings = $connection->getSettingsForMethod(ErpConnection::METHOD_WEBSERVICE);
        $name = mb_substr(trim((string) $request->request->get('name')), 0, 120);
        $endpoint = mb_substr(trim((string) $request->request->get('endpoint')), 0, 500);
        if ('' === $name || '' === $endpoint) { $this->addFlash('warning', 'Informe nome e URL, IP ou link do WebService.'); return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp, 'method' => ErpConnection::METHOD_WEBSERVICE, 'step' => 'services']); }
        $services = $this->webServices($settings);
        $services[] = ['id' => 'ws-'.bin2hex(random_bytes(4)), 'name' => $name, 'endpoint' => $endpoint, 'port' => mb_substr(trim((string) $request->request->get('port')), 0, 80), 'method' => mb_substr(trim((string) $request->request->get('service_method')), 0, 80), 'active' => '1' === (string) $request->request->get('active'), 'last_test_status' => 'NAO_TESTADO', 'last_test_message' => ''];
        $settings['webservices'] = $services; $connection->setSettingsForMethod(ErpConnection::METHOD_WEBSERVICE, $settings); $this->entityManager->flush();
        $this->addFlash('success', 'WebService cadastrado.');
        return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp, 'method' => ErpConnection::METHOD_WEBSERVICE, 'step' => 'services']);
    }

    #[Route('/erp/{erp}/webservice/services/{service}/test', name: 'kflow_erp_webservice_service_test', methods: ['POST'])]
    public function testRegisteredWebService(string $erp, string $service, Request $request): Response
    {
        $this->requireMenu('connections');
        if (!$this->erpCatalog->supports($erp) || !$this->isCsrfTokenValid('test-webservice-'.$erp.'-'.$service, (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
        $connection = $this->connectionFor($erp); if (!$connection instanceof ErpConnection) throw $this->createNotFoundException();
        $settings = $connection->getSettingsForMethod(ErpConnection::METHOD_WEBSERVICE); $services = $this->webServices($settings);
        foreach ($services as $index => $item) if ($item['id'] === $service) { $result = $this->testWebService($connection, $item); $services[$index]['last_test_status'] = $result['success'] ? 'SUCESSO' : 'ERRO'; $services[$index]['last_test_message'] = $result['message']; $settings['webservices'] = $services; $connection->setSettingsForMethod(ErpConnection::METHOD_WEBSERVICE, $settings); $this->entityManager->flush(); $this->addFlash($result['success'] ? 'success' : 'warning', $result['message']); break; }
        return $this->redirectToRoute('kflow_erp_connect', ['erp' => $erp, 'method' => ErpConnection::METHOD_WEBSERVICE, 'step' => 'services']);
    }

    #[Route('/erp/database', name: 'kflow_erp_database', methods: ['GET'])]
    public function database(): Response
    {
        $this->requireMenu('connections');
        $activeErp = $this->activeErp();

        return null === $activeErp
            ? $this->redirectToRoute('kflow_erp_index')
            : $this->redirectToRoute('kflow_erp_connect', ['erp' => $activeErp, 'method' => ErpConnection::METHOD_DATABASE]);
    }

    #[Route('/metabase', name: 'kflow_metabase', methods: ['GET'])]
    public function metabase(): Response
    {
        $this->requireMenu('connections');
        return $this->redirect($this->metabaseUrl);
    }

    #[Route('/erp/{erp}/{area}', name: 'kflow_erp_area', methods: ['GET'], requirements: ['area' => 'integration|users'])]
    public function erpArea(string $erp, string $area): Response
    {
        $this->requireMenu('connections');
        if (!$this->erpCatalog->supports($erp)) {
            throw $this->createNotFoundException();
        }

        $titles = ['integration' => 'Integração', 'users' => 'Usuários'];

        return $this->render('kflow/module.html.twig', [
            'title' => sprintf('%s · %s', $erp, $titles[$area]),
            'description' => sprintf('Configurações de %s para o ERP %s.', strtolower($titles[$area]), $erp),
            'icon' => 'gear',
        ]);
    }

    private function activeErp(): ?string
    {
        return $this->access->activeErp($this->currentUser());
    }

    private function partyPage(Request $request, string $type, string $title, string $module, string $icon, string $singular): Response
    {
        $this->requireMenu($this->menuForModule($module));
        $connection = $this->activeConnection();
        $activeErp = $connection?->getErpName();
        $partyData = [
            'configured' => false,
            'parties' => [],
            'error' => null,
            'sourceTable' => 'suppliers' === $type ? 'E095FOR' : 'E073TRA',
            'recordCount' => 0,
            'page' => 1,
            'perPage' => 10,
            'pageCount' => 1,
            'missingAddressCount' => 0,
            'staleCount' => 0,
        ];
        if ($connection instanceof ErpConnection) {
            $partyData = $this->cachedResult($type, max(1, $request->query->getInt('page', 1)), $connection);
        }

        return $this->render('kflow/parties.html.twig', [
            'activeErp' => $activeErp,
            'connectionMethod' => $connection?->getConnectionMethod() ?? 'database',
            'partyData' => $partyData,
            'extraFields' => \App\Service\IntegrationFields::forForm($connection?->getSettingsForMethod($connection->getConnectionMethod() ?? '') ?? [], $this->partyBinding($connection, $type)['form'] ?? $type, []),
            'partyType' => $type,
            'title' => $title,
            'singular' => $singular,
            'icon' => $icon,
            'module' => $module,
        ]);
    }

    private function activeConnection(): ?ErpConnection
    {
        $erp = $this->activeErp();
        if (null === $erp) return null;
        $connection = $this->entityManager->getRepository(ErpConnection::class)->findOneBy(['company' => $this->currentCompany(), 'erpName' => $erp, 'isActive' => true]);

        return $connection instanceof ErpConnection ? $connection : null;
    }

    private function connectionFor(string $erp): ?ErpConnection
    {
        $connection = $this->entityManager->getRepository(ErpConnection::class)->findOneBy(['company' => $this->currentCompany(), 'erpName' => $erp]);

        return $connection instanceof ErpConnection ? $connection : null;
    }

    /** @return array<string, mixed> */
    private function settingsForConnection(string $erp, string $method, ?ErpConnection $connection): array
    {
        return array_replace(
            $this->connectionProfile->defaultSettings($erp, $method),
            $connection?->getSettingsForMethod($method) ?? [],
        );
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) throw $this->createAccessDeniedException();
        return $user;
    }

    private function currentCompany(): Company
    {
        $company = $this->access->activeCompany($this->currentUser());
        if (!$company instanceof Company) throw $this->createAccessDeniedException('Nenhuma empresa está disponível para este usuário.');
        return $company;
    }

    private function requirePlatformAdmin(): void
    {
        if (!$this->access->isPlatformAdmin($this->currentUser())) throw $this->createAccessDeniedException();
    }

    private function requireMenu(string $menu): void
    {
        if (!$this->access->can($this->currentUser(), $menu)) throw $this->createAccessDeniedException('Você não tem acesso a este menu nesta empresa.');
    }

    private function requireRegistrationReviewAccess(): void
    {
        foreach (['clients', 'suppliers', 'carriers'] as $menu) {
            if ($this->access->can($this->currentUser(), $menu)) return;
        }

        throw $this->createAccessDeniedException('Você não tem acesso aos cadastros para consultar dados fiscais.');
    }

    private function menuForModule(string $module): string
    {
        return match ($module) { 'cliente' => 'clients', 'fornecedor' => 'suppliers', 'transportador' => 'carriers', 'requisicao' => 'requisition', 'aprovacao', 'aprovacao-requisicao' => 'approval', default => 'dashboard' };
    }

    /** @return list<CompanyMembership> */
    private function membershipsForCompany(): array
    {
        return $this->entityManager->getRepository(CompanyMembership::class)->findBy(['company' => $this->currentCompany()], ['id' => 'ASC']);
    }

    /** @param list<array<string, mixed>> $rows
     *  @param array<string, mixed> $access
     *  @return list<array{identifier: string, name: string, email: string}>
     */
    private function normalizeErpUsers(array $rows, array $access, bool $includePassword = false): array
    {
        $mapping = is_array($access['mapping'] ?? null) ? $access['mapping'] : [];
        $users = [];
        foreach ($rows as $row) {
            $identifier = trim((string) ($row[$mapping['identifier'] ?? ''] ?? (!empty($mapping['identifier']) ? \App\Service\ProcessGateway::readPath($row, $mapping['identifier']) : '')));
            if ('' === $identifier) continue;
            $users[] = ['identifier' => $identifier, 'name' => trim((string) ($row[$mapping['name'] ?? ''] ?? (!empty($mapping['name']) ? \App\Service\ProcessGateway::readPath($row, $mapping['name']) : ''))), 'email' => trim((string) ($row[$mapping['email'] ?? ''] ?? (!empty($mapping['email']) ? \App\Service\ProcessGateway::readPath($row, $mapping['email']) : '')))];
            if ($includePassword) $users[array_key_last($users)]['_password'] = (string) (!empty($mapping['password']) ? ($row[$mapping['password']] ?? \App\Service\ProcessGateway::readPath($row, $mapping['password'])) : '');
        }
        return $users;
    }

    /** @return list<array{identifier: string, name: string, email: string}> */
    private function erpUserDirectory(string $erp, string $method, array $settings): array
    {
        if (ErpConnection::METHOD_DATABASE !== $method) {
            $connection = $this->connectionFor($erp);
            if (!$connection) return [];
            return $this->normalizeErpUsers($this->processGateway->readUsers($connection, $method, $settings['user_access'] ?? []), $settings['user_access'] ?? [], true);
        }
        $access = is_array($settings['user_access'] ?? null) ? $settings['user_access'] : [];
        $table = (string) ($access['table'] ?? '');
        $columns = array_values(array_filter(array_map('strval', $access['mapping'] ?? [])));
        if ('' === $table || [] === $columns) return [];
        $rows = $this->databaseSchemaInspector->rows($settings, $table, $columns)['rows'];
        return $this->normalizeErpUsers($rows, $access, true);
    }

    /** @return array<string, mixed> */
    /** @return list<array{id: string, name: string, endpoint: string, port: string, method: string, active: bool, last_test_status: string, last_test_message: string}> */
    private function webServices(array $settings): array
    {
        $services = is_array($settings['webservices'] ?? null) ? $settings['webservices'] : [];
        $normalized = [];
        foreach ($services as $service) {
            if (!is_array($service) || !preg_match('/^ws-[a-f0-9]{8}$/', (string) ($service['id'] ?? ''))) continue;
            $normalized[] = ['id' => (string) $service['id'], 'name' => mb_substr((string) ($service['name'] ?? ''), 0, 120), 'endpoint' => mb_substr((string) ($service['endpoint'] ?? ''), 0, 500), 'port' => mb_substr((string) ($service['port'] ?? ''), 0, 80), 'method' => mb_substr((string) ($service['method'] ?? ''), 0, 80), 'active' => (bool) ($service['active'] ?? true), 'last_test_status' => (string) ($service['last_test_status'] ?? 'NAO_TESTADO'), 'last_test_message' => mb_substr((string) ($service['last_test_message'] ?? ''), 0, 240)];
        }
        return $normalized;
    }

    /** @param array<string, mixed>|null $service @return array{success: bool, message: string, responseTimeMs: int} */
    private function testWebService(ErpConnection $connection, ?array $service = null): array
    {
        $settings = $connection->getSettingsForMethod(ErpConnection::METHOD_WEBSERVICE);
        $endpoint = trim((string) ($service['endpoint'] ?? $settings['endpoint'] ?? ''));
        if ('' === $endpoint) return ['success' => false, 'message' => 'Informe a URL, IP ou link do WebService antes de testar.', 'responseTimeMs' => 0];
        if (!preg_match('#^https?://#i', $endpoint)) $endpoint = 'http://'.$endpoint;
        $port = trim((string) ($service['port'] ?? $settings['port'] ?? ''));
        if ('' !== $port && !str_contains(parse_url($endpoint, PHP_URL_HOST) ?: '', ':') && !preg_match('#:\d+(?:/|$)#', $endpoint)) $endpoint = preg_replace('#^(https?://[^/]+)#', '$1:'.$port, $endpoint) ?: $endpoint;
        $started = hrtime(true);
        try {
            $options = ['timeout' => max(1, min(8, (int) ($settings['timeout_seconds'] ?? 20))), 'max_duration' => max(1, min(8, (int) ($settings['timeout_seconds'] ?? 20)))];
            $username = trim((string) ($settings['username'] ?? '')); $encrypted = (string) ($settings['password_encrypted'] ?? '');
            if ('' !== $username && '' !== $encrypted) $options['auth_basic'] = [$username, $this->secretCipher->decrypt($encrypted)];
            $status = $this->httpClient->request('GET', $endpoint, $options)->getStatusCode();
            $success = $status < 500; $message = sprintf('WebService respondeu HTTP %d.', $status);
        } catch (TransportExceptionInterface $exception) { $success = false; $message = 'Não foi possível obter resposta do WebService.'; }
        $elapsed = (int) round((hrtime(true) - $started) / 1_000_000);
        if (null === $service) { $settings['last_test_at'] = (new \DateTimeImmutable())->format(DATE_ATOM); $settings['last_test_status'] = $success ? 'SUCESSO' : 'ERRO'; $settings['last_test_message'] = $message; $connection->setSettingsForMethod(ErpConnection::METHOD_WEBSERVICE, $settings); $this->entityManager->flush(); }
        return ['success' => $success, 'message' => $message, 'responseTimeMs' => $elapsed];
    }

    private function cachedResult(string $type, int $page, ?ErpConnection $connection = null, bool $load = true): array
    {
        $companyId = $this->currentCompany()->getId() ?? 0;
        $erp = strtolower($connection?->getErpName() ?? 'none');
        $revision = $connection?->getUpdatedAt()?->getTimestamp() ?? $connection?->getConfiguredAt()?->getTimestamp() ?? 0;
        $profileKey = substr(hash('sha256', json_encode([$connection?->getConnectionMethod(), $connection?->getSettingsForMethod($connection->getConnectionMethod() ?? '')])), 0, 20);
        $key = sprintf('united.v5.%s.%d.%s.%s.%d.%d', $type, $companyId, $erp, $profileKey, $revision, $page);
        if (null === $connection || !$load) {
            $item = $this->cache->getItem($key);
            return $item->isHit() ? (array) $item->get() : $this->emptyCachedResult($type) + ['pending' => true];
        }
        return $this->cache->get($key, function (ItemInterface $item) use ($type, $page, $connection): array {
            if ($connection->getConnectionMethod() !== ErpConnection::METHOD_DATABASE) {
                $result = $this->remoteFormCatalog->list($connection, $type, $page);
                $item->expiresAfter(empty($result['error']) ? 300 : 20);
                return $this->withWriteLinks($result, $type, $connection);
            }
            $settings = $this->settingsForConnection($connection->getErpName(), ErpConnection::METHOD_DATABASE, $connection);

            if ('products' === $type) {
                $result = $this->seniorProductCatalog->listProducts($this->productBinding($connection), $settings, $page);
            } elseif ('customers' === $type) {
                $result = $this->seniorCustomerCatalog->listCustomers($this->partyBinding($connection, 'customers'), $settings, $page);
            } else {
                $result = $this->seniorPartyCatalog->list($type, $this->partyBinding($connection, $type), $settings, $page);
            }
            $result = $this->withWriteLinks($result, $type, $connection);
            $item->expiresAfter(null === ($result['error'] ?? null) ? 300 : 20);

            return $result;
        });
    }

    /** @return array<string, mixed> */
    private function withWriteLinks(array $result, string $type, ErpConnection $connection): array
    {
        $aliases = match ($type) {
            'products' => ['company'=>'CodEmp','product_code'=>'CodPro','product_name'=>'DesPro','unit'=>'UniMed','ncm'=>'Ncm','origin_code'=>'CodOri','family'=>'CodFam','cst_pis'=>'CstPis','cst_cofins'=>'CstCofins','cst_icms'=>'CstIcms'],
            'customers' => ['company'=>'CodEmp','customer_code'=>'CodCli','name'=>'NomCli','document'=>'CgcCpf','state_registration'=>'InsEst','email'=>'EmlCli'],
            default => [($type==='suppliers'?'supplier_code':'carrier_code')=>'Code','name'=>'Name','document'=>'Document','state_registration'=>'StateRegistration','email'=>'Email','phone'=>'Phone'],
        };
        $key = match($type) {'products'=>'products','customers'=>'customers',default=>'parties'};
        $binding = $this->partyBinding($connection,$type);
        $settings = $connection->getSettingsForMethod($connection->getConnectionMethod() ?? '');
        $write = $settings['write_bindings'][$type]['update'] ?? [];
        foreach ($result[$key] ?? [] as $i=>$record) {
            $data=[];$keys=[];
            foreach ($aliases as $field=>$alias) if (array_key_exists($alias,$record)) $data[$field]=$record[$alias];
            foreach ($record as $alias=>$value) if (str_starts_with($alias,'X_')) $data[substr($alias,2)]=$value;
            foreach (explode(',',$write['key_columns']??'') as $destination) {
                $destination=trim($destination);if($destination==='')continue;
                foreach ($binding['mapping']??[] as $field=>$source) if (strcasecmp($destination,$source)===0 && isset($data[$field])) $keys[$destination]=$data[$field];
            }
            $result[$key][$i]['_writeUrl']=$this->generateUrl('united_write',['form'=>$type,'method'=>$connection->getConnectionMethod()??'database','data'=>$data,'keys'=>$keys]);
        }
        return $result;
    }

    private function emptyCachedResult(string $type): array
    {
        if ('products' === $type) {
            return ['recordCount' => 0, 'products' => []];
        }
        if ('customers' === $type) {
            return ['recordCount' => 0, 'missingAddressCount' => 0, 'staleCount' => 0];
        }

        return ['recordCount' => 0, 'parties' => [], 'missingAddressCount' => 0, 'staleCount' => 0];
    }

    /** @return array<string, string> */
    private function effectiveMapping(?ErpConnection $connection): array
    {
        return array_replace(
            $this->connectionProfile->defaultProductMapping('Senior'),
            $connection?->getProductMapping() ?? [],
        );
    }

    /** @return array<string, mixed> */
    private function productBinding(?ErpConnection $connection): array
    {
        $binding = $this->partyBinding($connection, 'products');
        $binding['mapping'] = array_replace($this->effectiveMapping($connection), is_array($binding['mapping'] ?? null) ? $binding['mapping'] : []);

        return $binding;
    }

    /** @return array<string, mixed> */
    private function partyBinding(?ErpConnection $connection, string $form): array
    {
        if ($connection && $connection->getConnectionMethod() !== ErpConnection::METHOD_DATABASE) return \App\Service\RemoteFormCatalog::binding($connection, $form);
        $settings = $connection?->getSettingsForMethod(ErpConnection::METHOD_DATABASE) ?? [];
        return \App\Service\FormBindingRegistry::resolve($settings, $form, $connection?->getProductMapping() ?? []);
    }

    /** @param array<string, mixed> $settings
     *  @param array<string, array{label: string, fields: array<string, array{label: string, hint: string}>}> $definitions
     *  @return list<array{id: string, label: string, template: string}>
     */
    private function configuredForms(array $settings, array $definitions): array
    {
        return \App\Service\FormBindingRegistry::forms($settings, $definitions);
    }

    /** @param list<array{id: string, label: string, template: string}> $forms
     *  @return array{id: string, label: string, template: string}|null
     */
    private function formConfig(array $forms, string $id): ?array
    {
        foreach ($forms as $form) {
            if ($form['id'] === $id) {
                return $form;
            }
        }

        return null;
    }
}
