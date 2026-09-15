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
    public function review(Request $request, KFlowAccess $access, EntityManagerInterface $em, \App\Service\RemoteFormCatalog $remote, ProductQualityReview $review): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$access->can($user, 'products')) throw $this->createAccessDeniedException();
        $c = $em->getRepository(ErpConnection::class)->findOneBy(['company' => $access->activeCompany($user), 'erpName' => $access->activeErp($user), 'isActive' => true]);
        $method = $c?->getConnectionMethod() ?? 'database';
        $settings = $c?->getSettingsForMethod($method) ?? [];
        $binding = $method === 'database' ? \App\Service\FormBindingRegistry::resolve($settings, 'products', $c?->getProductMapping() ?? []) : ($c ? \App\Service\RemoteFormCatalog::binding($c, 'products') : []);
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
                $write = $settings['write_bindings']['products']['update'] ?? [];
                $keys = [];
                if (!empty($write['key_columns'])) foreach (\App\Service\ProcessGateway::keys($write) as $destination) {
                    $sourceKey = array_search($destination, $binding['mapping'] ?? [], true);
                    if ($sourceKey !== false && isset($record['row'][$sourceKey])) $keys[$destination] = $record['row'][$sourceKey];
                }
                return $this->redirectToRoute('united_write', ['form'=>'products','method'=>$method,'action'=>'update','selected'=>['product_name'],'keys'=>$keys,'data'=>['product_name'=>ProductQualityReview::normalizeDescription($record['row']['product_name'],$record['case'])]]);
            } catch (\Throwable $e) { $this->addFlash('warning', 'Não foi possível confirmar a padronização. Atualize a análise e confira o ERP.'); }
            return $this->redirectToRoute('united_product_quality', ['case' => $case]);
        }
        if ($request->query->getBoolean('analyze')) {
            try {
                if ($method === 'database') $result = $review->analyze($settings, $binding, $case);
                elseif ($c) {
                    $page = $remote->list($c, 'products', max(1,$request->query->getInt('page',1)));
                    if ($page['error']) throw new \InvalidArgumentException($page['error']);
                    $result=['count'=>count($page['products']),'duplicates'=>0,'caseCount'=>0,'fiscalCount'=>0,'issues'=>[],'truncated'=>true,'mapping'=>$binding['mapping']??[],'table'=>$page['sourceTable']];
                    foreach ($page['products'] as $product) {
                        $row=['company'=>$product['CodEmp'],'product_code'=>$product['CodPro'],'product_name'=>$product['DesPro'],'ncm'=>$product['Ncm'],'unit'=>$product['UniMed']];
                        foreach (['ibs_rate','cbs_rate','cst_ibs_cbs','cclass_trib','tax_selective'] as $key) $row[$key]=$product['X_'.$key]??'';
                        $messages=ProductQualityReview::fiscalIssues($row,$binding['mapping']??[]);if($messages)++$result['fiscalCount'];
                        $suggestion=ProductQualityReview::normalizeDescription($row['product_name'],$case);$changed=$suggestion!==$row['product_name'];
                        if($changed){++$result['caseCount'];$messages[]='Padronizar descrição';}
                        if($messages)$result['issues'][]=['row'=>$row,'suggestion'=>$suggestion,'messages'=>$messages,'canNormalize'=>$changed];
                    }
                }
            }
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
