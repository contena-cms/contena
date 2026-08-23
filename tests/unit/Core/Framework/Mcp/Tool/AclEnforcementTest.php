<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Acl\AclCriteriaValidator;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;
use Contena\Core\Framework\Mcp\Tool\EntityAggregateTool;
use Contena\Core\Framework\Mcp\Tool\EntityDeleteTool;
use Contena\Core\Framework\Mcp\Tool\EntityReadTool;
use Contena\Core\Framework\Mcp\Tool\EntitySearchTool;
use Contena\Core\Framework\Mcp\Tool\EntityUpsertTool;
use Contena\Core\Framework\Mcp\Tool\McpToolResponse;
use Contena\Core\Framework\Mcp\Tool\SystemConfigReadTool;
use Contena\Core\Framework\Mcp\Tool\SystemConfigWriteTool;
use Contena\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[CoversClass(McpToolResponse::class)]
class AclEnforcementTest extends TestCase
{
    public function testEntitySearchToolDenied(): void
    {
        $tool = new EntitySearchTool(
            $this->createRegistryWithEntity(),
            static::createStub(RequestCriteriaBuilder::class),
            $this->createDeniedContextProvider(),
            static::createStub(JsonEntityEncoder::class),
            static::createStub(AclCriteriaValidator::class),
        );

        $this->assertAclDenied(($tool)('blog'), 'blog:read');
    }

    public function testEntityReadToolDenied(): void
    {
        $tool = new EntityReadTool(
            $this->createRegistryWithEntity(),
            static::createStub(RequestCriteriaBuilder::class),
            $this->createDeniedContextProvider(),
            static::createStub(JsonEntityEncoder::class),
            static::createStub(AclCriteriaValidator::class),
        );

        $this->assertAclDenied(($tool)('blog', 'some-id'), 'blog:read');
    }

    public function testEntityDeleteToolDenied(): void
    {
        $tool = new EntityDeleteTool(
            $this->createRegistryWithEntity(),
            $this->createDeniedContextProvider(),
            static::createStub(Connection::class),
        );

        $this->assertAclDenied(($tool)('blog', 'some-id'), 'blog:delete');
    }

    public function testEntityUpsertToolDenied(): void
    {
        $tool = new EntityUpsertTool(
            $this->createRegistryWithEntity(),
            $this->createDeniedContextProvider(),
            static::createStub(Connection::class),
        );

        $this->assertAclDenied(($tool)('blog', '{"name":"test"}'), 'blog:create');
    }

    public function testSystemConfigReadToolDenied(): void
    {
        $tool = new SystemConfigReadTool(
            static::createStub(SystemConfigService::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('core.listing'), 'system_config:read');
    }

    public function testSystemConfigWriteToolDenied(): void
    {
        $tool = new SystemConfigWriteTool(
            static::createStub(SystemConfigService::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('core.test', '"value"'), 'system_config:update');
    }

    public function testEntityAggregateToolDenied(): void
    {
        $tool = new EntityAggregateTool(
            $this->createRegistryWithEntity(),
            static::createStub(RequestCriteriaBuilder::class),
            $this->createDeniedContextProvider(),
            static::createStub(AclCriteriaValidator::class),
        );

        $this->assertAclDenied(($tool)('blog', '[]'), 'blog:read');
    }

    public function testSystemConfigReadToolAllowed(): void
    {
        $configService = static::createStub(SystemConfigService::class);
        $configService->method('get')->willReturn('test-value');

        $tool = new SystemConfigReadTool(
            $configService,
            $this->createAllowedContextProvider('system_config:read'),
        );

        $this->assertAclAllowed(($tool)('core.listing.defaultSorting'));
    }

    public function testSystemConfigWriteToolAllowed(): void
    {
        $configService = static::createStub(SystemConfigService::class);
        $configService->method('get')->willReturn('old-value');

        $tool = new SystemConfigWriteTool(
            $configService,
            $this->createAllowedContextProvider('system_config:update'),
        );

        $this->assertAclAllowed(($tool)('core.test', '"new-value"'));
    }

    public function testEntityDeleteToolAllowed(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getRepository')->willReturn(static::createStub(EntityRepository::class));

        $tool = new EntityDeleteTool(
            $registry,
            $this->createAllowedContextProvider('blog:delete'),
            static::createStub(Connection::class),
        );

        $this->assertAclAllowed(($tool)('blog', 'some-id'));
    }

    private function createRegistryWithEntity(): DefinitionInstanceRegistry
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('has')->willReturn(true);

        return $registry;
    }

    private function createDeniedContextProvider(): McpContextProvider
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [Defaults::LANGUAGE_SYSTEM]);

        $provider = static::createStub(McpContextProvider::class);
        $provider->method('getContext')->willReturn($context);

        return $provider;
    }

    private function createAllowedContextProvider(string ...$permissions): McpContextProvider
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions($permissions);
        $context = new Context($source, [Defaults::LANGUAGE_SYSTEM]);

        $provider = static::createStub(McpContextProvider::class);
        $provider->method('getContext')->willReturn($context);

        return $provider;
    }

    private function assertAclDenied(string $output, string $expectedPrivilege): void
    {
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertStringContainsString('Missing privilege', $data['error']);
        static::assertStringContainsString($expectedPrivilege, $data['error']);
    }

    private function assertAclAllowed(string $output): void
    {
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success'], 'Tool should succeed when ACL permissions are granted, got: ' . $output);
    }
}
