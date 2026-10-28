<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\System\Salutation\AbstractSalutationsSorter;
use Contena\Core\System\Salutation\SalutationSorter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();





    $services->set(AbstractSalutationsSorter::class, SalutationSorter::class);

};
