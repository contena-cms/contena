<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Monolog\Handler\NullHandler;
use Contena\Core\Framework\Test\Api\Acl\fixtures\AclTestController;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldTestDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldTestTranslationDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\DateTimeDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\NamedDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\NamedOptionalGroupDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\RootDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\SubCascadeDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\SubDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\SubManyDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\WriteProtectedDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\WriteProtectedReferenceDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\WriteProtectedRelationDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Write\Validation\TestDefinition\TestDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Write\Validation\TestDefinition\TestTranslationDefinition;
use Contena\Core\Framework\Test\Filesystem\Adapter\MemoryAdapterFactory;
use Contena\Core\Framework\Test\MessageQueue\fixtures\TestMessageHandler;
use Contena\Core\Framework\Test\TestCacheClearer;
use Contena\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Contena\Core\Framework\Test\TestSessionStorageFactory;
use Contena\Core\Test\Stub\ContentSystem\TestElementTypeLoader;
use Contena\Core\Test\Stub\ContentSystem\TestMultiReferenceGatingLoader;
use Contena\Core\Test\Stub\ContentSystem\TestMultiReferenceGatingLoaderConfigSerializer;
use Contena\Core\Test\Stub\ContentSystem\TestNavigationShapedLoader;
use Contena\Core\Test\Stub\ContentSystem\TestNavigationShapedLoaderConfigSerializer;
use Contena\Core\Test\Stub\ContentSystem\TestStyleOptionLoader;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Messenger\TraceableMessageBus;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $services->defaults()->public();
    $services->defaults()->public();

    $services->set(TestElementTypeLoader::class)
        ->tag('content_system.type_loader');

    $services->set(TestStyleOptionLoader::class)
        ->tag('content_system.style_option_loader');

    $services->set(TestMultiReferenceGatingLoader::class)
        ->tag('content_system.data_loader');

    $services->set(TestMultiReferenceGatingLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');

    $services->set(TestNavigationShapedLoader::class)
        ->tag('content_system.data_loader');

    $services->set(TestNavigationShapedLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');

    $services->set(MemoryAdapterFactory::class)->tag('contena.filesystem.factory');
    $services->set(NamedDefinition::class)->tag('contena.entity.definition');
    $services->set(NamedOptionalGroupDefinition::class)->tag('contena.entity.definition');
    $services->set(RootDefinition::class)->tag('contena.entity.definition', ['entity' => 'root']);
    $services->set(SubDefinition::class)->tag('contena.entity.definition', ['entity' => 'root_sub']);
    $services->set(SubCascadeDefinition::class)->tag('contena.entity.definition', ['entity' => 'root_sub_cascade']);
    $services->set(SubManyDefinition::class)->tag('contena.entity.definition', ['entity' => 'root_sub_many']);
    $services->set(TestDefinition::class)->tag('contena.entity.definition', ['entity' => '_test_lock']);
    $services->set(TestTranslationDefinition::class)->tag('contena.entity.definition', ['entity' => '_test_lock_translation']);
    $services->set(CustomFieldTestDefinition::class)->tag('contena.entity.definition', ['entity' => 'attribute_test']);
    $services->set(CustomFieldTestTranslationDefinition::class)->tag('contena.entity.definition', ['entity' => 'attribute_test_translation']);
    $services->set(WriteProtectedDefinition::class)->tag('contena.entity.definition', ['entity' => '_test_nullable']);
    $services->set(WriteProtectedRelationDefinition::class)->tag('contena.entity.definition', ['entity' => '_test_relation']);
    $services->set(WriteProtectedReferenceDefinition::class)->tag('contena.entity.definition', ['entity' => '_test_nullable_reference']);
    $services->set(DateTimeDefinition::class)->tag('contena.entity.definition', ['entity' => 'date_time_test']);

    $services->alias('messenger.test_receiver_locator', 'messenger.receiver_locator')->public();

    $services->set('messenger.bus.test_contena', TraceableMessageBus::class)
        ->decorate('messenger.default_bus')
        ->args([service('messenger.bus.test_contena.inner')]);

    $services->set('mailer.mailer', Mailer::class)
        ->args([
            service('mailer.transports'),
            service('messenger.default_bus'),
            service('debug.event_dispatcher')->ignoreOnInvalid(),
        ]);

    $services->alias('test.browser', 'test.client');

    $services->set('test.client', TestBrowser::class)
        ->share(false)
        ->public()
        ->args([
            service('kernel'),
            param('test.client.parameters'),
            service('test.client.history'),
            service('test.client.cookiejar'),
        ]);

    $services->set(NullHandler::class);
    $services->set(TestMessageHandler::class)->tag('messenger.message_handler');

    $services->set(TestCacheClearer::class)
        ->args([
            [service('cache.object'), service('cache.http')],
            service('cache_clearer'),
            param('kernel.cache_dir'),
        ]);

    $services->set(AclTestController::class)->public();
    $services->set(TestSessionStorageFactory::class)->decorate('session.storage.factory.mock_file');
    $services->alias('test.string_template_renderer', 'Contena\\Core\\Framework\\Adapter\\Twig\\StringTemplateRenderer');
};
