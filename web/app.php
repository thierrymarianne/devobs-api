<?php

use Symfony\Component\ClassLoader\ApcClassLoader,
    Symfony\Component\HttpFoundation\Request;

$loader = require_once __DIR__ . '/../app/autoload.php';

$loader = new ApcClassLoader('dashboard', $loader);
$loader->register(true);

require_once __DIR__ . '/../app/AppKernel.php';

$kernel = new AppKernel('prod', false);
$kernel->loadClassCache();

$request  = Request::createFromGlobals();

Request::setTrustedProxies(['127.0.0.1']);

$request->setTrustedHeaderName(Request::HEADER_CLIENT_PROTO, 'X-Proxy-Proto');
$request->setTrustedHeaderName(Request::HEADER_CLIENT_IP, 'X-Proxy-For');
$request->setTrustedHeaderName(Request::HEADER_CLIENT_HOST, 'X-Proxy-Host');

try {
    $response = $kernel->handle($request);
} catch (\Exception $exception) {
    // Errors will be visible in server logs in case of logs having wrong permissions
    error_log($exception->getMessage());
    exit();
}

$response->send();

$kernel->terminate($request, $response);
