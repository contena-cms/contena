<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Mcp;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Mcp\Command\DebugMcpCommand;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;
use Contena\Core\Framework\Mcp\Controller\McpServerController;
use Contena\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
use Contena\Core\Framework\Mcp\Loader\AppMcpPromptLoader;
use Contena\Core\Framework\Mcp\Loader\AppMcpResourceLoader;
use Contena\Core\Framework\Mcp\Loader\AppMcpToolLoader;
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
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 *
 * Verifies that all MCP services are registered in the DI container.
 */
class McpServiceRegistrationTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @return iterable<string, array{string}>
     */
    public static function mcpServiceProvider(): iterable
    {
        yield McpContextProvider::class => [McpContextProvider::class];
        yield McpServerController::class => [McpServerController::class];
        yield DebugMcpCommand::class => [DebugMcpCommand::class];
        yield EntitySchemaTool::class => [EntitySchemaTool::class];
        yield EntitySearchTool::class => [EntitySearchTool::class];
        yield EntityReadTool::class => [EntityReadTool::class];
        yield EntityUpsertTool::class => [EntityUpsertTool::class];
        yield EntityDeleteTool::class => [EntityDeleteTool::class];
        yield SystemConfigReadTool::class => [SystemConfigReadTool::class];
        yield SystemConfigWriteTool::class => [SystemConfigWriteTool::class];
        yield OrderStateTool::class => [OrderStateTool::class];
        yield MediaUploadTool::class => [MediaUploadTool::class];
        yield ContenaContextPrompt::class => [ContenaContextPrompt::class];
        yield EntityListResource::class => [EntityListResource::class];
        yield BusinessEventsResource::class => [BusinessEventsResource::class];
        yield FlowActionsResource::class => [FlowActionsResource::class];
        yield ChannelListResource::class => [ChannelListResource::class];
        yield CurrencyListResource::class => [CurrencyListResource::class];
        yield LanguageListResource::class => [LanguageListResource::class];
        yield StateMachineResource::class => [StateMachineResource::class];
        yield ExtensionsResource::class => [ExtensionsResource::class];
        yield EntityAggregateTool::class => [EntityAggregateTool::class];
        yield AppMcpCapabilityExecutor::class => [AppMcpCapabilityExecutor::class];
        yield AppMcpToolLoader::class => [AppMcpToolLoader::class];
        yield AppMcpPromptLoader::class => [AppMcpPromptLoader::class];
        yield AppMcpResourceLoader::class => [AppMcpResourceLoader::class];
    }

    #[DataProvider('mcpServiceProvider')]
    public function testMcpServiceIsRegistered(string $serviceClass): void
    {
        static::assertTrue(
            static::getContainer()->has($serviceClass),
            \sprintf('Service "%s" should be registered.', $serviceClass),
        );
    }
}
