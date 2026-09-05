<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dist = $root . '/dist';
$stagingRoot = sys_get_temp_dir() . '/zipquantum-build-' . bin2hex(random_bytes(6));
$moduleRoot = $stagingRoot . '/zipquantum';
$releaseMtime = (new DateTimeImmutable('2026-09-02T00:00:00Z'))->getTimestamp();

$excludeTop = [
    '.git', '.github', '.gitignore', '.phpunit.cache', '.phpunit.result.cache',
    'bin', 'dist', 'marketplace-assets', 'tests', 'tmp', 'vendor', 'composer.json', 'composer.lock',
    'README.md', 'phpunit.xml.dist', 'phpstan.neon.dist', 'phpcs.xml.dist',
];
$excludeDocs = [
    'API_CONTRACT.md', 'BACKEND_CHANGES_REQUIRED.md', 'MARKETPLACE_LISTING_DRAFT.md',
    'MVP_1_0_SPEC.md', 'PRESTASHOP_MARKETPLACE_AUDIT.md', 'TEST_MATRIX.md',
    'RUNTIME_QA.md', 'VALIDATION_REPORT.md',
];

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "The PHP zip extension is required.\n");
    exit(1);
}

mkdir($moduleRoot, 0777, true);
$directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
$filtered = new RecursiveCallbackFilterIterator(
    $directory,
    static function (SplFileInfo $item) use ($root, $excludeTop): bool {
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
        $top = explode('/', $relative)[0];

        return !($item->isDir() && in_array($top, $excludeTop, true));
    }
);
$iterator = new RecursiveIteratorIterator($filtered, RecursiveIteratorIterator::SELF_FIRST);
foreach ($iterator as $item) {
    $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
    $top = explode('/', $relative)[0];
    if (in_array($top, $excludeTop, true) || ($top === 'docs' && in_array(basename($relative), $excludeDocs, true))) {
        continue;
    }
    $target = $moduleRoot . '/' . $relative;
    if ($item->isDir()) {
        if (!is_dir($target)) {
            mkdir($target, 0777, true);
        }
    } else {
        $parent = dirname($target);
        if (!is_dir($parent)) {
            mkdir($parent, 0777, true);
        }
        copy($item->getPathname(), $target);
    }
}

if (!is_file($moduleRoot . '/docs/readme_en.pdf')) {
    fwrite(STDERR, "Missing Marketplace guide: docs/readme_en.pdf\n");
    removeTree($stagingRoot);
    exit(1);
}

if (!is_dir($dist)) {
    mkdir($dist, 0777, true);
}
$archivePath = $dist . '/zipquantum.zip';
$zip = new ZipArchive();
if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Unable to create archive.\n");
    removeTree($stagingRoot);
    exit(1);
}
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($moduleRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ($files as $file) {
    if ($file->isFile()) {
        $local = 'zipquantum/' . str_replace('\\', '/', substr($file->getPathname(), strlen($moduleRoot) + 1));
        $zip->addFile($file->getPathname(), $local);
        $zip->setMtimeName($local, $releaseMtime);
    }
}
$zip->close();
removeTree($stagingRoot);

validateArchive($archivePath);

$archiveHash = strtoupper(hash_file('sha256', $archivePath));
file_put_contents($archivePath . '.sha256', $archiveHash . ' *zipquantum.zip' . PHP_EOL);
echo $archivePath . PHP_EOL;
echo 'SHA-256 ' . $archiveHash . PHP_EOL;

function removeTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function validateArchive(string $archivePath): void
{
    $zip = new ZipArchive();
    if ($zip->open($archivePath) !== true) {
        throw new RuntimeException('Unable to inspect the Marketplace archive.');
    }
    $required = [
        'zipquantum/zipquantum.php',
        'zipquantum/config.xml',
        'zipquantum/logo.png',
        'zipquantum/.htaccess',
        'zipquantum/docs/readme_en.pdf',
    ];
    $seen = [];
    for ($index = 0; $index < $zip->numFiles; ++$index) {
        $name = (string) $zip->getNameIndex($index);
        if (!str_starts_with($name, 'zipquantum/') || str_contains($name, '../') || str_contains($name, '\\')) {
            $zip->close();
            throw new RuntimeException('Unsafe or invalid archive entry: ' . $name);
        }
        if (preg_match('#^zipquantum/(?:tests|vendor|bin|dist|tmp)/#', $name)) {
            $zip->close();
            throw new RuntimeException('Development content leaked into the archive: ' . $name);
        }
        $seen[$name] = true;
    }
    $zip->close();
    foreach ($required as $name) {
        if (!isset($seen[$name])) {
            throw new RuntimeException('Required Marketplace file is missing: ' . $name);
        }
    }
}
