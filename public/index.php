<?php

require dirname(__DIR__) . '/bootstrap/app.php';

use Masterminds\HTML5;
use Tunnela\TremedCodingChallenge\Framework\App;
use Tunnela\TremedCodingChallenge\App\Controllers\AuthController;
use Tunnela\TremedCodingChallenge\App\Controllers\SetupController;
use Tunnela\TremedCodingChallenge\App\Controllers\UsersController;

$app = new App([
    'viewPath' => root_path('resources/views'),
    'database' => [
        'driver' => 'sqlite',
        'file' => root_path('resources/databases/database.sqlite')
    ],
    'nonce' => bin2hex(random_bytes(32))
]);

$app->put('/api/auth/token', [AuthController::class, 'update']);

$app->post('/api/auth/token', [AuthController::class, 'create']);

$app->delete('/api/auth/token', [AuthController::class, 'delete']);

$app->post('/api/auth', [AuthController::class, 'state']);

$app->post('/api/setup', SetupController::class);

$app->post('/api/users', [UsersController::class, 'create'], ['auth' => true]);

$app->get('/api/users', [UsersController::class, 'index'], ['auth' => true]);

$app->delete('/api/users/{id}', [UsersController::class, 'delete'], ['auth' => true]);

$app->put('/api/users/{id}', [UsersController::class, 'update'], ['auth' => true]);

$app->get('/setup', function() {
    return $this->view('setup');
});

$app->get(['/', '/users', '/login'], function() {
    return $this->view('app');
});

// Let's add some headers to improve security
$app->middleware(function($response) {
    header('X-Frame-Options: DENY');
    header('Access-Control-Allow-Origin: ' . getenv('APP_DOMAIN'));

    return $response;
});

// Let's add CSP header
$app->middleware(function($response) {
    if (!$response || $this->isJson($response)) {
        return $response;
    }
    $nonce = $this->options('nonce');

    $defaultSrc = [
        '\'self\'',
        '\'nonce-' . $nonce . '\'',
    ];

    header('Content-Security-Policy: default-src ' . implode(' ', $defaultSrc) . ';');

    $html5 = new HTML5();
    $doc = $html5->loadHTML($response);

    foreach ($doc->getElementsByTagName('script') as $node) {
        $node->setAttribute('nonce', $nonce);
    }
    foreach ($doc->getElementsByTagName('link') as $node) {
        $node->setAttribute('nonce', $nonce);
    }
    return $html5->saveHTML($doc);
});

// Let's check if we already have a super admin user.
// If not, let's redirect to setup page.
$app->middleware(function($response, $database) {
    $hasBeenSetup = true;
    $isSetupRoute = in_array($this->path(), ['/setup', '/api/setup']);

    try {
        $database->query('SELECT id FROM users WHERE id = 1');
    } catch (\Exception $e) {
        $hasBeenSetup = false;
    }
    if (!$hasBeenSetup && !$isSetupRoute) {
        header('Location: /setup');
        exit;
    }
    if ($hasBeenSetup && $this->path() == '/setup') {
        header('Location: /');
        exit;
    }
    return $response;
});

// Auth middleware
$app->middleware(function($response, $route, $database, $data, $headers) {
    if (empty($route['auth'])) {
        return $response;
    }
    $isValid = false;
    $cookieToken = $_COOKIE['access_token'] ?? null;
    $headerToken = isset($headers->Authorization) ? 
        str_replace('Bearer ', '', $headers->Authorization) :
        null;

    // Double sec with http only cookie token and header (local storage) token.
    if (empty($cookieToken) || empty($headerToken) || $cookieToken != $headerToken) {
        return $this->json(['error' => 'Unauthorized'], 401);
    }
    try {
        $statement = $database->prepare(
            'SELECT id FROM auth_tokens WHERE access_token = ?'
        );

        $statement->execute([$cookieToken]);

        $token = $statement->fetch(\PDO::FETCH_ASSOC);

        $isValid = !empty($token);
    } catch (\Exception $e) {
        $isValid = false;
    }
    if (!$isValid) {
        return $this->json(['error' => 'Unauthorized'], 401);
    }
    return $response;
});

$app->run();