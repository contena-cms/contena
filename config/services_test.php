<?php declare(strict_types=1);

use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\SalesChannel\CategoryRoute;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\Test\TestNavigationSeoUrlRoute;
use Contena\Core\Content\Test\TestProductSeoUrlRoute;
use Contena\Core\Framework\App\Url\AppUrlVerifier;
use Contena\Core\Framework\Telemetry\Metrics\Config\TransportConfigProvider;
use Contena\Core\Framework\Telemetry\Metrics\Transport\TransportCollection;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CalculatedPriceFieldTestDefinition;
use Contena\Core\Framework\Test\Telemetry\Factory\TraceableTransportFactory;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Contena\Tests\Integration\Core\Content\Seo\SalesChannel\FixturesPhp\StoreApiSeoResolverTestRoute;
use Contena\Tests\Integration\Core\Framework\Api\EventListener\FixturesPhp\SalesChannelAuthenticationListenerTestRoute;
use Contena\Tests\Integration\Core\Framework\App\AppFixture;
use Contena\Tests\Integration\Core\Framework\App\PassingAppUrlVerifier;
use Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntity;
use Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntityAgg;
use Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntityWithHydrator;
use Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntityWithInheritance;
use Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntityWithSearchRanking;
use Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\DummyHydrator;
use Contena\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestConstantTaxRateProvider;
use Contena\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestEmptyTaxProvider;
use Contena\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestGenericExceptionTaxProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\iterator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('contena.messenger.enforce_message_size', true);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autoconfigure();

    $services->set(SalesChannelAuthenticationListenerTestRoute::class)
        ->tag('controller.service_arguments');

    $services->set(StoreApiSeoResolverTestRoute::class)
        ->args([
            service(CategoryRoute::class),
            service(SalesChannelContextFactory::class),
        ])
        ->tag('controller.service_arguments');

    $services->set(CalculatedPriceFieldTestDefinition::class)
        ->tag('contena.entity.definition');

    // Payment
    $services->set(TestConstantTaxRateProvider::class)
        ->tag('contena.tax.provider');

    $services->set(TestEmptyTaxProvider::class)
        ->tag('contena.tax.provider');

    $services->set(TestGenericExceptionTaxProvider::class)
        ->tag('contena.tax.provider');

    // Route
    $services->set(TestNavigationSeoUrlRoute::class)
        ->args([
            service(CategoryDefinition::class),
        ])
        ->tag('contena.seo_url.route');

    $services->set(TestProductSeoUrlRoute::class)
        ->args([
            service(ProductDefinition::class),
        ])
        ->tag('contena.seo_url.route');

    $services->set(AttributeEntity::class)
        ->tag('contena.entity');

    $services->set(AttributeEntityAgg::class);

    $services->set(AttributeEntityWithHydrator::class)
        ->tag('contena.entity');

    $services->set(AttributeEntityWithInheritance::class)
        ->tag('contena.entity');

    $services->set(AttributeEntityWithSearchRanking::class)
        ->tag('contena.entity');

    $services->set(DummyHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(AppUrlVerifier::class, PassingAppUrlVerifier::class);

    $services->set(AppFixture::class)
        ->public()
        ->args([
            service('app.repository'),
        ]);

    $services->set(TraceableTransportFactory::class)
        ->tag('contena.metric_transport_factory');

    $services->set(TransportCollection::class)
        ->lazy()
        ->factory([TransportCollection::class, 'create'])
        ->args([
            iterator([
                service(TraceableTransportFactory::class),
            ]),
            service(TransportConfigProvider::class),
        ]);
};
