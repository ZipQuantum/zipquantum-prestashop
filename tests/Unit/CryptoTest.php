<?php

namespace ZipQuantum\PrestaShop\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZipQuantum\PrestaShop\Security\Crypto;

final class CryptoTest extends TestCase
{
    public function testRoundTripAndTamperDetection(): void
    {
        $crypto = new Crypto('unit-test-installation-key');
        $payload = ['access_token' => 'access', 'refresh_token' => 'refresh', 'installation_id' => 'uuid'];
        $encrypted = $crypto->encrypt($payload);

        self::assertStringStartsWith('zqps1:', $encrypted);
        self::assertSame($payload, $crypto->decrypt($encrypted));

        $last = substr($encrypted, -1);
        $tampered = substr($encrypted, 0, -1) . ($last === 'A' ? 'B' : 'A');
        self::assertNull($crypto->decrypt($tampered));
    }

    public function testWrongInstallationKeyCannotDecrypt(): void
    {
        $payload = (new Crypto('shop-a'))->encrypt(['token' => 'secret']);
        self::assertNull((new Crypto('shop-b'))->decrypt($payload));
    }
}

