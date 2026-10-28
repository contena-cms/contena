<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Contena\Core\Content\LegalGuaranteeNotice\SalesChannel\AbstractLegalGuaranteeNoticeRoute;
use Contena\Core\Content\LegalGuaranteeNotice\SalesChannel\LegalGuaranteeNoticeRoute;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();




    $services->alias(AbstractLegalGuaranteeNoticeRoute::class, LegalGuaranteeNoticeRoute::class);
};
