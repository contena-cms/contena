<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\ProductExport\Api\ProductExportController;
use Contena\Core\Content\ProductExport\Command\ProductExportGenerateCommand;
use Contena\Core\Content\ProductExport\DataAbstractionLayer\ProductExportExceptionHandler;
use Contena\Core\Content\ProductExport\EventListener\ProductExportEventListener;
use Contena\Core\Content\ProductExport\ProductExportDefinition;
use Contena\Core\Content\ProductExport\Provider\GoogleProductExportProvider;
use Contena\Core\Content\ProductExport\Provider\OpenAiProductExportProvider;
use Contena\Core\Content\ProductExport\SalesChannel\ExportController;
use Contena\Core\Content\ProductExport\ScheduledTask\ProductExportGenerateTask;
use Contena\Core\Content\ProductExport\ScheduledTask\ProductExportGenerateTaskHandler;
use Contena\Core\Content\ProductExport\ScheduledTask\ProductExportPartialGenerationHandler;
use Contena\Core\Content\ProductExport\Service\ProductExporter;
use Contena\Core\Content\ProductExport\Service\ProductExportFileHandler;
use Contena\Core\Content\ProductExport\Service\ProductExportGenerator;
use Contena\Core\Content\ProductExport\Service\ProductExportRenderer;
use Contena\Core\Content\ProductExport\Service\ProductExportValidator;
use Contena\Core\Content\ProductExport\Validator\FeedLabelValidator;
use Contena\Core\Content\ProductExport\Validator\GoogleProductExportValidator;
use Contena\Core\Content\ProductExport\Validator\JsonlRowParser;
use Contena\Core\Content\ProductExport\Validator\OpenAiProductExportValidator;
use Contena\Core\Content\ProductExport\Validator\XmlValidator;
use Contena\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Contena\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextService;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();
    $parameters->set('product_export.directory', 'export');
    $parameters->set('product_export.read_buffer_size', '%contena.product_export.read_buffer_size%');
    // Stale detection tuning: unlock stuck exports when older than max(min_seconds, factor * interval)
    $parameters->set('product_export.stale_min_seconds', 300);
    $parameters->set('product_export.stale_interval_factor', 2.0);

    $services = $containerConfigurator->services();

    $services->set(ProductExportDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(FeedLabelValidator::class)
        ->tag('kernel.event_subscriber');

    $services->set(ProductExportRenderer::class)
        ->args([
            service(StringTemplateRenderer::class),
            service('event_dispatcher'),
        ]);

    $services->set(ProductExporter::class)
        ->public()
        ->args([
            service('product_export.repository'),
            service(ProductExportGenerator::class),
            service('event_dispatcher'),
            service(ProductExportFileHandler::class),
            service(ClockInterface::class),
        ]);

    $services->set(ProductExportFileHandler::class)
        ->args([
            service('contena.filesystem.private'),
            param('product_export.directory'),
            service(ClockInterface::class),
        ]);

    $services->set(ProductExportGenerator::class)
        ->public()
        ->args([
            service(ProductStreamBuilder::class),
            service('sales_channel.product.repository'),
            service(ProductExportRenderer::class),
            service('event_dispatcher'),
            service(ProductExportValidator::class),
            service(SalesChannelContextService::class),
            service(Translator::class),
            service(SalesChannelContextPersister::class),
            service(Connection::class),
            param('product_export.read_buffer_size'),
            service(SeoUrlPlaceholderHandlerInterface::class),
            service('twig'),
            service(ProductDefinition::class),
            service(LanguageLocaleCodeProvider::class),
            service(TwigVariableParserFactory::class),
            service(CategoryBreadcrumbBuilder::class),
        ]);

    $services->set(ProductExportGenerateCommand::class)
        ->args([
            service(SalesChannelContextFactory::class),
            service(ProductExporter::class),
            service('product_export.repository'),
            service('logger'),
        ])
        ->tag('console.command');

    $services->set(ProductExportGenerateTask::class)
        ->tag('contena.scheduled.task');

    $services->set(ProductExportGenerateTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Connection::class),
            service('messenger.default_bus'),
            service(ClockInterface::class),
            param('product_export.stale_min_seconds'),
            param('product_export.stale_interval_factor'),
        ])
        ->tag('messenger.message_handler');

    $services->set(ProductExportPartialGenerationHandler::class)
        ->args([
            service(ProductExportGenerator::class),
            service(SalesChannelContextFactory::class),
            service('product_export.repository'),
            service(ProductExportFileHandler::class),
            service('messenger.default_bus'),
            service(ProductExportRenderer::class),
            service(Translator::class),
            service(SalesChannelContextService::class),
            service(SalesChannelContextPersister::class),
            service(Connection::class),
            service(LanguageLocaleCodeProvider::class),
            service(ClockInterface::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(ProductExportController::class)
        ->public()
        ->args([
            service('sales_channel_domain.repository'),
            service('sales_channel.repository'),
            service(ProductExportGenerator::class),
            service('event_dispatcher'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(ProductExportValidator::class)
        ->args([
            tagged_iterator('contena.product_export.validator'),
        ]);

    $services->set(JsonlRowParser::class)
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(XmlValidator::class)
        ->tag('contena.product_export.validator');

    $services->set(OpenAiProductExportValidator::class)
        ->args([
            service(JsonlRowParser::class),
        ])
        ->tag('contena.product_export.validator')
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(GoogleProductExportValidator::class)
        ->tag('contena.product_export.validator')
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);


    $services->set(OpenAiProductExportProvider::class)
        ->args([
            service('sales_channel.repository'),
            service(SystemConfigService::class),
        ])
        ->tag('contena.product_export.provider')
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(GoogleProductExportProvider::class)
        ->args([
            service('sales_channel.repository'),
            service(SystemConfigService::class),
        ])
        ->tag('contena.product_export.provider')
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(ProductExportExceptionHandler::class)
        ->tag('contena.dal.exception_handler');

    $services->set(ProductExportEventListener::class)
        ->args([
            service('product_export.repository'),
            service(ProductExportFileHandler::class),
            service('contena.filesystem.private'),
        ])
        ->tag('kernel.event_subscriber');


    $services->set(ExportController::class)
        ->public()
        ->args([
            service(ProductExporter::class),
            service(ProductExportFileHandler::class),
            service('contena.filesystem.private'),
            service('event_dispatcher'),
            service('product_export.repository'),
            service(SalesChannelContextFactory::class),
            service(ClockInterface::class),
        ]);
};
