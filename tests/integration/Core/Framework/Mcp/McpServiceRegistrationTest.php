<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Mcp;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\Command\DebugMcpCommand;
use Contena\Core\Framework\Mcp\Context\ChannelApiMcpContextProvider;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;
use Contena\Core\Framework\Mcp\Controller\ChannelApiMcpServerController;
use Contena\Core\Framework\Mcp\Controller\IntegrationMcpAllowlistController;
use Contena\Core\Framework\Mcp\Controller\McpServerController;
use Contena\Core\Framework\Mcp\Controller\McpToolListController;
use Contena\Core\Framework\Mcp\Controller\UserMcpAllowlistController;
use Contena\Core\Framework\Mcp\McpCapabilityCatalog;
use Contena\Core\Framework\Mcp\McpToolsetRegistry;
use Contena\Core\Framework\Mcp\McpToolsetSessionStorage;
use Contena\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Contena\Core\Framework\Mcp\Notification\McpSessionRegistry;
use Contena\Core\Framework\Mcp\Prompt\ContenaContextPrompt;
use Contena\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Contena\Core\Framework\Mcp\Resource\BusinessEventsResource;
use Contena\Core\Framework\Mcp\Resource\ChannelListResource;
use Contena\Core\Framework\Mcp\Resource\EntityListResource;
use Contena\Core\Framework\Mcp\Resource\ExtensionsResource;
use Contena\Core\Framework\Mcp\Resource\FlowActionsResource;
use Contena\Core\Framework\Mcp\Resource\LanguageListResource;
use Contena\Core\Framework\Mcp\Resource\StateMachineResource;
use Contena\Core\Framework\Mcp\Tool\EntityAggregateTool;
use Contena\Core\Framework\Mcp\Tool\EntityDeleteTool;
use Contena\Core\Framework\Mcp\Tool\EntityReadTool;
use Contena\Core\Framework\Mcp\Tool\EntitySchemaTool;
use Contena\Core\Framework\Mcp\Tool\EntitySearchTool;
use Contena\Core\Framework\Mcp\Tool\EntityUpsertTool;
use Contena\Core\Framework\Mcp\Tool\MediaUploadTool;
use Contena\Core\Framework\Mcp\Tool\SystemConfigReadTool;
use Contena\Core\Framework\Mcp\Tool\SystemConfigWriteTool;
use Contena\Core\Framework\Mcp\Tool\ToolSearchTool;
use Contena\Core\Framework\Mcp\Tool\ToolsetEnableTool;
use Contena\Core\Framework\Mcp\Tool\ToolsetsListTool;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\System\Channel\Mcp\Tool\ChannelApiContextTool;
use Contena\Core\System\Channel\Mcp\Tool\ChannelApiToolSearchTool;
use Contena\Core\System\Channel\Mcp\Tool\ChannelApiToolsetEnableTool;
use Contena\Core\System\Channel\Mcp\Tool\ChannelApiToolsetsListTool;

/**
 * @internal
 */
class McpServiceRegistrationTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function serviceProvider(): iterable
    {
        foreach ([
            McpContextProvider::class,
            ChannelApiMcpContextProvider::class,
            McpServerController::class,
            ChannelApiMcpServerController::class,
            McpToolListController::class,
            IntegrationMcpAllowlistController::class,
            UserMcpAllowlistController::class,
            DebugMcpCommand::class,
            McpCapabilityCatalog::class,
            McpToolsetRegistry::class,
            McpToolsetSessionStorage::class,
            McpListChangedNotifier::class,
            McpSessionRegistry::class,
            McpRateLimiter::class,
            ToolSearchTool::class,
            ToolsetsListTool::class,
            ToolsetEnableTool::class,
            ChannelApiContextTool::class,
            ChannelApiToolSearchTool::class,
            ChannelApiToolsetsListTool::class,
            ChannelApiToolsetEnableTool::class,
            EntitySchemaTool::class,
            EntitySearchTool::class,
            EntityAggregateTool::class,
            EntityReadTool::class,
            EntityUpsertTool::class,
            EntityDeleteTool::class,
            SystemConfigReadTool::class,
            SystemConfigWriteTool::class,
            MediaUploadTool::class,
            ContenaContextPrompt::class,
            EntityListResource::class,
            BusinessEventsResource::class,
            ExtensionsResource::class,
            FlowActionsResource::class,
            ChannelListResource::class,
            LanguageListResource::class,
            StateMachineResource::class,
        ] as $service) {
            yield $service => [$service];
        }
    }

    /**
     * @param class-string $service
     */
    #[DataProvider('serviceProvider')]
    public function testServiceIsRegistered(string $service): void
    {
        static::assertTrue(static::getContainer()->has($service), $service);
    }
}
