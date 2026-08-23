<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Administration\Controller;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Administration\Controller\AdministrationController;
use Contena\Administration\Events\PreResetExcludedSearchTermEvent;
use Contena\Administration\Framework\Routing\KnownIps\KnownIpsCollectorInterface;
use Contena\Administration\Snippet\SnippetFinderInterface;
use Contena\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(AdministrationController::class)]
class AdministrationControllerTest extends TestCase
{
    public function testResetExcludedSearchTermThrowsRoutingException(): void
    {
        $context = Context::createDefaultContext();
        $this->expectExceptionObject(RoutingException::languageNotFound($context->getLanguageId()));

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchOne')->willReturn(false);

        $this->createAdministrationController($connection)->resetExcludedSearchTerm($context);
    }

    /**
     * @param list<string> $excludedTerms
     */
    #[DataProvider('excludedTerms')]
    public function testResetExcludedSearchTerm(Context $context, string|false $englishLanguageId, array $excludedTerms): void
    {
        $searchConfigId = Uuid::randomBytes();
        $tenantId = $context->getTenantId();
        $tenantCondition = $tenantId === null ? 'tenant_id IS NULL' : 'tenant_id = :tenant_id';
        $tenantParameters = $tenantId === null ? [] : ['tenant_id' => Uuid::fromHexToBytes($tenantId)];

        $connection = $this->createMock(Connection::class);
        $fetchCount = 0;
        $connection->method('fetchOne')->willReturnCallback(function (string $query, array $parameters) use (&$fetchCount, $searchConfigId, $englishLanguageId, $context, $tenantCondition, $tenantParameters): string|false {
            if ($fetchCount++ === 0) {
                static::assertSame('SELECT id FROM blog_search_config WHERE language_id = :language_id AND ' . $tenantCondition, $query);
                static::assertSame([
                    'language_id' => Uuid::fromHexToBytes($context->getLanguageId()),
                    ...$tenantParameters,
                ], $parameters);

                return $searchConfigId;
            }

            return $englishLanguageId;
        });

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        if ($englishLanguageId === false) {
            $eventDispatcher->expects($this->once())
                ->method('dispatch')
                ->willReturn(new PreResetExcludedSearchTermEvent($searchConfigId, $excludedTerms, $context));
        } else {
            $eventDispatcher->expects($this->never())->method('dispatch');
        }

        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'UPDATE `blog_search_config` SET `excluded_terms` = :excludedTerms WHERE `id` = :id AND ' . $tenantCondition,
                [
                    'excludedTerms' => json_encode($excludedTerms, \JSON_THROW_ON_ERROR),
                    'id' => $searchConfigId,
                    ...$tenantParameters,
                ]
            );

        $response = $this->createAdministrationController($connection, $eventDispatcher)->resetExcludedSearchTerm($context);

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"success":true}', $response->getContent());
    }

    public static function excludedTerms(): \Generator
    {
        $englishLanguageId = Uuid::randomHex();
        $englishTerms = require __DIR__ . '/../../../../src/Core/Migration/Fixtures/stopwords/en.php';

        yield 'default language uses the extension event' => [
            Context::createDefaultContext(),
            false,
            ['的', '了'],
        ];

        yield 'English uses the upstream stopword list' => [
            new Context(new SystemSource(), [$englishLanguageId]),
            Uuid::fromHexToBytes($englishLanguageId),
            $englishTerms,
        ];

        yield 'tenant resets only its own configuration' => [
            Context::createTenantContext(Uuid::randomHex()),
            false,
            ['tenant-specific'],
        ];
    }

    private function createAdministrationController(
        Connection $connection,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): AdministrationController {
        /** @var EntityRepository<LanguageCollection>&Stub $languageRepository */
        $languageRepository = static::createStub(EntityRepository::class);

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
            $connection,
            $eventDispatcher ?? static::createStub(EventDispatcherInterface::class),
            __DIR__ . '/../../../../src/Core',
        );
    }
}
