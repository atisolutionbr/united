<?php

namespace App\Controller;

use App\Entity\{ErpConnection, User};
use App\Service\{KFlowAccess, LookupCatalog, PurchasingCatalog, DatabaseSchemaInspector, ProcessGateway};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response, JsonResponse};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class LookupController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly KFlowAccess $access, private readonly LookupCatalog $lookups, private readonly DatabaseSchemaInspector $schema) {}
    private function connection(string $permission): ErpConnection
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$this->access->can($user, $permission)) throw $this->createAccessDeniedException();
        $c = $this->em->getRepository(ErpConnection::class)->findOneBy(['company' => $this->access->activeCompany($user), 'erpName' => $this->access->activeErp($user), 'isActive' => true]);
        if (!$c) throw $this->createNotFoundException('Selecione um ERP ativo.');
        return $c;
    }

    #[Route('/unitedati/lookups/{form}/{field}', name: 'united_lookup', requirements: ['form' => 'requests|requisitions', 'field' => '[a-z_]+'], methods: ['GET'])]
    public function search(string $form, string $field, Request $request): JsonResponse
    {
        $c = $this->connection('requests' === $form ? 'solicitation' : 'requisition');
        if (!isset(PurchasingCatalog::fields($form)[$field])) throw $this->createNotFoundException();
        try {
            $term = mb_substr(trim((string) $request->query->get('q')), 0, 120);
            $source = $this->lookups->source($c, $form, $field);
            $result = $this->lookups->search($c, $form, $field, $term, min(10000, max(1, $request->query->getInt('page', 1))));
            $choices = array_filter($request->getSession()->get('united_lookup_choices', []), static fn ($item) => $item['expires'] > time());
            foreach ($result['items'] as &$item) {
                $item['token'] = bin2hex(random_bytes(24));
                $choices[$item['token']] = ['connection' => $c->getId(), 'form' => $form, 'field' => $field, 'value' => $item['value'], 'label' => $item['label'], 'company' => $item['company'], 'source' => hash('sha256', json_encode($source)), 'expires' => time() + 3600];
            }
            $request->getSession()->set('united_lookup_choices', array_slice($choices, -3000, null, true));
            return $this->json($result);
        } catch (\InvalidArgumentException $e) { return $this->json(['items' => [], 'more' => false, 'error' => $e->getMessage()], 422); }
        catch (\Throwable $e) { return $this->json(['items' => [], 'more' => false, 'error' => 'Não foi possível consultar a lista. Confira o vínculo e a conexão do ERP.'], 502); }
    }

    #[Route('/unitedati/lookup-settings/{form}', name: 'united_lookup_settings', requirements: ['form' => 'requests|requisitions|products'], methods: ['GET', 'POST'])]
    public function configure(string $form, Request $request): Response
    {
        $c = $this->connection('connections');
        $fields = 'products' === $form ? ['product' => ['label' => 'Produto']] : array_filter(PurchasingCatalog::fields($form), static fn ($f, $key) => 'product' !== $key && in_array($f['type'], ['text', 'textarea'], true), ARRAY_FILTER_USE_BOTH);
        $field = (string) $request->query->get('field', array_key_first($fields));
        if (!isset($fields[$field])) throw $this->createNotFoundException();
        $method = (string) $request->query->get('method', 'database');
        if (!in_array($method, ['database', 'api', 'webservice'], true)) throw $this->createNotFoundException();
        $settings = $c->getSettingsForMethod($method);
        $source = $settings['lookup_sources'][$form][$field] ?? [];
        $error = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('lookup-'.$form.'-'.$field.'-'.$method, (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
            $source = $request->request->all('source');
            try {
                $enabled = $request->request->getBoolean('enabled');
                if ($enabled) {
                    if ('database' === $method) {
                        ProcessGateway::identifier($source['table'] ?? '');
                        $columns = $this->schema->columns($settings, $source['table'])['columns'];
                        foreach (['value', 'label'] as $key) if (empty($source[$key]) || !in_array($source[$key], $columns, true)) throw new \InvalidArgumentException('Selecione as colunas de código e nome.');
                        foreach (['barcode', 'company_column'] as $key) if (!empty($source[$key]) && !in_array($source[$key], $columns, true)) throw new \InvalidArgumentException('Coluna adicional inválida.');
                    } else {
                        foreach (['operation', 'items_path', 'value', 'label'] as $key) if (empty($source[$key])) throw new \InvalidArgumentException('Preencha operação, coleção, código e nome.');
                        foreach (['items_path', 'value', 'label'] as $key) ProcessGateway::readPath([], $source[$key]);
                        if ('api' === $method && (!str_starts_with($source['operation'], '/') || str_starts_with($source['operation'], '//'))) throw new \InvalidArgumentException('Informe uma rota relativa iniciada por /.');
                    }
                }
                // A field has one active list across profiles; saving explicitly switches its source.
                foreach (['database', 'api', 'webservice'] as $other) {
                    $profile = $c->getSettingsForMethod($other);
                    if (isset($profile['lookup_sources'][$form][$field])) { $profile['lookup_sources'][$form][$field]['enabled'] = false; $c->setSettingsForMethod($other, $profile); }
                }
                $settings = $c->getSettingsForMethod($method);
                $settings['lookup_sources'][$form][$field] = $source + ['enabled' => $enabled];
                $settings['lookup_sources'][$form][$field]['enabled'] = $enabled;
                $c->setSettingsForMethod($method, $settings); $this->em->flush();
                $this->addFlash('success', 'Origem da lista salva. A consulta usará este vínculo.');
                return $this->redirectToRoute('united_lookup_settings', ['form' => $form, 'field' => $field, 'method' => $method]);
            } catch (\InvalidArgumentException $e) { $error = $e->getMessage(); }
        } elseif ($request->query->has('table')) $source['table'] = (string) $request->query->get('table');
        $tables = 'database' === $method ? $this->schema->tables($settings) : ['tables' => [], 'error' => null];
        $columns = 'database' === $method && !empty($source['table']) ? $this->schema->columns($settings, $source['table']) : ['columns' => [], 'error' => null];
        return $this->render('kflow/purchasing/lookups.html.twig', ['form' => $form, 'field' => $field, 'fields' => $fields, 'method' => $method, 'source' => $source, 'tables' => $tables['tables'], 'columns' => $columns['columns'], 'error' => $error ?? $tables['error'] ?? $columns['error'], 'erp' => $c->getErpName()]);
    }
}
