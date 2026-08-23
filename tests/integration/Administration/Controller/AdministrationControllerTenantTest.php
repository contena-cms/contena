<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Administration\Controller;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Administration\Controller\AdministrationController;
use Contena\Administration\Framework\Routing\KnownIps\KnownIpsCollectorInterface;
use Contena\Administration\Snippet\SnippetFinderInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Contena\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class AdministrationControllerTenantTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResetExcludedSearchTermsWritesOnlyTheCurrentTenantScope(): void
    {
        $tenantA = $this->createTenant('Search reset tenant A');
        $tenantB = $this->createTenant('Search reset tenant B');
        $configIds = [
            'platform' => $this->platformConfigId(),
            'tenant-a' => $this->createTenantConfig($tenantA->id),
            'tenant-b' => $this->createTenantConfig($tenantB->id),
        ];
        $contexts = [
            'platform' => Context::createDefaultContext(),
            'tenant-a' => Context::createTenantContext($tenantA->id),
            'tenant-b' => Context::createTenantContext($tenantB->id),
            'global' => Context::createGlobalContext(),
        ];

        foreach ($contexts as $scope => $context) {
            $markers = $this->resetMarkers($configIds, $scope);

            $this->controller()->resetExcludedSearchTerm($context);

            $expectedTarget = $scope === 'global' ? 'platform' : $scope;
            foreach ($configIds as $configScope => $configId) {
                $excludedTerms = $this->excludedTerms($configId);
                if ($configScope === $expectedTarget) {
                    static::assertNotSame($markers[$configScope], $excludedTerms, $scope . ' did not reset its own configuration');

                    continue;
                }

                static::assertSame($markers[$configScope], $excludedTerms, $scope . ' changed ' . $configScope . ' configuration');
            }
        }
    }

    private function platformConfigId(): string
    {
        $id = $this->connection()->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `blog_search_config` WHERE `language_id` = :languageId AND `tenant_id` IS NULL',
            ['languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)],
        );
        static::assertIsString($id);

        return $id;
    }

    private function createTenantConfig(string $tenantId): string
    {
        $id = Uuid::randomHex();
        $this->connection()->insert('blog_search_config', [
            'tenant_id' => Uuid::fromHexToBytes($tenantId),
            'id' => Uuid::fromHexToBytes($id),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'and_logic' => 1,
            'min_search_length' => 2,
            'excluded_terms' => '[]',
            'created_at' => new \DateTimeImmutable()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $id;
    }

    /**
     * @param array<string, string> $configIds
     *
     * @return array<string, list<string>>
     */
    private function resetMarkers(array $configIds, string $operationScope): array
    {
        $markers = [];
        foreach ($configIds as $configScope => $configId) {
            $markers[$configScope] = [$operationScope . '-' . $configScope];
            $this->connection()->update('blog_search_config', [
                'excluded_terms' => json_encode($markers[$configScope], \JSON_THROW_ON_ERROR),
            ], [
                'id' => Uuid::fromHexToBytes($configId),
            ]);
        }

        return $markers;
    }

    /**
     * @return list<string>
     */
    private function excludedTerms(string $configId): array
    {
        $excludedTerms = $this->connection()->fetchOne(
            'SELECT `excluded_terms` FROM `blog_search_config` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($configId)],
        );
        static::assertIsString($excludedTerms);

        /** @var list<string> $decoded */
        $decoded = json_decode($excludedTerms, true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function controller(): AdministrationController
    {
        /** @var EntityRepository<LanguageCollection>&Stub $languageRepository */
        $languageRepository = static::createStub(EntityRepository::class);
        $eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(static fn (object $event): object => $event);

        return new AdministrationController(
            static::createStub(TemplateFinderInterface::class),
            static::createStub(SnippetFinderInterface::class),
            [],
            static::createStub(KnownIpsCollectorInterface::class),
            static::createStub(HtmlSanitizer::class),
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(FilesystemOperator::class),
            $languageRepository,
            static::createStub(SymfonyBearerTokenValidator::class),
            $this->connection(),
            $eventDispatcher,
            \dirname(__DIR__, 4) . '/src/Core',
        );
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
