<?php

namespace ZipQuantum\PrestaShop\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZipQuantum\PrestaShop\Support\CanonicalJson;

final class CanonicalJsonTest extends TestCase
{
    public function testHashIsStableAcrossAssociativeKeyOrder(): void
    {
        $first = ['provider' => 'prestashop', 'link' => ['reference' => 'item-1', 'link' => 'https://shop.test/item']];
        $second = ['link' => ['link' => 'https://shop.test/item', 'reference' => 'item-1'], 'provider' => 'prestashop'];

        self::assertSame(CanonicalJson::hash($first), CanonicalJson::hash($second));
    }

    public function testListOrderRemainsSignificant(): void
    {
        self::assertNotSame(CanonicalJson::hash(['a' => [1, 2]]), CanonicalJson::hash(['a' => [2, 1]]));
    }
}

