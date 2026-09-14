<?php
declare(strict_types=1);

namespace MmpDemo\Service;

use MmpDemo\Exception\ApiException;

final class StatusWorkflow
{
    private const array TRANSITIONS = [
        'submitted' => ['acknowledged', 'cancelled'],
        'acknowledged' => ['in_progress', 'cancelled'],
        'in_progress' => ['ready', 'cancelled'],
        'ready' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public static function nextStatuses(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    public static function validateStatus(mixed $status): string
    {
        if (!is_string($status) || !array_key_exists($status, self::TRANSITIONS)) {
            throw new ApiException(422, 'INVALID_STATUS', 'A recognized status is required.');
        }
        return $status;
    }

    public static function assertTransition(string $from, string $to): void
    {
        self::validateStatus($to);
        if (!in_array($to, self::nextStatuses($from), true)) {
            throw new ApiException(409, 'INVALID_STATUS_TRANSITION',
                "A {$from} request cannot transition to {$to}.");
        }
    }
}
