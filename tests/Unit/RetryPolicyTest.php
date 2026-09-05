<?php

namespace ZipQuantum\PrestaShop\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZipQuantum\PrestaShop\Support\RetryPolicy;

final class RetryPolicyTest extends TestCase
{
    /** @return array<string, array{int,int|null}> */
    public static function attempts(): array
    {
        return [
            'one minute' => [1, 60],
            'five minutes' => [2, 300],
            'thirty minutes' => [3, 1800],
            'two hours' => [4, 7200],
            'twelve hours' => [5, 43200],
            'exhausted' => [6, null],
        ];
    }

    #[DataProvider('attempts')]
    public function testRetrySchedule(int $attempt, ?int $expected): void
    {
        self::assertSame($expected, RetryPolicy::delayForAttempt($attempt));
    }

    public function testRetryAfterSupportsSecondsAndHttpDate(): void
    {
        self::assertSame(17, RetryPolicy::retryAfterSeconds('17', 100));
        self::assertSame(60, RetryPolicy::retryAfterSeconds('invalid', 100));
        self::assertSame(20, RetryPolicy::retryAfterSeconds(gmdate(DATE_RFC7231, 120), 100));
    }

    public function testRetryAfterCannotBypassMaximumAttempts(): void
    {
        self::assertNull(RetryPolicy::delayForAttempt(6, 600));
    }
}
