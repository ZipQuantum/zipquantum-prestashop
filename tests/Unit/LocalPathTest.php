<?php

namespace ZipQuantum\PrestaShop\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZipQuantum\PrestaShop\Support\LocalPath;

final class LocalPathTest extends TestCase
{
    /** @return array<string, array{string,string}> */
    public static function paths(): array
    {
        return [
            'normal path' => ['/order', '/order'],
            'missing slash' => ['cart?action=show', '/cart?action=show'],
            'absolute URL' => ['https://evil.test/', '/order'],
            'protocol relative' => ['//evil.test/', '/order'],
            'encoded protocol relative' => ['%2f%2fevil.test/', '/order'],
            'backslash authority' => ['\\evil.test/path', '/order'],
            'encoded backslash authority' => ['%5cevil.test/path', '/order'],
            'traversal' => ['/../admin', '/order'],
            'encoded traversal' => ['/%2e%2e/admin', '/order'],
            'header injection' => ["/order\r\nX-Test: injected", '/order'],
            'empty' => ['', '/order'],
        ];
    }

    #[DataProvider('paths')]
    public function testOnlyLocalPathsAreAccepted(string $input, string $expected): void
    {
        self::assertSame($expected, LocalPath::normalize($input));
    }
}
