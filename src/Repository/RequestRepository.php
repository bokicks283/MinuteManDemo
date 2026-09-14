<?php
declare(strict_types=1);

namespace MmpDemo\Repository;

use PDO;
use Throwable;

final class RequestRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function transaction(callable $operation): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $operation();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function findAll(): array
    {
        return $this->pdo->query(
            'SELECT * FROM service_requests ORDER BY created_at DESC, id DESC'
        )->fetchAll();
    }

    public function findById(int $id, bool $forUpdate = false): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM service_requests WHERE id = :id' . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    }

    public function create(array $input): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO service_requests
             (customer_name, customer_email, service_type, quantity, request_description, due_date)
             VALUES (:customer_name, :customer_email, :service_type, :quantity, :request_description, :due_date)'
        );
        $statement->execute($input);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, string $status): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE service_requests SET request_status = :status WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'status' => $status]);
    }

    public function insertHistory(int $id, ?string $old, string $new): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO request_status_history (request_id, old_status, new_status)
             VALUES (:id, :old, :new)'
        );
        $statement->execute(['id' => $id, 'old' => $old, 'new' => $new]);
    }

    public function history(int $id): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM request_status_history WHERE request_id = :id ORDER BY changed_at, id'
        );
        $statement->execute(['id' => $id]);
        return $statement->fetchAll();
    }
}
