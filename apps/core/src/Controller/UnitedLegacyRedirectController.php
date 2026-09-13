<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\{Request, RedirectResponse};
use Symfony\Component\Routing\Attribute\Route;

final class UnitedLegacyRedirectController
{
    #[Route('/kflow', name: 'united_legacy_root', methods: ['GET'])]
    #[Route('/kflow/{path}', name: 'united_legacy_path', requirements: ['path' => '.+'], methods: ['GET'])]
    public function redirect(Request $request, string $path = ''): RedirectResponse
    {
        $query = $request->getQueryString();
        return new RedirectResponse('/unitedati'.('' !== $path ? '/'.$path : '').($query ? '?'.$query : ''), 302);
    }
}
