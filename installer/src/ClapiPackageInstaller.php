<?php

declare(strict_types=1);

namespace AdeluxeTools\ClapiInstaller;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;

final class ClapiPackageInstaller extends LibraryInstaller
{
    public function supports(string $packageType): bool
    {
        return $packageType === 'adeluxe-tools-clapi';
    }

    public function getInstallPath(PackageInterface $package): string
    {
        $name = $package->getPrettyName();
        $shortname = str_contains($name, '/') ? substr($name, strrpos($name, '/') + 1) : $name;

        return '.clapi/packages/' . $shortname;
    }
}
