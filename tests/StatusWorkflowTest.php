<?php
declare(strict_types=1);

namespace MmpDemo\Tests;

use MmpDemo\Exception\ApiException;
use MmpDemo\Service\StatusWorkflow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StatusWorkflowTest extends TestCase
{
    public static function transitions(): iterable
    {
        $map = [
            'submitted' => ['acknowledged', 'cancelled'],
            'acknowledged' => ['in_progress', 'cancelled'],
            'in_progress' => ['ready', 'cancelled'],
            'ready' => ['completed'], 'completed' => [], 'cancelled' => [],
        ];
        foreach ($map as $from => $allowed) {
            foreach (array_keys($map) as $to) {
                yield "{$from} -> {$to}" => [$from, $to, in_array($to, $allowed, true)];
            }
        }
    }

    #[DataProvider('transitions')]
    public function testTransitionMatrix(string $from, string $to, bool $allowed): void
    {
        if (!$allowed) {
            $this->expectException(ApiException::class);
            $this->expectExceptionMessage("A {$from} request cannot transition to {$to}.");
        }
        StatusWorkflow::assertTransition($from, $to);
        self::assertContains($to, StatusWorkflow::nextStatuses($from));
    }

    public function testUnknownStatusIsRejected(): void
    {
        $this->expectException(ApiException::class);
        StatusWorkflow::validateStatus('unknown');
    }
}
