<?php
declare(strict_types=1);

namespace MmpDemo\Tests;

use MmpDemo\Exception\ApiException;
use MmpDemo\Service\RequestService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestValidationTest extends TestCase
{
    private const array INPUT = [
        'customer_name' => ' Fictional Customer ', 'customer_email' => 'demo@example.test',
        'service_type' => 'Flyers', 'quantity' => 100,
    ];

    public function testValidInputIsNormalized(): void
    {
        $data = RequestService::validateInput(self::INPUT + ['due_date' => '2028-02-29']);
        self::assertSame('Fictional Customer', $data['customer_name']);
        self::assertSame('2028-02-29', $data['due_date']);
        self::assertSame('', $data['request_description']);
    }

    public function testOptionalDateDefaultsToNull(): void
    {
        self::assertNull(RequestService::validateInput(self::INPUT)['due_date']);
    }

    public static function invalidFields(): iterable
    {
        yield ['customer_name', ' '];
        yield ['customer_email', 'invalid'];
        yield ['service_type', ''];
        yield ['quantity', 0];
        yield ['quantity', 1.5];
        yield ['quantity', '12'];
        yield ['quantity', 4294967296];
        yield ['due_date', '2026-02-29'];
        yield ['due_date', '0000-01-01'];
        yield ['due_date', []];
        yield ['request_description', []];
        yield ['customer_name', str_repeat('x', 101)];
    }

    #[DataProvider('invalidFields')]
    public function testInvalidFieldIsReported(string $field, mixed $value): void
    {
        try {
            RequestService::validateInput(array_replace(self::INPUT, [$field => $value]));
            self::fail('Expected validation failure.');
        } catch (ApiException $exception) {
            self::assertSame(422, $exception->httpStatus);
            self::assertArrayHasKey($field, $exception->fields);
        }
    }
}
