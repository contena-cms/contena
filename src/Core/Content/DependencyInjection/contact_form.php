<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Contena\Core\Content\Cms\Service\CmsFormSlotConfigResolver;
use Contena\Core\Content\ContactForm\SalesChannel\ContactFormRoute;
use Contena\Core\Content\ContactForm\Validation\ContactFormValidationFactory;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(ContactFormValidationFactory::class)
        ->args([
            service('event_dispatcher'),
            service(SystemConfigService::class),
        ]);

    $services->set(ContactFormRoute::class)
        ->public()
        ->args([
            service(ContactFormValidationFactory::class),
            service(DataValidator::class),
            service('event_dispatcher'),
            service('salutation.repository'),
            service(RequestStack::class),
            service('contena.rate_limiter'),
            service(CmsFormSlotConfigResolver::class),
            service(ExtensionDispatcher::class),
        ]);
};
