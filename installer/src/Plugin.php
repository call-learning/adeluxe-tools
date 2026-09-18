<?php

declare(strict_types=1);

namespace AdeluxeTools\AdeluxeInstaller;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

final class Plugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
        $composer->getInstallationManager()->addInstaller(new AdeluxePackageInstaller($io, $composer));
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_AUTOLOAD_DUMP => 'sync',
        ];
    }

    public function sync(Event $event): void
    {
        $sync = new SyncManager($this->composer, $this->io);
        $sync->sync();
    }
}
