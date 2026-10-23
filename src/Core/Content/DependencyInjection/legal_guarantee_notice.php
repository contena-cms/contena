<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Contena\Core\Content\LegalGuaranteeNotice\LegalGuaranteeNoticeRenderer;
use Contena\Core\Content\LegalGuaranteeNotice\LegalGuaranteeNoticeTwigFilter;
use Contena\Core\Content\LegalGuaranteeNotice\Channel\AbstractLegalGuaranteeNoticeRoute;
use Contena\Core\Content\LegalGuaranteeNotice\Channel\LegalGuaranteeNoticeRoute;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(LegalGuaranteeNoticeRenderer::class)
        ->args([
            service('twig'),
            service(LanguageLocaleCodeProvider::class),
        ]);

    $services->set(LegalGuaranteeNoticeTwigFilter::class)
        ->args([
            service(LegalGuaranteeNoticeRenderer::class),
        ])
        ->tag('twig.extension');

    $services->set(LegalGuaranteeNoticeRoute::class)
        ->public()
        ->args([
            service(SystemConfigService::class),
            service(LegalGuaranteeNoticeRenderer::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->alias(AbstractLegalGuaranteeNoticeRoute::class, LegalGuaranteeNoticeRoute::class);
};
