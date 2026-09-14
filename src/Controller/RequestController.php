<?php
declare(strict_types=1);

namespace MmpDemo\Controller;

use JsonException;
use MmpDemo\Exception\ApiException;
use MmpDemo\Service\RequestService;

final class RequestController
{
    public function __construct(private readonly RequestService $service) {}

    public function dispatch(string $method, string $path): void
    {
        if (!preg_match('#^/api/requests(?:/([^/]+)(?:/(status|history))?)?$#D', $path, $matches)) {
            throw new ApiException(404, 'NOT_FOUND', 'Endpoint not found.');
        }
        $id = isset($matches[1]) ? filter_var($matches[1], FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]) : null;
        if ($id === false) {
            throw new ApiException(422, 'INVALID_ID', 'Request ID must be a positive integer.');
        }
        $action = $matches[2] ?? '';
        $allowed = $id === null ? ['GET', 'POST'] : ($action === 'status' ? ['PATCH'] : ['GET']);
        if (!in_array($method, $allowed, true)) {
            header('Allow: ' . implode(', ', $allowed));
            throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Method not allowed for this endpoint.');
        }
        if ($method === 'POST') {
            $result = $this->service->create($this->body());
            header('Location: /api/requests/' . $result['id']);
            self::json(['data' => $result], 201);
        } elseif ($method === 'PATCH') {
            self::json(['data' => $this->service->changeStatus($id, $this->body()['status'] ?? null)]);
        } else {
            $result = $id === null ? $this->service->all()
                : ($action === 'history' ? $this->service->history($id) : $this->service->get($id));
            self::json(['data' => $result]);
        }
    }

    private function body(): array
    {
        if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
            throw new ApiException(415, 'UNSUPPORTED_MEDIA_TYPE', 'Use Content-Type: application/json.');
        }
        $raw = file_get_contents('php://input', false, null, 0, 1048577);
        if (strlen($raw) > 1048576) {
            throw new ApiException(413, 'BODY_TOO_LARGE', 'JSON body must not exceed 1 MiB.');
        }
        try {
            $body = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException(400, 'INVALID_JSON', 'Body must contain valid JSON.');
        }
        if (!$body instanceof \stdClass) {
            throw new ApiException(400, 'INVALID_JSON', 'Body must be a JSON object.');
        }
        return (array) $body;
    }

    public static function json(array $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($body, JSON_THROW_ON_ERROR);
    }
}
