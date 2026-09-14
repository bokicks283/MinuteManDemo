<?php
declare(strict_types=1);

namespace MmpDemo\Service;

use DateTimeImmutable;
use MmpDemo\Exception\ApiException;
use MmpDemo\Repository\RequestRepository;

final class RequestService
{
    public function __construct(private readonly RequestRepository $repository) {}

    public function all(): array
    {
        return array_map($this->present(...), $this->repository->findAll());
    }

    public function get(int $id, bool $forUpdate = false): array
    {
        $request = $this->repository->findById($id, $forUpdate);
        if ($request === null) {
            throw new ApiException(404, 'REQUEST_NOT_FOUND', 'Request not found.');
        }
        return $this->present($request);
    }

    public function history(int $id): array
    {
        $this->get($id);
        return $this->repository->history($id);
    }

    public function create(array $input): array
    {
        $data = self::validateInput($input);
        return $this->repository->transaction(function () use ($data): array {
            $id = $this->repository->create($data);
            $this->repository->insertHistory($id, null, 'submitted');
            return $this->get($id);
        });
    }

    public function changeStatus(int $id, mixed $status): array
    {
        $status = StatusWorkflow::validateStatus($status);
        return $this->repository->transaction(function () use ($id, $status): array {
            // Lock before checking: concurrent clients must validate against the latest status.
            $request = $this->get($id, true);
            StatusWorkflow::assertTransition($request['request_status'], $status);
            $this->repository->updateStatus($id, $status);
            $this->repository->insertHistory($id, $request['request_status'], $status);
            return $this->get($id);
        });
    }

    public static function validateInput(array $input): array
    {
        $errors = [];
        $data = [];
        foreach (['customer_name' => 100, 'customer_email' => 255, 'service_type' => 50] as $field => $max) {
            $value = $input[$field] ?? null;
            if (!is_string($value) || trim($value) === '' || mb_strlen($value) > $max) {
                $errors[$field] = "Required text, maximum {$max} characters.";
            } else {
                $data[$field] = trim($value);
            }
        }
        if (isset($data['customer_email']) && !filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['customer_email'] = 'Enter a valid email address.';
        }
        $quantity = $input['quantity'] ?? null;
        if (!is_int($quantity) || $quantity < 1 || $quantity > 4294967295) {
            $errors['quantity'] = 'Quantity must be an integer between 1 and 4294967295.';
        }
        $data['quantity'] = $quantity;
        $description = $input['request_description'] ?? '';
        if (!is_string($description) || strlen($description) > 65535) {
            $errors['request_description'] = 'Description must be text of at most 65535 bytes.';
        }
        $data['request_description'] = $description;
        $due = $input['due_date'] ?? null;
        if ($due === '') {
            $due = null;
        }
        if ($due !== null) {
            $date = is_string($due) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $due) ? DateTimeImmutable::createFromFormat('!Y-m-d', $due) : false;
            if (!$date || $date->format('Y-m-d') !== $due || $due < '1000-01-01') {
                $errors['due_date'] = 'Use a valid date in YYYY-MM-DD format (year 1000 or later).';
            }
        }
        $data['due_date'] = $due;
        if ($errors !== []) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Please correct the submitted fields.', $errors);
        }
        return $data;
    }

    private function present(array $request): array
    {
        $request['allowed_statuses'] = StatusWorkflow::nextStatuses($request['request_status']);
        return $request;
    }
}
