<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Mcp\Feature\McpPromptFeatureDefinition;
use Contena\Core\Framework\App\Mcp\Feature\McpResourceFeatureDefinition;
use Contena\Core\Framework\App\Mcp\Feature\McpToolFeatureDefinition;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Mcp\Controller\McpServerController;
use Contena\Core\Framework\Mcp\Controller\StoreApiMcpServerController;
use Contena\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
use Contena\Core\Framework\Mcp\Loader\AppMcpToolLoader;
use Contena\Core\Framework\Mcp\McpCapabilityCatalog;
use Contena\Core\Framework\Mcp\McpToolsetRegistry;
use Contena\Core\Framework\Mcp\McpToolsetSessionStorage;
use Contena\Core\Framework\Mcp\Prompt\ContenaContextPrompt;
use Contena\Core\Framework\Mcp\Resource\BusinessEventsResource;
use Contena\Core\Framework\Mcp\Resource\CurrencyListResource;
use Contena\Core\Framework\Mcp\Resource\EntityListResource;
use Contena\Core\Framework\Mcp\Resource\ExtensionsResource;
use Contena\Core\Framework\Mcp\Resource\FlowActionsResource;
use Contena\Core\Framework\Mcp\Resource\LanguageListResource;
use Contena\Core\Framework\Mcp\Resource\ChannelListResource;
use Contena\Core\Framework\Mcp\Resource\StateMachineResource;
use Contena\Core\Framework\Mcp\Tool\EntityAggregateTool;
use Contena\Core\Framework\Mcp\Tool\EntityDeleteTool;
use Contena\Core\Framework\Mcp\Tool\EntityReadTool;
use Contena\Core\Framework\Mcp\Tool\EntitySchemaTool;
use Contena\Core\Framework\Mcp\Tool\EntitySearchTool;
use Contena\Core\Framework\Mcp\Tool\EntityUpsertTool;
use Contena\Core\Framework\Mcp\Tool\MediaUploadTool;
use Contena\Core\Framework\Mcp\Tool\OrderStateTool;
use Contena\Core\Framework\Mcp\Tool\SystemConfigReadTool;
use Contena\Core\Framework\Mcp\Tool\SystemConfigWriteTool;
use Contena\Core\Framework\Mcp\Tool\ToolsetEnableTool;
use Contena\Core\Framework\Mcp\Tool\ToolsetsListTool;
use Contena\Core\System\Channel\Mcp\Tool\StoreApiContextTool;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
#[CoversNothing]
class McpServiceConfigTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $loader = new PhpFileLoader($this->container, new FileLocator());
        $loader->load(__DIR__ . '/../../../../../src/Core/Framework/DependencyInjection/mcp.php');
    }

    #[DataProvider('expectedServiceProvider')]
    public function testServiceIsRegistered(string $serviceId): void
    {
        static::assertTrue(
            $this->container->hasDefinition($serviceId),
            \sprintf('Service "%s" is not registered', $serviceId),
        );
    }

    #[DataProvider('toolServiceProvider')]
    public function testToolServiceIsTagged(string $serviceId): void
    {
        static::assertTrue(
            $this->container->getDefinition($serviceId)->hasTag('mcp.tool'),
            \sprintf('Service "%s" is not tagged with mcp.tool', $serviceId),
        );
    }

    #[DataProvider('resourceServiceProvider')]
    public function testResourceServiceIsTagged(string $serviceId): void
    {
        static::assertTrue(
            $this->container->getDefinition($serviceId)->hasTag('mcp.resource'),
            \sprintf('Service "%s" is not tagged with mcp.resource', $serviceId),
        );
    }

    public function testPromptServiceIsTagged(): void
    {
        static::assertTrue($this->container->getDefinition(ContenaContextPrompt::class)->hasTag('mcp.prompt'));
    }

    public function testAppMcpToolLoaderIsTaggedAsMcpLoader(): void
    {
        static::assertTrue($this->container->getDefinition(AppMcpToolLoader::class)->hasTag('mcp.loader'));
    }

    public function testControllerIsPublic(): void
    {
        static::assertTrue($this->container->getDefinition(McpServerController::class)->isPublic());
        static::assertTrue($this->container->getDefinition(StoreApiMcpServerController::class)->isPublic());
    }

    public function testStoreApiToolServiceIsTagged(): void
    {
        static::assertTrue($this->container->getDefinition(StoreApiContextTool::class)->hasTag('contena.store_api_mcp.tool'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function expectedServiceProvider(): iterable
    {
        yield McpToolFeatureDefinition::class => [McpToolFeatureDefinition::class];
        yield McpPromptFeatureDefinition::class => [McpPromptFeatureDefinition::class];
        yield McpResourceFeatureDefinition::class => [McpResourceFeatureDefinition::class];
        yield McpServerController::class => [McpServerController::class];
        yield StoreApiMcpServerController::class => [StoreApiMcpServerController::class];
        yield EntitySchemaTool::class => [EntitySchemaTool::class];
        yield EntitySearchTool::class => [EntitySearchTool::class];
        yield EntityAggregateTool::class => [EntityAggregateTool::class];
        yield EntityReadTool::class => [EntityReadTool::class];
        yield EntityUpsertTool::class => [EntityUpsertTool::class];
        yield EntityDeleteTool::class => [EntityDeleteTool::class];
        yield SystemConfigReadTool::class => [SystemConfigReadTool::class];
        yield SystemConfigWriteTool::class => [SystemConfigWriteTool::class];
        yield OrderStateTool::class => [OrderStateTool::class];
        yield MediaUploadTool::class => [MediaUploadTool::class];
        yield StoreApiContextTool::class => [StoreApiContextTool::class];
        yield ToolsetsListTool::class => [ToolsetsListTool::class];
        yield ToolsetEnableTool::class => [ToolsetEnableTool::class];
        yield ContenaContextPrompt::class => [ContenaContextPrompt::class];
        yield EntityListResource::class => [EntityListResource::class];
        yield BusinessEventsResource::class => [BusinessEventsResource::class];
        yield FlowActionsResource::class => [FlowActionsResource::class];
        yield ChannelListResource::class => [ChannelListResource::class];
        yield CurrencyListResource::class => [CurrencyListResource::class];
        yield LanguageListResource::class => [LanguageListResource::class];
        yield StateMachineResource::class => [StateMachineResource::class];
        yield ExtensionsResource::class => [ExtensionsResource::class];
        yield AppMcpCapabilityExecutor::class => [AppMcpCapabilityExecutor::class];
        yield AppMcpToolLoader::class => [AppMcpToolLoader::class];
        yield McpCapabilityCatalog::class => [McpCapabilityCatalog::class];
        yield McpToolsetRegistry::class => [McpToolsetRegistry::class];
        yield McpToolsetSessionStorage::class => [McpToolsetSessionStorage::class];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function toolServiceProvider(): iterable
    {
        yield EntitySchemaTool::class => [EntitySchemaTool::class];
        yield EntitySearchTool::class => [EntitySearchTool::class];
        yield EntityAggregateTool::class => [EntityAggregateTool::class];
        yield EntityReadTool::class => [EntityReadTool::class];
        yield EntityUpsertTool::class => [EntityUpsertTool::class];
        yield EntityDeleteTool::class => [EntityDeleteTool::class];
        yield SystemConfigReadTool::class => [SystemConfigReadTool::class];
        yield SystemConfigWriteTool::class => [SystemConfigWriteTool::class];
        yield OrderStateTool::class => [OrderStateTool::class];
        yield MediaUploadTool::class => [MediaUploadTool::class];
        yield ToolsetsListTool::class => [ToolsetsListTool::class];
        yield ToolsetEnableTool::class => [ToolsetEnableTool::class];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function resourceServiceProvider(): iterable
    {
        yield EntityListResource::class => [EntityListResource::class];
        yield BusinessEventsResource::class => [BusinessEventsResource::class];
        yield FlowActionsResource::class => [FlowActionsResource::class];
        yield ChannelListResource::class => [ChannelListResource::class];
        yield CurrencyListResource::class => [CurrencyListResource::class];
        yield LanguageListResource::class => [LanguageListResource::class];
        yield StateMachineResource::class => [StateMachineResource::class];
        yield ExtensionsResource::class => [ExtensionsResource::class];
    }
}
