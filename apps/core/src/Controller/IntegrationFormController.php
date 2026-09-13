<?php

namespace App\Controller;

use App\Entity\{ErpConnection, User};
use App\Service\{KFlowAccess, ErpConnectionProfile, IntegrationFields, DatabaseSchemaInspector, ProcessGateway};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class IntegrationFormController extends AbstractController
{
    #[Route('/unitedati/forms/{form}', name: 'united_integration_form', requirements: ['form' => 'form-[a-f0-9]+'], methods: ['GET'])]
    public function view(string $form, Request $request, KFlowAccess $access, EntityManagerInterface $em, ErpConnectionProfile $profile, DatabaseSchemaInspector $schema): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) throw $this->createAccessDeniedException();
        $c = $em->getRepository(ErpConnection::class)->findOneBy(['company' => $access->activeCompany($user), 'erpName' => $access->activeErp($user), 'isActive' => true]);
        $settings = $c?->getSettingsForMethod('database') ?? [];
        $definition = null;
        foreach ($settings['form_catalog'] ?? [] as $item) if (($item['id'] ?? '') === $form) $definition = $item;
        if (!$definition) throw $this->createNotFoundException();
        $menu = match ($definition['template']) { 'customers' => 'clients', 'requests' => 'solicitation', 'requisitions' => 'requisition', default => $definition['template'] };
        if (!$access->can($user, $menu)) throw $this->createAccessDeniedException();
        $fields = IntegrationFields::forForm($settings, $form, $profile->databaseForms()[$definition['template']]['fields']);
        $binding = $settings['bindings'][$form] ?? [];
        $rows = []; $total = 0; $error = null; $page = max(1, $request->query->getInt('page', 1));
        try {
            $driver = strtolower($settings['driver'] ?? 'sqlserver');
            $quote = static function (string $name) use ($driver): string {
                ProcessGateway::identifier($name);
                return implode('.', array_map(static fn ($p) => match ($driver) { 'sqlserver', 'sql server' => '['.$p.']', 'mysql', 'mariadb' => '`'.$p.'`', default => '"'.$p.'"' }, explode('.', $name)));
            };
            $table = $quote($binding['table'] ?? '');
            $available = $schema->columns($settings, $binding['table'])['columns'];
            $selects = []; $order = [];
            foreach ($fields as $key => $field) {
                $column = $binding['mapping'][$key] ?? '';
                if ($column && in_array($column, $available, true)) { $selects[] = $quote($column).' AS '.$quote($key); $order[] = $quote($column); }
            }
            if (!$selects) throw new \InvalidArgumentException('Vincule os campos deste formulário para consultar seus registros.');
            $pdo = $schema->open($settings);
            $total = (int) $pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
            $offset = ($page - 1) * 15;
            $sql = 'SELECT '.implode(', ', $selects).' FROM '.$table.' ORDER BY '.$order[0];
            $sql .= in_array($driver, ['postgresql', 'mysql', 'mariadb'], true) ? ' LIMIT 15 OFFSET '.$offset : ' OFFSET '.$offset.' ROWS FETCH NEXT 15 ROWS ONLY';
            $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\InvalidArgumentException $e) { $error = $e->getMessage(); }
        catch (\Throwable $e) { $error = 'Não foi possível consultar a origem deste formulário. Confira a conexão e os campos vinculados.'; }
        return $this->render('kflow/integration_form.html.twig', ['definition' => $definition, 'fields' => $fields, 'binding' => $binding, 'rows' => $rows, 'page' => $page, 'total' => $total, 'error' => $error, 'erp' => $c->getErpName()]);
    }
}
