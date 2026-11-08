<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\TaskController;
use App\Core\Csrf;
use App\Core\JsonStore;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Repositories\CategoryRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;

$config = require __DIR__ . '/../app/bootstrap.php';

/* ── Dependencies ────────────────────────────────────────────── */
$request = new Request();
$session = new Session($config);
$logger = new Logger('security.log');
$rateLimiter = new RateLimiter(
    (int) ($config['login_max_attempts'] ?? 5),
    900
);
$userRepository = new UserRepository($config['data_path']);
$categoryRepository = new CategoryRepository($config['data_path']);
$taskRepository = new TaskRepository($config['data_path']);

/* ── View factory ───────────────────────────────────────────── */
$viewFactory = static fn (): View => new View(__DIR__ . '/../app/Views');

/* ── Auth middleware helpers ──────────────────────────────────── */
$requireAuth = static function () use ($session): void {
    if ($session->get('user_id') === null) {
        Response::redirect('/login')->send();
        exit;
    }
};

$requireGuest = static function () use ($session, $viewFactory, $request): void {
    if ($session->get('user_id') !== null) {
        Response::redirect('/tasks')->send();
        exit;
    }
};

/* ── Router ─────────────────────────────────────────────────── */
$router = new Router(static function () use ($viewFactory): Response {
    return Response::html($viewFactory()->render('errors/404'), 404);
});

/* Public routes */
$router->get('/', static function () use ($session, $viewFactory, $request): Response {
    if ($session->get('user_id') !== null) {
        return Response::redirect('/tasks');
    }

    return Response::html($viewFactory()->render('home', ['title' => 'Beranda']));
});

/* Auth routes */
$authControllerFactory = static function () use ($request, $userRepository, $categoryRepository, $rateLimiter, $logger, $session): AuthController {
    return new AuthController(
        $request,
        $userRepository,
        $categoryRepository,
        $rateLimiter,
        $logger,
        $session
    );
};

$router->get('/login', static function () use ($authControllerFactory, $requireGuest): Response {
    $requireGuest();

    return (new AuthControllerProxy($authControllerFactory()))->showLogin();
});

$router->post('/login', static function () use ($authControllerFactory, $requireGuest): Response {
    $requireGuest();

    return (new AuthControllerProxy($authControllerFactory()))->handleLogin();
});

$router->get('/register', static function () use ($authControllerFactory, $requireGuest): Response {
    $requireGuest();

    return (new AuthControllerProxy($authControllerFactory()))->showRegister();
});

$router->post('/register', static function () use ($authControllerFactory, $requireGuest): Response {
    $requireGuest();

    return (new AuthControllerProxy($authControllerFactory()))->handleRegister();
});

$router->post('/logout', static function () use ($authControllerFactory, $requireAuth): Response {
    $requireAuth();

    return (new AuthControllerProxy($authControllerFactory()))->logout();
});

$taskControllerFactory = static function () use ($request, $taskRepository, $categoryRepository, $logger, $session): TaskController {
    return new TaskController($request, $taskRepository, $categoryRepository, $logger, $session);
};

$router->get('/tasks', static function () use ($taskControllerFactory, $requireAuth): Response {
    $requireAuth();
    return $taskControllerFactory()->index();
});

$router->get('/tasks/create', static function () use ($taskControllerFactory, $requireAuth): Response {
    $requireAuth();
    return $taskControllerFactory()->createForm();
});

$router->post('/tasks', static function () use ($taskControllerFactory, $requireAuth): Response {
    $requireAuth();
    return $taskControllerFactory()->create();
});

$router->get('/tasks/{id}/edit', static function (Request $request, array $parameters) use ($taskControllerFactory, $requireAuth): Response {
    $requireAuth();
    return $taskControllerFactory()->editForm($parameters['id']);
});

$router->post('/tasks/{id}/update', static function (Request $request, array $parameters) use ($taskControllerFactory, $requireAuth): Response {
    $requireAuth();
    return $taskControllerFactory()->update($parameters['id']);
});

$router->post('/tasks/{id}/toggle', static function (Request $request, array $parameters) use ($taskControllerFactory, $requireAuth): Response {
    $requireAuth();
    return $taskControllerFactory()->toggle($parameters['id']);
});

$router->post('/tasks/{id}/delete', static function (Request $request, array $parameters) use ($taskControllerFactory, $requireAuth): Response {
    $requireAuth();
    return $taskControllerFactory()->delete($parameters['id']);
});

$categoryControllerFactory = static function () use ($request, $categoryRepository, $logger, $session): CategoryController {
    return new CategoryController($request, $categoryRepository, $logger, $session);
};

$router->get('/categories', static function () use ($categoryControllerFactory, $requireAuth): Response {
    $requireAuth();
    return $categoryControllerFactory()->index();
});

$router->post('/categories', static function () use ($categoryControllerFactory, $requireAuth): Response {
    $requireAuth();
    return $categoryControllerFactory()->create();
});

$router->post('/categories/{id}/update', static function (Request $request, array $parameters) use ($categoryControllerFactory, $requireAuth): Response {
    $requireAuth();
    return $categoryControllerFactory()->update($parameters['id']);
});

$router->post('/categories/{id}/delete', static function (Request $request, array $parameters) use ($categoryControllerFactory, $requireAuth): Response {
    $requireAuth();
    return $categoryControllerFactory()->delete($parameters['id']);
});

/* Import route stub */
$router->get('/import', static function () use ($session): Response {
    if ($session->get('user_id') === null) {
        Response::redirect('/login')->send();
        exit;
    }

    return Response::redirect('/tasks');
});

/* Export route stub */
$router->get('/export/xlsx', static function () use ($session): Response {
    if ($session->get('user_id') === null) {
        Response::redirect('/login')->send();
        exit;
    }

    return Response::redirect('/tasks');
});

$router->dispatch($request)->send();

/**
 * Proxy that maps controller method names to AuthController methods.
 * This bridges the closure-based router (returning Response) with
 * the controller (which also returns Response).
 *
 * @internal
 */
final class AuthControllerProxy
{
    public function __construct(private readonly AuthController $controller)
    {
    }

    public function showLogin(): Response
    {
        return $this->controller->showLogin();
    }

    public function handleLogin(): Response
    {
        return $this->controller->handleLogin();
    }

    public function showRegister(): Response
    {
        return $this->controller->showRegister();
    }

    public function handleRegister(): Response
    {
        return $this->controller->handleRegister();
    }

    public function logout(): Response
    {
        return $this->controller->logout();
    }
}
