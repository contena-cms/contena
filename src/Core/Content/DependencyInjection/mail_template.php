<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Psr\Clock\ClockInterface;
use Contena\Core\Content\Mail\Payload\MailPayloadFactory;
use Contena\Core\Content\Mail\Service\MailService;
use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation\MailHeaderFooterTranslationDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation\MailTemplateTranslationDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation\MailTemplateTypeTranslationDefinition;
use Contena\Core\Content\MailTemplate\Api\MailActionController;
use Contena\Core\Content\MailTemplate\MailTemplateDefinition;
use Contena\Core\Content\MailTemplate\Request\Resolver\GetDataAndSendRequestResolver;
use Contena\Core\Content\MailTemplate\Request\Resolver\PreviewRequestResolver;
use Contena\Core\Content\MailTemplate\Request\Resolver\SimulateRequestResolver;
use Contena\Core\Content\MailTemplate\Service\MailDataProvider;
use Contena\Core\Content\MailTemplate\Service\MailDataSimulator;
use Contena\Core\Content\MailTemplate\Service\MailTemplateContentBuilder;
use Contena\Core\Content\MailTemplate\Service\MailTemplateSendService;
use Contena\Core\Content\MailTemplate\Service\MailTemplateService;
use Contena\Core\Content\Shared\MailFlow\DataProvider\ChannelProvider;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Event\BusinessEventCollector;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Template Entities
    $services->set(MailTemplateDefinition::class)
        ->tag('contena.entity.definition', ['entity' => 'mail_template']);

    $services->set(MailTemplateTranslationDefinition::class)
        ->tag('contena.entity.definition', ['entity' => 'mail_template_translation']);

    $services->set(MailTemplateTypeDefinition::class)
        ->tag('contena.entity.definition', ['entity' => 'mail_template_type']);

    $services->set(MailTemplateTypeTranslationDefinition::class)
        ->tag('contena.entity.definition', ['entity' => 'mail_template_type_translation']);

    $services->set(MailTemplateMediaDefinition::class)
        ->tag('contena.entity.definition');

    // Header Footer Entities
    $services->set(MailHeaderFooterDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(MailHeaderFooterTranslationDefinition::class)
        ->tag('contena.entity.definition');

    // Controller
    $services->set(MailActionController::class)
        ->public()
        ->args([
            service(StringTemplateRenderer::class),
            service(MailTemplateService::class),
            service(MailTemplateSendService::class),
            service(MailPayloadFactory::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(MailDataProvider::class)
        ->args([
            tagged_iterator('contena.mail.data_provider', 'key'),
        ]);

    $services->set(MailTemplateService::class)
        ->args([
            service('mail_template.repository'),
            service(StringTemplateRenderer::class),
            service(MailDataProvider::class),
            service(MailDataSimulator::class),
            service(MailTemplateContentBuilder::class),
            service('event_dispatcher'),
            service(Translator::class),
            service(LanguageLocaleCodeProvider::class),
        ]);

    $services->set(MailTemplateSendService::class)
        ->args([
            service(MailService::class),
            service(MailDataProvider::class),
        ]);

    $services->set(MailTemplateContentBuilder::class);

    $services->set(MailDataSimulator::class)
        ->args([
            service(BusinessEventCollector::class),
            service(DefinitionInstanceRegistry::class),
            service('event_dispatcher'),
            tagged_iterator('contena.mail.data_provider', 'key'),
            service(ClockInterface::class),
        ]);

    $services->set(PreviewRequestResolver::class)
        ->args([
            service(MailTemplateService::class),
            service(ChannelProvider::class),
        ])
        ->tag('controller.argument_value_resolver');

    $services->set(GetDataAndSendRequestResolver::class)
        ->args([
            service(MailTemplateService::class),
            service(MailPayloadFactory::class),
        ])
        ->tag('controller.argument_value_resolver');

    $services->set(SimulateRequestResolver::class)
        ->args([
            service(ChannelProvider::class),
        ])
        ->tag('controller.argument_value_resolver');
};
