<?php declare(strict_types=1);

namespace Contena\Tests\Bench;

require __DIR__ . '/../../../src/Core/TestBootstrapper.php';

use Contena\Core\TestBootstrapper;

(new TestBootstrapper())
    ->setForceInstall(false)
    ->setPlatformEmbedded(false)
    ->bootstrap();
