<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

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
use Contena\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MailTemplateDefinition::class)->tag('contena.entity.definition', ['entity' => 'mail_template']);
    $services->set(MailTemplateTranslationDefinition::class)->tag('contena.entity.definition', ['entity' => 'mail_template_translation']);
    $services->set(MailTemplateTypeDefinition::class)->tag('contena.entity.definition', ['entity' => 'mail_template_type']);
    $services->set(MailTemplateTypeTranslationDefinition::class)->tag('contena.entity.definition', ['entity' => 'mail_template_type_translation']);
    $services->set(MailTemplateMediaDefinition::class)->tag('contena.entity.definition');
    $services->set(MailHeaderFooterDefinition::class)->tag('contena.entity.definition');
    $services->set(MailHeaderFooterTranslationDefinition::class)->tag('contena.entity.definition');

    $services->set(MailActionController::class)
        ->public()
        ->args([
            service(MailTemplateService::class),
            service(MailTemplateSendService::class),
            service(MailPayloadFactory::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(MailDataProvider::class)
        ->args([tagged_iterator('contena.mail.data_provider', 'key')]);

    $services->set(MailTemplateService::class)
        ->args([
            service('mail_template.repository'),
            service(StringTemplateRenderer::class),
            service(MailDataProvider::class),
            service(MailDataSimulator::class),
            service(MailTemplateContentBuilder::class),
            service('event_dispatcher'),
        ]);

    $services->set(MailTemplateSendService::class)
        ->args([service(MailService::class), service(MailDataProvider::class)]);

    $services->set(MailTemplateContentBuilder::class)
        ->args([service('mail_header_footer.repository')]);
    $services->set(MailDataSimulator::class);

    $services->set(PreviewRequestResolver::class)
        ->args([service(MailTemplateService::class)])
        ->tag('controller.argument_value_resolver');
    $services->set(GetDataAndSendRequestResolver::class)
        ->args([service(MailTemplateService::class), service(MailPayloadFactory::class)])
        ->tag('controller.argument_value_resolver');
    $services->set(SimulateRequestResolver::class)
        ->tag('controller.argument_value_resolver');
};
