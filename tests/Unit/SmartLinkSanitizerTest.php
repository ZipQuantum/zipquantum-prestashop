<?php

namespace ZipQuantum\PrestaShop\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZipQuantum\PrestaShop\Security\SmartLinkSanitizer;

final class SmartLinkSanitizerTest extends TestCase
{
    public function testKeepsSafeAggregateAndSvgQr(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h10v10H0z"/></svg>';
        $input = [
            'id' => '42',
            'short_link' => 'https://brand.example/item-42',
            'clicks' => '128',
            'qr' => 'data:image/svg+xml;base64,' . base64_encode($svg),
        ];

        self::assertSame(
            [
                'id' => 42,
                'short_link' => 'https://brand.example/item-42',
                'clicks' => 128,
                'qr' => $input['qr'],
            ],
            SmartLinkSanitizer::sanitize($input)
        );
    }

    public function testRejectsExecutableLinkAndSvgContent(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $result = SmartLinkSanitizer::sanitize([
            'short_link' => 'javascript:alert(1)',
            'clicks' => -5,
            'qr' => 'data:image/svg+xml;base64,' . base64_encode($svg),
        ]);

        self::assertArrayNotHasKey('short_link', $result);
        self::assertArrayNotHasKey('qr', $result);
        self::assertSame(0, $result['clicks']);
    }

    public function testAllowsAnEmbeddedRasterLogoInSvgQr(): void
    {
        $logo = base64_encode("\x89PNG\r\n\x1a\nlogo");
        $svg = '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><clipPath id="crop"><rect width="10" height="10"/></clipPath></defs>'
            . '<image href="data:image/png;base64,' . $logo . '" clip-path="url(#crop)"/>'
            . '</svg>';
        $uri = 'data:image/svg+xml;base64,' . base64_encode($svg);

        self::assertSame($uri, SmartLinkSanitizer::sanitize(['qr' => $uri])['qr']);
    }

    public function testRejectsExternalSvgReferences(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><image href="https://evil.example/pixel"/></svg>';
        $result = SmartLinkSanitizer::sanitize(['qr' => 'data:image/svg+xml;base64,' . base64_encode($svg)]);

        self::assertArrayNotHasKey('qr', $result);
    }

    public function testRejectsExternalCssUrlsInSvg(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><rect fill="url(https://evil.example/fill)"/></svg>';
        $result = SmartLinkSanitizer::sanitize(['qr' => 'data:image/svg+xml;base64,' . base64_encode($svg)]);

        self::assertArrayNotHasKey('qr', $result);
    }

    public function testRejectsCredentialedAndNonHttpsLinks(): void
    {
        foreach (['http://brand.example/x', 'https://user:pass@brand.example/x', '//brand.example/x'] as $url) {
            self::assertArrayNotHasKey('short_link', SmartLinkSanitizer::sanitize(['short_link' => $url]));
        }
    }
}
