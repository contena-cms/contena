<?php declare(strict_types=1);

namespace Contena\Core\Content\Mail;

use Contena\Core\Content\Mail\Service\MailSender;
use Contena\Core\Content\Mail\Transport\MailerTransportLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
class MailerConfigurationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition('mailer.default_transport')) {
            $container->getDefinition('mailer.default_transport')->setFactory([
                new Reference(MailerTransportLoader::class),
                'fromString',
            ]);
        }

        $container->getDefinition('mailer.transports')->setFactory([
            new Reference(MailerTransportLoader::class),
            'fromStrings',
        ]);

        $mailer = $container->getDefinition(MailSender::class);
        // use the same message bus from symfony/mailer configuration.
        // matching: https://developer.contena.cn/docs/guides/hosting/infrastructure/message-queue.html#sending-mails-over-the-message-queue
        $originalMailer = $container->getDefinition('mailer.mailer');
        $mailer->replaceArgument(6, $originalMailer->getArgument(1));
    }
}
