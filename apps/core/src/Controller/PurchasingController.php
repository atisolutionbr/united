<?php

namespace App\Controller;

use App\Entity\{ErpConnection, User};
use App\Service\{KFlowAccess, PurchasingCatalog, ProcessGateway, IntegrationFields, DatabaseSchemaInspector};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/unitedati/purchasing')]
#[IsGranted('ROLE_USER')]
final class PurchasingController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly Connection $db, private readonly KFlowAccess $access, private readonly ProcessGateway $gateway, private readonly DatabaseSchemaInspector $schema, private readonly \App\Service\LookupCatalog $lookups) {}

    private function connection(string $menu): ErpConnection
    {
        $user = $this->getUser();
        if (!$user instanceof User || ('' !== $menu && !$this->access->can($user, $menu))) throw $this->createAccessDeniedException();
        $company = $this->access->activeCompany($user);
        $erp = $this->access->activeErp($user);
        $connection = $company && $erp ? $this->em->getRepository(ErpConnection::class)->findOneBy(['company' => $company, 'erpName' => $erp, 'isActive' => true]) : null;
        if (!$connection) throw $this->createNotFoundException('Selecione uma empresa e um ERP ativo em Conectar ERP.');
        return $connection;
    }

    private function process(string $process): void { if (!isset(PurchasingCatalog::CARDS[$process])) throw $this->createNotFoundException(); }
    private function token(Request $request, string $id): void { if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) throw $this->createAccessDeniedException('Atualize a página e tente novamente.'); }
    private function bindings(ErpConnection $c, string $process): array
    {
        $rows = $this->db->fetchAllAssociative('SELECT * FROM united_process_binding WHERE connection_id = ? AND process = ? ORDER BY id', [$c->getId(), $process]);
        foreach ($rows as &$row) $row['config'] = json_decode($row['config'], true, 512, JSON_THROW_ON_ERROR);
        return $rows;
    }
    private function binding(ErpConnection $c, string $process, int $id): array
    {
        foreach ($this->bindings($c, $process) as $row) if ((int) $row['id'] === $id) return $row;
        throw $this->createNotFoundException('Vínculo não encontrado nesta empresa e ERP.');
    }
    private function audit(ErpConnection $c, string $action, array $details, ?int $document = null): void
    {
        $this->db->insert('united_process_audit', ['connection_id' => $c->getId(), 'document_id' => $document, 'actor_id' => $this->getUser()->getId(), 'action' => $action, 'details' => json_encode($details, JSON_THROW_ON_ERROR)]);
    }

    #[Route('/approvals', name: 'united_approvals', methods: ['GET'])]
    public function approvals(): Response
    {
        $c = $this->connection('approval');
        $counts = [];
        foreach (PurchasingCatalog::CARDS as $key => $label) $counts[$key] = count(array_filter($this->bindings($c, $key), static fn ($b) => 'approval' === $b['config']['purpose']));
        return $this->render('kflow/purchasing/approvals.html.twig', ['cards' => PurchasingCatalog::CARDS, 'counts' => $counts, 'erp' => $c->getErpName()]);
    }

    #[Route('/bindings/{process}', name: 'united_process_bindings', methods: ['GET', 'POST'])]
    public function configure(string $process, Request $request): Response
    {
        $this->process($process);
        $c = $this->connection('connections');
        $id = $request->query->getInt('id');
        $existing = $id ? $this->binding($c, $process, $id) : null;
        $config = $existing['config'] ?? ['method' => 'database', 'purpose' => in_array($process, ['requests', 'requisitions'], true) ? 'create' : 'approval', 'mapping' => []];
        $label = $existing['label'] ?? '';
        $error = null;
        if ($request->isMethod('POST')) {
            $this->token($request, 'process-binding-'.$process);
            $config = $request->request->all('config');
            $label = mb_substr(trim((string) $request->request->get('label')), 0, 120);
            try {
                if ('' === $label) throw new \InvalidArgumentException('Dê um nome ao vínculo.');
                $config = $this->gateway->validate($c, $config);
                $data = ['label' => $label, 'config' => json_encode($config, JSON_THROW_ON_ERROR)];
                if ($id) $this->db->update('united_process_binding', $data, ['id' => $id, 'connection_id' => $c->getId()]);
                else $this->db->insert('united_process_binding', $data + ['connection_id' => $c->getId(), 'process' => $process]);
                $this->audit($c, 'binding.saved', ['process' => $process, 'label' => $label]);
                $this->addFlash('success', 'Vínculo salvo. A configuração não envia dados ao ERP.');
                return $this->redirectToRoute('united_process_bindings', ['process' => $process]);
            } catch (\InvalidArgumentException $e) { $error = $e->getMessage(); }
            catch (\Throwable $e) { $error = 'Não foi possível salvar. Confira a conexão e use um nome exclusivo para o vínculo.'; }
        } else {
            foreach (['method', 'purpose', 'table'] as $key) if ($request->query->has($key)) $config[$key] = (string) $request->query->get($key);
        }
        $method = in_array($config['method'] ?? '', ['database', 'api', 'webservice'], true) ? $config['method'] : 'database';
        $settings = $c->getSettingsForMethod($method);
        $fields = 'create' === ($config['purpose'] ?? '') ? IntegrationFields::forConnection($c, $process, PurchasingCatalog::fields($process)) : ['id' => ['label' => 'Identificador'], 'description' => ['label' => 'Descrição / objeto'], 'requester' => ['label' => 'Solicitante'], 'amount' => ['label' => 'Valor'], 'date' => ['label' => 'Data'], 'document' => ['label' => 'Documento']];
        $tables = 'database' === $method ? $this->schema->tables($settings) : ['tables' => [], 'error' => null];
        $columns = 'database' === $method && !empty($config['table']) ? $this->schema->columns($settings, $config['table']) : ['columns' => [], 'error' => null];
        return $this->render('kflow/purchasing/bindings.html.twig', ['process' => $process, 'title' => PurchasingCatalog::CARDS[$process], 'erp' => $c->getErpName(), 'bindings' => $this->bindings($c, $process), 'config' => $config, 'label' => $label, 'fields' => $fields, 'tables' => $tables['tables'], 'columns' => $columns['columns'], 'error' => $error ?? $tables['error'] ?? $columns['error'], 'id' => $id]);
    }

    #[Route('/approvals/{process}', name: 'united_approval_items', methods: ['GET', 'POST'])]
    public function items(string $process, Request $request): Response
    {
        $this->process($process);
        $c = $this->connection('approval');
        $bindings = array_values(array_filter($this->bindings($c, $process), static fn ($b) => 'approval' === $b['config']['purpose']));
        $selected = $request->query->getInt('source', (int) ($bindings[0]['id'] ?? 0));
        $binding = $selected ? $this->binding($c, $process, $selected) : null;
        if ($binding && 'approval' !== $binding['config']['purpose']) throw $this->createNotFoundException();
        $error = null;
        if ($request->isMethod('POST')) {
            $this->token($request, 'decision-'.$process);
            $key = (string) $request->request->get('record');
            $records = $request->getSession()->get('united_approval_records', []);
            $record = $records[$key] ?? null;
            unset($records[$key]);
            $request->getSession()->set('united_approval_records', $records);
            if (!$record || $record['connection'] !== $c->getId() || $record['binding'] !== $selected || $record['expires'] < time() || $record['config'] !== hash('sha256', json_encode($binding['config']))) throw $this->createAccessDeniedException('A seleção expirou. Atualize a lista.');
            try {
                $this->audit($c, 'decision.started', ['request' => $key, 'binding' => $selected, 'decision' => $request->request->get('decision'), 'keys' => $record['keys']]);
                $receipt = $this->gateway->decide($c, $binding['config'], $record['keys'], (string) $request->request->get('decision'), $key);
                $this->audit($c, 'decision.confirmed', ['request' => $key, 'receipt' => $receipt]);
                $this->addFlash('success', $receipt);
            } catch (\Throwable $e) {
                $this->audit($c, 'decision.unconfirmed', ['request' => $key]);
                $this->addFlash('warning', 'Não houve confirmação da decisão. Consulte o ERP antes de repetir a operação.');
            }
            return $this->redirectToRoute('united_approval_items', ['process' => $process, 'source' => $selected, 'page' => $request->query->getInt('page', 1)]);
        }
        $page = max(1, $request->query->getInt('page', 1));
        $result = ['rows' => [], 'total' => 0];
        if ($binding) try { $result = $this->gateway->list($c, $binding['config'], $page); } catch (\Throwable $e) { $error = 'Não foi possível consultar os objetos. Confira a conexão, a paginação e os campos do vínculo.'; }
        $records = array_filter($request->getSession()->get('united_approval_records', []), static fn ($r) => $r['expires'] >= time());
        foreach ($result['rows'] as &$row) {
            $key = bin2hex(random_bytes(24));
            $records[$key] = ['connection' => $c->getId(), 'binding' => $selected, 'keys' => $row['keys'], 'config' => hash('sha256', json_encode($binding['config'])), 'expires' => time() + 900];
            $row['token'] = $key;
        }
        $request->getSession()->set('united_approval_records', array_slice($records, -300, null, true));
        return $this->render('kflow/purchasing/items.html.twig', ['title' => PurchasingCatalog::CARDS[$process], 'process' => $process, 'bindings' => $bindings, 'selected' => $selected, 'result' => $result, 'page' => $page, 'error' => $error]);
    }

    #[Route('/documents/{kind}', name: 'united_documents', requirements: ['kind' => 'requests|requisitions'], methods: ['GET', 'POST'])]
    public function documents(string $kind, Request $request): Response
    {
        $c = $this->connection('requests' === $kind ? 'solicitation' : 'requisition');
        $fields = IntegrationFields::forConnection($c, $kind, PurchasingCatalog::fields($kind));
        $error = null;
        $input = [];
        if ($request->isMethod('POST')) {
            $this->token($request, 'document-'.$kind);
            $input = $request->request->all('data');
            try {
                $labels = [];
                $tokens = $request->request->all('lookup_tokens');
                $choices = $request->getSession()->get('united_lookup_choices', []);
                foreach ($fields as $key => $field) {
                    if ('' === trim((string) ($input[$key] ?? ''))) continue;
                    $isLookup = in_array($key, \App\Service\LookupCatalog::FIELDS, true);
                    if (!$isLookup) foreach (['database', 'api', 'webservice'] as $method) if (!empty($c->getSettingsForMethod($method)['lookup_sources'][$kind][$key]['enabled'])) $isLookup = true;
                    if (!$isLookup) continue;
                    $choice = $choices[$tokens[$key] ?? ''] ?? null;
                    $source = $this->lookups->source($c, $kind, $key);
                    if (!$choice || $choice['connection'] !== $c->getId() || $choice['form'] !== $kind || $choice['field'] !== $key || $choice['value'] !== (string) $input[$key] || $choice['expires'] < time() || $choice['source'] !== hash('sha256', json_encode($source))) throw new \InvalidArgumentException('Selecione '.$field['label'].' na lista vinculada.');
                    $labels[$key] = $choice['label'];
                }
                $data = PurchasingCatalog::validate($input, $fields);
                $data['_labels'] = $labels;
                $key = (string) $request->request->get('request_key');
                if (!preg_match('/^[a-f0-9]{48}$/D', $key)) throw new \InvalidArgumentException('Atualize a página antes de salvar.');
                $this->db->executeStatement('INSERT INTO united_document (connection_id, kind, actor_id, payload, request_key) VALUES (?, ?, ?, ?, ?) ON CONFLICT (request_key) DO NOTHING', [$c->getId(), $kind, $this->getUser()->getId(), json_encode($data, JSON_THROW_ON_ERROR), $key]);
                $this->addFlash('success', 'Documento salvo. Abra-o para conferir e enviar ao ERP.');
                return $this->redirectToRoute('united_documents', ['kind' => $kind]);
            } catch (\InvalidArgumentException $e) { $error = $e->getMessage(); }
        }
        $lookupFields = \App\Service\LookupCatalog::FIELDS;
        foreach (['database', 'api', 'webservice'] as $method) foreach ($c->getSettingsForMethod($method)['lookup_sources'][$kind] ?? [] as $key => $source) if (!empty($source['enabled'])) $lookupFields[] = $key;
        $page = max(1, $request->query->getInt('page', 1));
        $total = (int) $this->db->fetchOne('SELECT COUNT(*) FROM united_document WHERE connection_id = ? AND kind = ?', [$c->getId(), $kind]);
        $docs = $this->db->fetchAllAssociative('SELECT * FROM united_document WHERE connection_id = ? AND kind = ? ORDER BY id DESC LIMIT 15 OFFSET '.(($page - 1) * 15), [$c->getId(), $kind]);
        foreach ($docs as &$doc) $doc['payload'] = json_decode($doc['payload'], true);
        return $this->render('kflow/purchasing/documents.html.twig', ['kind' => $kind, 'title' => PurchasingCatalog::CARDS[$kind], 'fields' => $fields, 'documents' => $docs, 'page' => $page, 'total' => $total, 'error' => $error, 'input' => $input, 'requestKey' => bin2hex(random_bytes(24)), 'lookupFields' => $lookupFields, 'lookupTokens' => $request->request->all('lookup_tokens'), 'erp' => $c->getErpName()]);
    }

    #[Route('/document/{id}', name: 'united_document', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function document(int $id, Request $request): Response
    {
        $user = $this->getUser();
        $c = $this->connection('');
        $doc = $this->db->fetchAssociative('SELECT * FROM united_document WHERE id = ? AND connection_id = ?', [$id, $c->getId()]);
        if (!$doc) throw $this->createNotFoundException();
        if (!$this->access->can($user, 'requests' === $doc['kind'] ? 'solicitation' : 'requisition')) throw $this->createAccessDeniedException();
        $doc['payload'] = json_decode($doc['payload'], true);
        $bindings = array_values(array_filter($this->bindings($c, $doc['kind']), static fn ($b) => 'create' === $b['config']['purpose']));
        if ($request->isMethod('POST')) {
            $this->token($request, 'document-'.$id);
            $action = (string) $request->request->get('action');
            try {
                if ('send' === $action) {
                    $binding = $this->binding($c, $doc['kind'], $request->request->getInt('binding'));
                    foreach (IntegrationFields::forConnection($c, $doc['kind'], PurchasingCatalog::fields($doc['kind'])) as $key => $field) if (($field['required'] ?? false) && empty($binding['config']['mapping'][$key])) throw new \InvalidArgumentException('Vincule o campo obrigatório: '.$field['label']);
                    if (1 !== $this->db->executeStatement("UPDATE united_document SET delivery_status = 'sending' WHERE id = ? AND delivery_status = 'draft'", [$id])) throw new \InvalidArgumentException('Este documento já foi enviado ou aguarda conferência.');
                    $this->audit($c, 'document.send.started', ['binding' => $binding['id']], $id);
                    try {
                        $receipt = $this->gateway->create($c, $binding['config'], $doc['payload'], $doc['request_key']);
                        $this->db->update('united_document', ['delivery_status' => 'sent', 'receipt' => $receipt], ['id' => $id]);
                        $this->audit($c, 'document.sent', ['receipt' => $receipt], $id);
                        $this->addFlash('success', $receipt);
                    } catch (\Throwable $e) {
                        $this->db->update('united_document', ['delivery_status' => 'unconfirmed'], ['id' => $id]);
                        $this->audit($c, 'document.unconfirmed', [], $id);
                        $this->addFlash('warning', 'Envio sem confirmação. Confira o ERP para evitar duplicidade.');
                    }
                } elseif ('advance' === $action) {
                    if (!$this->access->can($user, 'approval')) throw $this->createAccessDeniedException();
                    $note = trim((string) $request->request->get('note'));
                    if ('' === $note || mb_strlen($note) > 4000) throw new \InvalidArgumentException('Registre a conferência e a referência do documento no ERP.');
                    $stages = array_keys(PurchasingCatalog::STAGES);
                    $current = array_search($doc['stage'], $stages, true);
                    if (false === $current || !isset($stages[$current + 1])) throw new \InvalidArgumentException('Fluxo já concluído.');
                    if (in_array($doc['stage'], ['request-check', 'quotation-check'], true)) {
                        $lastActor = $this->db->fetchOne("SELECT actor_id FROM united_process_audit WHERE document_id = ? AND action = 'workflow.advance' ORDER BY id DESC LIMIT 1", [$id]);
                        if ((int) $lastActor === $user->getId()) throw new \InvalidArgumentException('A dupla verificação deve ser feita por outra pessoa.');
                    }
                    $this->db->transactional(function () use ($doc, $stages, $current, $note, $c, $id): void {
                        if (1 !== $this->db->executeStatement('UPDATE united_document SET stage = ? WHERE id = ? AND stage = ?', [$stages[$current + 1], $id, $doc['stage']])) throw new \InvalidArgumentException('Outra pessoa atualizou o fluxo. Recarregue a página.');
                        $this->audit($c, 'workflow.advance', ['from' => $doc['stage'], 'to' => $stages[$current + 1], 'reference' => $note], $id);
                    });
                    $this->addFlash('success', 'Conferência registrada no fluxo local.');
                }
            } catch (\InvalidArgumentException $e) { $this->addFlash('warning', $e->getMessage()); }
            return $this->redirectToRoute('united_document', ['id' => $id]);
        }
        $audit = $this->db->fetchAllAssociative('SELECT a.*, u.username FROM united_process_audit a LEFT JOIN app_user u ON u.id = a.actor_id WHERE document_id = ? ORDER BY a.id DESC LIMIT 100', [$id]);
        foreach ($audit as &$event) $event['details'] = json_decode($event['details'], true);
        return $this->render('kflow/purchasing/document.html.twig', ['doc' => $doc, 'fields' => IntegrationFields::forConnection($c, $doc['kind'], PurchasingCatalog::fields($doc['kind'])), 'bindings' => $bindings, 'stages' => PurchasingCatalog::STAGES, 'audit' => $audit]);
    }
}
