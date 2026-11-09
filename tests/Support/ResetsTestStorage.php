<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Incident\Presentation\Admin\StorageMonitor;
use Symfony\Component\Filesystem\Filesystem;

/** E2E tests share var/storage/footage_test and the app cache: start each test from empty media storage. */
trait ResetsTestStorage
{
    private static function resetTestStorage(): void
    {
        $container = static::getContainer();
        (new Filesystem())->remove($container->getParameter('kernel.project_dir').'/var/storage/footage_test');
        $container->get(StorageMonitor::class)->refresh();
    }
}
