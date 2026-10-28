<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use League\Flysystem\FilesystemOperator;
use Psr\Clock\ClockInterface;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Filesystem\FilesystemFactory;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\App\Source\SourceResolver;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetDefinition;
use Contena\Core\System\Snippet\Command\DownloadTranslationCommand;
use Contena\Core\System\Snippet\Command\InstallTranslationCommand;
use Contena\Core\System\Snippet\Command\LintTranslationFilesCommand;
use Contena\Core\System\Snippet\Command\ListTranslationsCommand;
use Contena\Core\System\Snippet\Command\UpdateTranslationCommand;
use Contena\Core\System\Snippet\Command\Util\CountryAgnosticFileLinter;
use Contena\Core\System\Snippet\Command\ValidateSnippetsCommand;
use Contena\Core\System\Snippet\Files\SnippetFileCollection;
use Contena\Core\System\Snippet\Files\StorefrontSnippetLifecycleHandler;
use Contena\Core\System\Snippet\Files\StorefrontSnippetStorage;
use Contena\Core\System\Snippet\SalesChannel\SalesChannelSnippetLoader;
use Contena\Core\System\Snippet\SalesChannel\SnippetRoute;
use Contena\Core\System\Snippet\ScheduledTask\UpdateTranslationsTask;
use Contena\Core\System\Snippet\ScheduledTask\UpdateTranslationsTaskHandler;
use Contena\Core\System\Snippet\Service\AbstractTranslationConfigLoader;
use Contena\Core\System\Snippet\Service\AbstractTranslationLoader;
use Contena\Core\System\Snippet\Service\TranslationConfigLoader;
use Contena\Core\System\Snippet\Service\TranslationFilesystemFactory;
use Contena\Core\System\Snippet\Service\TranslationLoader;
use Contena\Core\System\Snippet\Service\TranslationMetadataStore;
use Contena\Core\System\Snippet\Service\TranslationRemover;
use Contena\Core\System\Snippet\Service\TranslationUpdater;
use Contena\Core\System\Snippet\SnippetDefinition;
use Contena\Core\System\Snippet\SnippetFileHandler;
use Contena\Core\System\Snippet\SnippetFixer;
use Contena\Core\System\Snippet\SnippetValidator;
use Contena\Core\System\Snippet\SnippetValidatorInterface;
use Contena\Core\System\Snippet\Struct\TranslationConfig;
use Contena\Core\System\Snippet\Subscriber\CustomFieldSubscriber;
use Contena\Core\System\Snippet\Subscriber\LanguageDeletionSubscriber;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SnippetSetDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(SnippetDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(SnippetValidatorInterface::class, SnippetValidator::class)
        ->args([
            service(SnippetFileCollection::class),
            service(SnippetFileHandler::class),
            param('kernel.project_dir') . '/',
        ]);

    $services->set(SnippetValidator::class)
        ->args([
            service(SnippetFileCollection::class),
            service(SnippetFileHandler::class),
            param('kernel.project_dir') . '/',
        ]);

    $services->set(StorefrontSnippetStorage::class)
        ->args([
            service('contena.filesystem.translation'),
            service(SourceResolver::class),
            service('logger'),
            param('kernel.cache_dir') . '/app-snippets',
        ]);

    $services->set(StorefrontSnippetLifecycleHandler::class)
        ->args([
            service(StorefrontSnippetStorage::class),
            service(CacheInvalidator::class),
            service(Connection::class),
        ])
        ->tag('contena.app_lifecycle.handler', ['priority' => -1400]);

    $services->set(SnippetFixer::class)
        ->args([
            service(SnippetFileHandler::class),
        ]);

    $services->set(ValidateSnippetsCommand::class)
        ->args([
            service(SnippetValidator::class),
            service(SnippetFixer::class),
        ])
        ->tag('console.command');

    $services->set(CountryAgnosticFileLinter::class)
        ->args([
            service(Filesystem::class),
            service('plugin.repository'),
            service('app.repository'),
            inline_service(Finder::class),
        ]);

    $services->set(LintTranslationFilesCommand::class)
        ->args([
            service(CountryAgnosticFileLinter::class),
        ])
        ->tag('console.command');

    $services->set(InstallTranslationCommand::class)
        ->args([
            service(TranslationConfig::class),
            service(TranslationMetadataStore::class),
            service(TranslationUpdater::class),
        ])
        ->tag('console.command');

    $services->set(DownloadTranslationCommand::class)
        ->args([
            service(AbstractTranslationLoader::class),
            service(TranslationConfig::class),
        ])
        ->tag('console.command');

    $services->set(UpdateTranslationCommand::class)
        ->args([
            service(TranslationLoader::class),
            service(TranslationMetadataStore::class),
        ])
        ->tag('console.command');

    $services->set(ListTranslationsCommand::class)
        ->args([
            service(TranslationConfig::class),
            service(TranslationMetadataStore::class),
        ])
        ->tag('console.command');

    $services->set('contena.translation.client', Client::class)
        ->args([
            [
                'timeout' => 30,
                'connect_timeout' => 5,
            ],
        ]);

    $services->set(TranslationConfigLoader::class)
        ->args([
            service('filesystem'),
            param('contena.translation'),
        ]);

    $services->alias(AbstractTranslationConfigLoader::class, TranslationConfigLoader::class);

    $services->set(TranslationConfig::class)
        ->lazy()
        ->public()
        ->factory([service(TranslationConfigLoader::class), 'load']);

    $services->set(TranslationLoader::class)
        ->args([
            service('contena.filesystem.translation'),
            service('language.repository'),
            service('locale.repository'),
            service('snippet_set.repository'),
            service('contena.translation.client'),
            service(TranslationConfig::class),
            service('event_dispatcher'),
        ]);

    $services->alias(AbstractTranslationLoader::class, TranslationLoader::class);

    $services->set(TranslationMetadataStore::class)
        ->args([
            service(TranslationConfig::class),
            service('contena.translation.client'),
            service('contena.filesystem.translation'),
            service('cache.object'),
        ]);

    $services->set(TranslationUpdater::class)
        ->args([
            service(TranslationLoader::class),
            service(TranslationMetadataStore::class),
        ]);

    $services->set(TranslationRemover::class)
        ->args([
            service('contena.filesystem.translation'),
            service(TranslationLoader::class),
            service(TranslationMetadataStore::class),
            service('event_dispatcher'),
        ]);

    $services->set(UpdateTranslationsTask::class)
        ->tag('contena.scheduled.task');

    $services->set(UpdateTranslationsTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(TranslationUpdater::class),
            service('language.repository'),
        ])
        ->tag('messenger.message_handler');

    $services->set(TranslationFilesystemFactory::class)
        ->args([
            service('contena.filesystem.private'),
            service(FilesystemFactory::class),
            param('kernel.project_dir'),
            param('contena.translation.use_local_filesystem'),
        ]);

    $services->set('contena.filesystem.translation', FilesystemOperator::class)
        ->factory([service(TranslationFilesystemFactory::class), 'create']);

    $services->set(SalesChannelSnippetLoader::class)
        ->args([
            service(Translator::class),
            service(LanguageLocaleCodeProvider::class),
            service('sales_channel.language.repository'),
        ]);

    $services->set(SnippetRoute::class)
        ->public()
        ->args([
            service(SalesChannelSnippetLoader::class),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(SnippetFileHandler::class)
        ->args([
            service('filesystem'),
        ]);

    $services->set(CustomFieldSubscriber::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(LanguageDeletionSubscriber::class)
        ->args([
            service(Connection::class),
            service(TranslationMetadataStore::class),
        ])
        ->tag('kernel.event_subscriber');
};
