<?php

// Local fixture only. Never configure this endpoint as a real ERP.
if (str_contains($_SERVER['REQUEST_URI'], 'wsdl')) {
    header('Content-Type: text/xml');
    readfile(__DIR__.'/service.wsdl');
    return;
}
class FixtureService
{
    public function Create($request): array { return ['success' => 'P1' === ($request->product ?? '')]; }
}
$server = new SoapServer(__DIR__.'/service.wsdl');
$server->setClass(FixtureService::class);
$server->handle();
