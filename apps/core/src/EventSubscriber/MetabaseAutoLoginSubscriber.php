<?php

namespace App\EventSubscriber;

use App\Service\MetabaseSessionBridge;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class MetabaseAutoLoginSubscriber implements EventSubscriberInterface
{
    private const SESSION_KEY = 'kflow_metabase_session_issued';

    public function __construct(
        private readonly Security $security,
        private readonly MetabaseSessionBridge $metabaseSessionBridge,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(METABASE_COOKIE_DOMAIN)%')]
        private readonly string $cookieDomain,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || $event->getRequest()->attributes->get('_route') !== 'kflow_metabase' || null === $this->security->getUser()) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        if (!str_starts_with($route, 'kflow_') || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $isMetabaseLaunch = 'kflow_metabase' === $route;
        if ((!$isMetabaseLaunch && $session->get(self::SESSION_KEY)) || !$this->metabaseSessionBridge->isConfigured()) {
            return;
        }

        $metabaseSessionId = $this->metabaseSessionBridge->createSession();
        if (null === $metabaseSessionId) {
            return;
        }

        $cookie = Cookie::create('metabase.SESSION', $metabaseSessionId)
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);

        if ('' !== trim($this->cookieDomain)) {
            $cookie = $cookie->withDomain($this->cookieDomain);
        }

        $event->getResponse()->headers->setCookie($cookie);
        $session->set(self::SESSION_KEY, true);
    }

    public function onLogout(LogoutEvent $event): void
    {
        $response = new RedirectResponse($this->urlGenerator->generate('security_login'));
        $response->headers->clearCookie(
            'metabase.SESSION',
            '/',
            '' !== trim($this->cookieDomain) ? $this->cookieDomain : null,
        );

        $event->setResponse($response);
    }
}
