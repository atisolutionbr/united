<?php
namespace App\Tests\Service;
use App\EventSubscriber\MetabaseAutoLoginSubscriber;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\HttpKernel\{HttpKernelInterface,Event\ResponseEvent};
use PHPUnit\Framework\TestCase;
final class NavigationLatencyTest extends TestCase
{
 public function testNormalNavigationDoesNotInitializeMetabaseDependencies(): void
 {
  $subscriber=(new \ReflectionClass(MetabaseAutoLoginSubscriber::class))->newInstanceWithoutConstructor();
  foreach(['kflow_products','kflow_clients','kflow_erp_connect','united_documents'] as $route) {
   $request=new Request();$request->attributes->set('_route',$route);$response=new Response('ok');
   $event=new ResponseEvent($this->createStub(HttpKernelInterface::class),$request,HttpKernelInterface::MAIN_REQUEST,$response);
   $subscriber->onKernelResponse($event);self::assertSame('ok',$event->getResponse()->getContent());
  }
 }
}
