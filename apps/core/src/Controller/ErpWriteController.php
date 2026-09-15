<?php
namespace App\Controller;
use App\Entity\{ErpConnection,User};
use App\Service\{KFlowAccess,ErpConnectionProfile,IntegrationFields,ProcessGateway};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/unitedati/erp-write')]
final class ErpWriteController extends AbstractController
{
 public function __construct(private readonly EntityManagerInterface $em,private readonly KFlowAccess $access,private readonly ErpConnectionProfile $profile,private readonly ProcessGateway $gateway) {}
 private function connection(string $form, bool $configure=false): ErpConnection
 {
  $menu=['products'=>'products','customers'=>'clients','suppliers'=>'suppliers','carriers'=>'carriers'][$form] ?? null;
  $u=$this->getUser(); if (!$menu || !$u instanceof User || !$this->access->can($u,$configure?'connections':$menu)) throw $this->createAccessDeniedException();
  $c=$this->em->getRepository(ErpConnection::class)->findOneBy(['company'=>$this->access->activeCompany($u),'erpName'=>$this->access->activeErp($u),'isActive'=>true]);
  if (!$c) throw $this->createNotFoundException('Selecione o ERP ativo.'); return $c;
 }
 private function fields(ErpConnection $c,string $form): array { return IntegrationFields::forConnection($c,$form,$this->profile->databaseForms()[$form]['fields']); }
 private function check(Request $r,string $id): void { if (!$this->isCsrfTokenValid($id,(string)$r->request->get('_token'))) throw $this->createAccessDeniedException('Atualize a página.'); }
 #[Route('/{form}/configure',name:'united_write_configure',methods:['GET','POST'])]
 public function configure(string $form,Request $r): Response
 {
  $c=$this->connection($form,true);$action=(string)$r->query->get('action','update');
  if (!in_array($action,['insert','update','delete'],true)) throw $this->createNotFoundException();
  $method=(string)$r->query->get('method',$c->getConnectionMethod() ?? 'database');
  if (!in_array($method,['database','api','webservice'],true)) throw $this->createNotFoundException();
  $settings=$c->getSettingsForMethod($method);$config=$settings['write_bindings'][$form][$action] ?? ['method'=>$method,'action'=>$action,'mapping'=>[]];$error=null;
  if ($r->isMethod('POST')) {
   $this->check($r,'write-config-'.$form.'-'.$method.'-'.$action);
   try {
    $config=$r->request->all('config');$config['method']=$method;$config['action']=$action;
    $config['mapping']=array_intersect_key($config['mapping'] ?? [],$this->fields($c,$form));
    $config=$this->gateway->validateWrite($config);
    $settings['write_bindings'][$form][$action]=$config;$c->setSettingsForMethod($method,$settings);$this->em->flush();
    $this->addFlash('success','Vínculo de gravação salvo. Nenhum registro foi alterado no ERP.');
    return $this->redirectToRoute('united_write_configure',['form'=>$form,'method'=>$method,'action'=>$action]);
   } catch (\InvalidArgumentException $e) {$error=$e->getMessage();}
  }
  return $this->render('kflow/write/configure.html.twig',['form'=>$form,'title'=>$this->profile->databaseForms()[$form]['label'],'config'=>$config,'method'=>$method,'action'=>$action,'fields'=>$this->fields($c,$form),'error'=>$error]);
 }
 #[Route('/{form}',name:'united_write',methods:['GET','POST'])]
 public function edit(string $form,Request $r): Response
 {
  $c=$this->connection($form);$method=(string)$r->query->get('method',$c->getConnectionMethod() ?? 'database');$action=(string)$r->query->get('action','update');
  if (!in_array($method,['database','api','webservice'],true)||!in_array($action,['insert','update','delete'],true)) throw $this->createNotFoundException();
  $config=$c->getSettingsForMethod($method)['write_bindings'][$form][$action] ?? null;$error=null;$input=$r->query->all('data');$keys=$r->query->all('keys');
  if ($r->isMethod('POST')) {
   $this->check($r,'write-'.$form);$input=$r->request->all('data');$keys=$r->request->all('keys');
   try {
    if (!$config) throw new \InvalidArgumentException('Configure o vínculo desta operação antes de continuar.');
    $config=$this->gateway->validateWrite($config);$data=[];
    foreach ($r->request->all('selected') as $field) {
     if (!is_string($field)||!isset($config['mapping'][$field])||!is_scalar($input[$field] ?? null)) throw new \InvalidArgumentException('Seleção de campo inválida.');
     $data[$field]=mb_substr((string)$input[$field],0,4000);
    }
    if ($action!=='delete'&&!$data) throw new \InvalidArgumentException('Selecione os campos que serão gravados.');
    if ($action==='delete') $data=[];
    $expected=$action==='insert'?[]:ProcessGateway::keys($config);
    if (array_diff($expected,array_keys($keys))||array_diff(array_keys($keys),$expected)) throw new \InvalidArgumentException('Preencha a chave completa.');
    foreach ($keys as $v) if (!is_scalar($v)||trim((string)$v)==='') throw new \InvalidArgumentException('Chave vazia.');
    $db=$this->em->getConnection();
    $id=$db->fetchOne('INSERT INTO united_erp_write(connection_id,actor_id,form,config,payload,record_keys,request_key) VALUES (?,?,?,?,?,?,?) RETURNING id',[$c->getId(),$this->getUser()->getId(),$form,json_encode($config,JSON_THROW_ON_ERROR),json_encode($data,JSON_THROW_ON_ERROR),json_encode($keys,JSON_THROW_ON_ERROR),bin2hex(random_bytes(24))]);
    return $this->redirectToRoute('united_write_review',['form'=>$form,'id'=>$id]);
   } catch (\InvalidArgumentException $e) {$error=$e->getMessage();}
  }
  $history=$this->em->getConnection()->fetchAllAssociative('SELECT id,status,created_at FROM united_erp_write WHERE connection_id=? AND form=? AND actor_id=? ORDER BY id DESC LIMIT 15',[$c->getId(),$form,$this->getUser()->getId()]);
  return $this->render('kflow/write/edit.html.twig',['form'=>$form,'title'=>$this->profile->databaseForms()[$form]['label'],'method'=>$method,'action'=>$action,'config'=>$config,'fields'=>$this->fields($c,$form),'input'=>$input,'selected'=>$r->isMethod('POST')?$r->request->all('selected'):$r->query->all('selected'),'keys'=>$keys,'keyFields'=>$config&&$action!=='insert'?ProcessGateway::keys($config):[],'error'=>$error,'history'=>$history]);
 }
 #[Route('/{form}/review/{id}',name:'united_write_review',requirements:['id'=>'\d+'],methods:['GET','POST'])]
 public function review(string $form,int $id,Request $r): Response
 {
  $c=$this->connection($form);$db=$this->em->getConnection();$row=$db->fetchAssociative('SELECT * FROM united_erp_write WHERE id=? AND connection_id=? AND actor_id=? AND form=?',[$id,$c->getId(),$this->getUser()->getId(),$form]);
  if (!$row) throw $this->createNotFoundException();
  foreach (['config','payload','record_keys'] as $key) $row[$key]=json_decode($row[$key],true,512,JSON_THROW_ON_ERROR);
  if ($r->isMethod('POST')) {
   $this->check($r,'write-review-'.$id);
   $current=$c->getSettingsForMethod($row['config']['method'])['write_bindings'][$form][$row['config']['action']] ?? [];
   if ($current!==$row['config']) { $this->addFlash('warning','O vínculo mudou. Prepare uma nova revisão antes de enviar.'); return $this->redirectToRoute('united_write',['form'=>$form]); }
   if (1!==$db->executeStatement("UPDATE united_erp_write SET status='sending' WHERE id=? AND status='review'",[$id])) { $this->addFlash('warning','Esta operação já foi enviada. Confira seu status.');return $this->redirectToRoute('united_write_review',['form'=>$form,'id'=>$id]); }
   try {
    $receipt=$this->gateway->write($c,$row['config'],$row['payload'],$row['record_keys'],$row['request_key']);
    $db->update('united_erp_write',['status'=>'confirmed','receipt'=>$receipt],['id'=>$id]);
    $c->updateTimestamp();$this->em->flush();
    $this->addFlash('success',$receipt);
   } catch (\Throwable $e) {
    $db->update('united_erp_write',['status'=>'unconfirmed','receipt'=>'Sem confirmação. Confira no ERP antes de preparar uma nova operação.'],['id'=>$id]);
    $this->addFlash('warning','Não houve confirmação. A operação não será repetida automaticamente. Confira o ERP.');
   }
   return $this->redirectToRoute('united_write_review',['form'=>$form,'id'=>$id]);
  }
  return $this->render('kflow/write/review.html.twig',['form'=>$form,'row'=>$row,'fields'=>$this->fields($c,$form),'erp'=>$c->getErpName()]);
 }
}
