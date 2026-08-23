<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Mail\Message\SendMailHandler;
use Contena\Core\Content\Mail\Payload\MailPayloadFactory;
use Contena\Core\Content\Mail\Service\MailAttachmentsBuilder;
use Contena\Core\Content\Mail\Service\MailFactory;
use Contena\Core\Content\Mail\Service\MailSender;
use Contena\Core\Content\Mail\Service\MailService;
use Contena\Core\Content\Mail\Service\SendMailTemplate;
use Contena\Core\Content\Mail\Subscriber\FailedMessageSubscriber;
use Contena\Core\Content\Mail\Telemetry\MailGroupResolver;
use Contena\Core\Content\Mail\Telemetry\MailMetricsInstrumentor;
use Contena\Core\Content\Mail\Transport\MailerTransportLoader;
use Contena\Core\Content\Mail\Transport\SmtpOauthAuthenticator;
use Contena\Core\Content\Mail\Transport\SmtpOauthTokenProvider;
use Contena\Core\Content\Mail\Transport\SmtpOauthTransportFactoryDecorator;
use Contena\Core\Content\MailTemplate\Service\MailTemplateContentBuilder;
use Contena\Core\Content\Media\MediaService;
use Contena\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Contena\Core\Framework\Telemetry\Metrics\Meter;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SendMailHandler::class)
        ->args([service('mailer.transports'), service('contena.filesystem.private'), service('logger')])
        ->tag('messenger.message_handler');

    $services->set(MailSender::class)
        ->public()
        ->args([
            service('mailer.mailer'),
            service('contena.filesystem.private'),
            service(SystemConfigService::class),
            param('contena.mail.max_body_length'),
            service('logger'),
            param('contena.messenger.message_max_kib_size'),
            abstract_arg('message bus'),
            param('contena.staging.mailing.disable_delivery'),
        ]);

    $services->set(MailFactory::class)->public()->args([service('validator')]);

    $services->set(MailService::class)
        ->args([
            service(DataValidator::class),
            service(StringTemplateRenderer::class),
            service(MailFactory::class),
            service(MailSender::class),
            service('media.repository'),
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service('logger'),
            service(LanguageLocaleCodeProvider::class),
            service(MailTemplateContentBuilder::class),
            service(MailMetricsInstrumentor::class),
        ]);

    $services->set(MailGroupResolver::class);

    $services->set(MailMetricsInstrumentor::class)
        ->args([
            service(Meter::class),
            service(MailGroupResolver::class),
        ]);

    $services->set(SendMailTemplate::class)
        ->args([
            service(MailService::class),
            service('mail_template.repository'),
            service('logger'),
            service(Connection::class),
        ]);

    $services->set(MailAttachmentsBuilder::class)
        ->public()
        ->args([service(MediaService::class), service('media.repository')]);

    $services->set(MailPayloadFactory::class);
    $services->alias('core_mailer', 'mailer');

    $services->set(MailerTransportLoader::class)
        ->args([
            service('mailer.transport_factory'),
            service(SystemConfigService::class),
            service(MailAttachmentsBuilder::class),
            service('contena.filesystem.public'),
        ]);

    $services->set(SmtpOauthTransportFactoryDecorator::class)
        ->decorate('mailer.transport_factory.smtp')
        ->args([
            service(SmtpOauthTransportFactoryDecorator::class . '.inner'),
            service(SmtpOauthAuthenticator::class),
        ]);

    $services->set(SmtpOauthAuthenticator::class)->args([service(SmtpOauthTokenProvider::class)]);
    $services->set(SmtpOauthTokenProvider::class)
        ->args([service('http_client'), service('cache.object'), service(SystemConfigService::class)]);

    $services->set(FailedMessageSubscriber::class)
        ->args([service(Connection::class), service(ClockInterface::class)])
        ->tag('kernel.event_subscriber');
};
