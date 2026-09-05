<?php

namespace ZipQuantum\PrestaShop\Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class MarketplaceContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__);
    }

    public function testTechnicalIdentityAndCompatibilityAreFrozen(): void
    {
        $main = file_get_contents($this->root . '/zipquantum.php');
        self::assertIsString($main);
        self::assertStringContainsString("\$this->name = 'zipquantum'", $main);
        self::assertStringContainsString("'min' => '8.1.0'", $main);
        self::assertStringContainsString("'max' => '9.99.99'", $main);
        self::assertStringContainsString("\$this->version = '1.0.0'", $main);
    }

    public function testPrivacyAndNoRemoteDeletionContractIsPresent(): void
    {
        $php = $this->allSource('.php');
        self::assertStringNotContainsString("request('DELETE'", $php);
        self::assertStringNotContainsString('CURLOPT_CUSTOMREQUEST => \'DELETE\'', $php);
        self::assertStringNotContainsString('unserialize(', $php);
        self::assertStringNotContainsString('serialize(', $php);
        self::assertStringNotContainsString('eval(', $php);

        $spec = file_get_contents($this->root . '/docs/MVP_1_0_SPEC.md');
        self::assertIsString($spec);
        self::assertStringContainsString('No storefront JavaScript', $spec);
        self::assertStringContainsString('never calls a remote link deletion endpoint', $spec);
    }

    public function testProviderAndIdempotencyPrefixMatchContract(): void
    {
        $source = file_get_contents($this->root . '/src/Service/SyncService.php');
        $payload = file_get_contents($this->root . '/src/Domain/ObjectPayloadFactory.php');
        self::assertIsString($source);
        self::assertIsString($payload);
        self::assertStringContainsString("'ps'", $source);
        self::assertStringContainsString("'provider' => 'prestashop'", $payload);
    }

    public function testQueueRetriesResetTheirAttemptBudget(): void
    {
        $source = file_get_contents($this->root . '/src/Repository/QueueRepository.php');
        self::assertIsString($source);
        self::assertGreaterThanOrEqual(2, substr_count($source, "'attempts' => 0"));
    }

    public function testRemoteDisplayValuesAreSanitizedBeforeStorage(): void
    {
        $sync = file_get_contents($this->root . '/src/Service/SyncService.php');
        $associations = file_get_contents($this->root . '/src/Repository/AssociationRepository.php');
        self::assertIsString($sync);
        self::assertIsString($associations);
        self::assertStringContainsString('SmartLinkSanitizer::sanitize', $sync);
        self::assertStringContainsString('SmartLinkSanitizer::sanitize', $associations);
    }

    public function testPrestaShopNineDoesNotUseTheLegacyGlobalContext(): void
    {
        $php = $this->allSource('.php');
        self::assertStringNotContainsString('Context::getContext()', $php);
        self::assertStringNotContainsString("class_exists('Context')", $php);
    }

    public function testRequiredSecurityFilesAndFolderIndexesExist(): void
    {
        self::assertFileExists($this->root . '/.htaccess');
        self::assertFileExists($this->root . '/header_csp.txt');
        foreach ($this->directories() as $directory) {
            self::assertFileExists($directory . '/index.php', 'Missing index.php in ' . $directory);
        }
    }

    public function testBackOfficeJavascriptWaitsForDomAndOpensOauthPopupDuringUserActivation(): void
    {
        $javascript = file_get_contents($this->root . '/views/js/admin.js');
        self::assertIsString($javascript);
        self::assertStringContainsString("document.addEventListener('DOMContentLoaded', initialize)", $javascript);
        $openPosition = strpos($javascript, "window.open('', 'zipquantum-oauth'");
        $requestPosition = strpos($javascript, "call('oauthStart'");
        self::assertIsInt($openPosition);
        self::assertIsInt($requestPosition);
        self::assertLessThan($requestPosition, $openPosition);
        self::assertStringNotContainsString('fingerprint', strtolower($javascript));
    }

    public function testAjaxControllerRequiresAnAuthenticatedEmployeeAndUsesSignedAdminLink(): void
    {
        $controller = file_get_contents($this->root . '/controllers/admin/AdminZipquantumAjaxController.php');
        $module = file_get_contents($this->root . '/zipquantum.php');
        self::assertIsString($controller);
        self::assertIsString($module);
        self::assertStringContainsString('$this->context->employee', $controller);
        self::assertStringContainsString("getAdminLink('AdminZipquantumAjax')", $module);
    }

    public function testAnalyticsRefreshPreservesLocallyCachedQrData(): void
    {
        $repository = file_get_contents($this->root . '/src/Repository/AssociationRepository.php');
        self::assertIsString($repository);
        self::assertStringContainsString('array_replace($currentLink, $remoteLink)', $repository);
    }

    public function testEveryExecutablePhpSourceHasContextProtection(): void
    {
        foreach ($this->phpFiles() as $file) {
            $relative = str_replace('\\', '/', substr($file, strlen($this->root) + 1));
            if ($relative === 'index.php' || str_ends_with($relative, '/index.php')
                || str_starts_with($relative, 'tests/') || str_starts_with($relative, 'bin/')) {
                continue;
            }
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertMatchesRegularExpression(
                '/defined\([\'\"]_PS_VERSION_[\'\"]\)/',
                $contents,
                'Missing PrestaShop context protection in ' . $relative
            );
        }
    }

    /** @return array<int, string> */
    private function directories(): array
    {
        $directories = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if (!$item->isDir() || str_contains($path, DIRECTORY_SEPARATOR . '.git')
                || str_contains($path, DIRECTORY_SEPARATOR . '.phpunit.cache')
                || str_contains($path, DIRECTORY_SEPARATOR . 'vendor')
                || str_contains($path, DIRECTORY_SEPARATOR . 'dist')
                || str_contains($path, DIRECTORY_SEPARATOR . 'marketplace-assets')
                || str_contains($path, DIRECTORY_SEPARATOR . 'tmp')
                || str_contains($path, DIRECTORY_SEPARATOR . 'tests')) {
                continue;
            }
            $directories[] = $path;
        }

        return $directories;
    }

    /** @return array<int, string> */
    private function phpFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($item->isFile() && $item->getExtension() === 'php'
                && !str_contains($path, DIRECTORY_SEPARATOR . 'vendor')
                && !str_contains($path, DIRECTORY_SEPARATOR . 'dist')
                && !str_contains($path, DIRECTORY_SEPARATOR . 'marketplace-assets')
                && !str_contains($path, DIRECTORY_SEPARATOR . 'tmp')
                && !str_contains($path, DIRECTORY_SEPARATOR . '.git')) {
                $files[] = $path;
            }
        }

        return $files;
    }

    private function allSource(string $extension): string
    {
        $contents = '';
        foreach ($this->phpFiles() as $file) {
            $relative = str_replace('\\', '/', substr($file, strlen($this->root) + 1));
            if (!str_starts_with($relative, 'tests/')
                && !str_starts_with($relative, 'bin/')
                && str_ends_with($file, $extension)) {
                $contents .= (string) file_get_contents($file);
            }
        }

        return $contents;
    }
}
