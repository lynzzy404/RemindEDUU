<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;

$config = require __DIR__ . '/../app/bootstrap.php';
$request = new Request();
$view = new View(__DIR__ . '/../app/Views');
$router = new Router(static function () use ($view): Response {
    return Response::html($view->render('errors/404'), 404);
});

$router->get('/', static function () use ($view): Response {
    return Response::html($view->render('home', ['title' => 'Beranda']));
});

$router->dispatch($request)->send();
