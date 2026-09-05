<?php

namespace ZipQuantum\PrestaShop\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZipQuantum\PrestaShop\Support\PublicPreviewUrl;

final class PublicPreviewUrlTest extends TestCase
{
    public function testKeepsPublicHttpImages(): void
    {
        self::assertSame(
            'https://shop.example.org/img/product.webp',
            PublicPreviewUrl::sanitize('https://shop.example.org/img/product.webp')
        );
    }

    /** @dataProvider nonPublicUrls */
    public function testOmitsNonPublicImages(string $url): void
    {
        self::assertSame('', PublicPreviewUrl::sanitize($url));
    }

    /** @return iterable<string, array{string}> */
    public static function nonPublicUrls(): iterable
    {
        yield 'loopback' => ['https://127.0.0.1:8089/img/product.jpg'];
        yield 'private ipv4' => ['http://192.168.1.10/image.png'];
        yield 'ipv6 loopback' => ['https://[::1]/image.png'];
        yield 'localhost' => ['http://localhost/image.png'];
        yield 'development suffix' => ['https://shop.test/image.png'];
        yield 'non-http' => ['file:///tmp/image.png'];
        yield 'relative' => ['/img/image.png'];
    }
}
