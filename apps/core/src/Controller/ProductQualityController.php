<?php

namespace App\Controller;

use App\Entity\{ErpConnection, User};
use App\Service\{KFlowAccess, ProductQualityReview};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ProductQualityController extends AbstractController
{
    #[Route('/unitedati/products/quality', name: 'united_product_quality', methods: ['GET', 'POST'])]
    public function review(Request $request, KFlowAccess $access, EntityManagerInterface $em, ProductQualityReview $review): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$access->can($user, 'products')) throw $this->createAccessDeniedException();
        $c = $em->getRepository(ErpConnection::class)->findOneBy(['company' => $access->activeCompany($user), 'erpName' => $access->activeErp($user), 'isActive' => true]);
        $settings = $c?->getSettingsForMethod('database') ?? [];
        $binding = \App\Service\FormBindingRegistry::resolve($settings, 'products', $c?->getProductMapping() ?? []);
        $case = 'lower' === $request->query->get('case') ? 'lower' : 'upper';
        $error = null; $result = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('product-quality', (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
            $key = (string) $request->request->get('record');
            $records = $request->getSession()->get('united_quality_records', []);
            $record = $records[$key] ?? null;
            unset($records[$key]);
            $request->getSession()->set('united_quality_records', $records);
            if (!$record || $record['connection'] !== $c?->getId() || $record['expires'] < time() || $record['binding'] !== hash('sha256', json_encode($binding))) throw $this->createAccessDeniedException('Atualize a análise antes de aplicar.');
            try {
                $review->normalize($settings, $binding, $record['row'], $record['case']);
                $em->getConnection()->insert('united_process_audit', ['connection_id' => $c->getId(), 'actor_id' => $user->getId(), 'action' => 'product.description.normalized', 'details' => json_encode(['company' => $record['row']['company'], 'code' => $record['row']['product_code'], 'before' => $record['row']['product_name'], 'after' => ProductQualityReview::normalizeDescription($record['row']['product_name'], $record['case'])], JSON_THROW_ON_ERROR)]);
                $this->addFlash('success', 'Descrição padronizada no ERP.');
            } catch (\Throwable $e) { $this->addFlash('warning', 'Não foi possível confirmar a padronização. Atualize a análise e confira o ERP.'); }
            return $this->redirectToRoute('united_product_quality', ['case' => $case]);
        }
        if ($request->query->getBoolean('analyze')) {
            try { $result = $review->analyze($settings, $binding, $case); }
            catch (\InvalidArgumentException $e) { $error = $e->getMessage(); }
            catch (\Throwable $e) { $error = 'Não foi possível analisar o cadastro. Confira a conexão e os campos vinculados.'; }
        }
        $records = [];
        if ($result) foreach ($result['issues'] as &$issue) {
            $issue['token'] = bin2hex(random_bytes(24));
            $records[$issue['token']] = ['connection' => $c->getId(), 'row' => $issue['row'], 'case' => $case, 'binding' => hash('sha256', json_encode($binding)), 'expires' => time() + 900];
        }
        $request->getSession()->set('united_quality_records', $records);
        return $this->render('kflow/products_quality.html.twig', ['result' => $result, 'error' => $error, 'case' => $case, 'erp' => $c?->getErpName()]);
    }
}
