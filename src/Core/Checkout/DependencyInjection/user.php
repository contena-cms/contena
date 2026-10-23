<?php declare(strict_types=1);

namespace Contena\Core\Checkout\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    // User entity services are wired in the baseline follow-up (field conversion & broken-link repair).
};
