<?php
declare(strict_types=1);

use MmpDemo\Controller\RequestController;
use MmpDemo\Database;
use MmpDemo\Exception\ApiException;
use MmpDemo\Repository\RequestRepository;
use MmpDemo\Service\RequestService;

require dirname(__DIR__) . '/vendor/autoload.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (in_array($path, ['/', '/dashboard'], true) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require dirname(__DIR__) . '/templates/' . ($path === '/' ? 'customer' : 'dashboard') . '.php';
    exit;
}
try {
    if (!str_starts_with($path, '/api/')) {
        throw new ApiException(404, 'NOT_FOUND', 'Page not found.');
    }
    $controller = new RequestController(new RequestService(new RequestRepository(Database::connect())));
    $controller->dispatch($_SERVER['REQUEST_METHOD'], $path);
} catch (ApiException $exception) {
    $error = ['code' => $exception->errorCode, 'message' => $exception->getMessage()];
    if ($exception->fields !== []) {
        $error['fields'] = $exception->fields;
    }
    RequestController::json(['error' => $error], $exception->httpStatus);
} catch (Throwable $exception) {
    error_log((string) $exception);
    RequestController::json(['error' => [
        'code' => 'INTERNAL_ERROR', 'message' => 'Unable to process the request. Please try again.',
    ]], 500);
}
