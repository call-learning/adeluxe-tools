<?php

declare(strict_types=1);

namespace AdeluxeTools\ClapiInstaller;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use RuntimeException;

final class SyncManager
{
    private const MANIFEST = '.clapi/generated.json';

    public function __construct(
        private readonly Composer $composer,
        private readonly IOInterface $io,
    ) {
    }

    public function sync(): void
    {
        $root = $this->composer->getPackage();
        $extra = $root->getExtra();
        $config = $extra['clapi'] ?? null;

        if (!is_array($config)) {
            return;
        }

        $component = $config['component'] ?? null;
        $modules = $config['modules'] ?? [];
        $target = rtrim((string)($config['target'] ?? '.'), '/');

        if (!is_string($component) || $component === '') {
            throw new RuntimeException('extra.clapi.component is required, e.g. mod_competvet.');
        }
        if (!is_array($modules)) {
            throw new RuntimeException('extra.clapi.modules must be an array.');
        }

        $previous = $this->loadGeneratedManifest();
        $current = ['files' => [], 'lang' => []];

        foreach ($this->getClapiPackages() as $package) {
            $packagePath = $this->composer->getInstallationManager()->getInstallPath($package);
            if ($packagePath === null) {
                continue;
            }

            foreach ($modules as $module) {
                if (!is_string($module)) {
                    continue;
                }
                $modulePath = $packagePath . '/modules/' . $module;
                if (!is_dir($modulePath)) {
                    continue;
                }

                $this->io->write(sprintf('<info>CLAPI</info> Installing module <comment>%s</comment>', $module));
                $this->installModule($modulePath, $target, $component, $current);
            }
        }

        $this->removeStaleFiles($previous['files'] ?? [], $current['files']);
        $this->syncLanguage($target, $component, $previous['lang'] ?? [], $current['lang']);
        $this->saveGeneratedManifest($current);
    }

    /** @return PackageInterface[] */
    private function getClapiPackages(): array
    {
        return array_values(array_filter(
            $this->composer->getRepositoryManager()->getLocalRepository()->getPackages(),
            static fn(PackageInterface $package): bool => $package->getType() === 'adeluxe-tools-clapi'
        ));
    }

    private function installModule(string $modulePath, string $target, string $component, array &$current): void
    {
        $manifestPath = $modulePath . '/module.json';
        if (!is_file($manifestPath)) {
            throw new RuntimeException('Missing CLAPI module manifest: ' . $manifestPath);
        }

        $manifest = json_decode((string)file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        foreach (($manifest['resources'] ?? []) as $resource) {
            $type = $resource['type'] ?? null;
            $source = $modulePath . '/' . ($resource['source'] ?? '');

            if ($type === 'copy') {
                $destination = $target . '/' . ltrim((string)($resource['target'] ?? ''), '/');
                $this->copyTree($source, $destination, $component, $current['files']);
            } elseif ($type === 'lang') {
                $strings = require $source;
                if (!is_array($strings)) {
                    throw new RuntimeException('Language resource must return an array: ' . $source);
                }
                foreach ($strings as $key => $value) {
                    $current['lang'][(string)$key] = (string)$value;
                }
            }
        }
    }

    private function copyTree(string $source, string $destination, string $component, array &$generated): void
    {
        if (is_file($source)) {
            $this->copyFile($source, $destination, $component, $generated);
            return;
        }
        if (!is_dir($source)) {
            throw new RuntimeException('CLAPI resource does not exist: ' . $source);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($source) + 1);
            $this->copyFile($file->getPathname(), $destination . '/' . $relative, $component, $generated);
        }
    }

    private function copyFile(string $source, string $destination, string $component, array &$generated): void
    {
        $contents = (string)file_get_contents($source);
        $contents = str_replace('__COMPONENT__', $component, $contents);

        $dir = dirname($destination);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create directory: ' . $dir);
        }

        file_put_contents($destination, $contents);
        $generated[] = $destination;
    }

    private function removeStaleFiles(array $previous, array $current): void
    {
        foreach (array_diff($previous, $current) as $file) {
            if (is_string($file) && is_file($file)) {
                unlink($file);
                $this->io->write('<comment>CLAPI</comment> Removed ' . $file);
            }
        }
    }

    private function syncLanguage(string $target, string $component, array $previous, array $current): void
    {
        if ($previous === [] && $current === []) {
            return;
        }

        $file = $target . '/lang/en/' . $component . '.php';
        $contents = is_file($file) ? (string)file_get_contents($file) : "<?php\n";
        $begin = '// CLAPI-GENERATED-BEGIN';
        $end = '// CLAPI-GENERATED-END';

        $block = $begin . "\n";
        ksort($current);
        foreach ($current as $key => $value) {
            $block .= '$string[' . var_export($key, true) . '] = ' . var_export($value, true) . ";\n";
        }
        $block .= $end;

        $pattern = '/' . preg_quote($begin, '/') . '.*?' . preg_quote($end, '/') . '/s';
        if (preg_match($pattern, $contents)) {
            $contents = preg_replace($pattern, $block, $contents) ?? $contents;
        } else {
            $contents = rtrim($contents) . "\n\n" . $block . "\n";
        }

        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($file, $contents);
    }

    private function loadGeneratedManifest(): array
    {
        if (!is_file(self::MANIFEST)) {
            return ['files' => [], 'lang' => []];
        }
        return json_decode((string)file_get_contents(self::MANIFEST), true) ?: ['files' => [], 'lang' => []];
    }

    private function saveGeneratedManifest(array $manifest): void
    {
        if (!is_dir('.clapi')) {
            mkdir('.clapi', 0777, true);
        }
        file_put_contents(self::MANIFEST, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
}
