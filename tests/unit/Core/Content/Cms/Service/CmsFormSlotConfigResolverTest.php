<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Service\CmsFormSlotConfigResolver;
use Contena\Core\Content\LandingPage\LandingPageCollection;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\LandingPage\LandingPageEntity;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(CmsFormSlotConfigResolver::class)]
class CmsFormSlotConfigResolverTest extends TestCase
{
    public function testResolveUsesCmsSlotConfigWhenNoEntityContextIsGiven(): void
    {
        $slotId = Uuid::randomHex();
        $slot = new CmsSlotEntity();
        $slot->setId($slotId);
        $slot->setTranslated(['config' => $this->createSlotConfig('slot@example.com', 'Slot message')]);

        $resolver = $this->createResolver(slotEntities: [$slot]);

        static::assertSame(
            [
                'receivers' => ['slot@example.com' => 'slot@example.com'],
                'message' => 'Slot message',
            ],
            $resolver->resolve($this->createChannelContext(), $slotId, null, null)
        );
    }

    public function testResolveIgnoresMalformedCmsSlotConfig(): void
    {
        $slotId = Uuid::randomHex();
        $slot = new CmsSlotEntity();
        $slot->setId($slotId);
        $slot->setTranslated(['config' => [
            'mailReceiver' => null,
            'confirmationText' => null,
        ]]);

        $resolver = $this->createResolver(slotEntities: [$slot]);

        static::assertSame(
            ['receivers' => ['' => ''], 'message' => ''],
            $resolver->resolve($this->createChannelContext(), $slotId, null, null)
        );
    }

    /**
     * @param class-string<CategoryEntity|LandingPageEntity|BlogEntity> $entityClass
     */
    #[DataProvider('entityProvider')]
    public function testResolveUsesEntitySpecificConfig(string $entityClass, string $entityName): void
    {
        $slotId = Uuid::randomHex();
        $navigationId = Uuid::randomHex();
        $entity = new $entityClass();
        $entity->setId($navigationId);
        $entity->setSlotConfig([
            $slotId => $this->createSlotConfig('entity@example.com', 'Entity message'),
        ]);

        $resolver = $this->createResolver(
            categoryEntities: $entity instanceof CategoryEntity ? [$entity] : [],
            landingPageEntities: $entity instanceof LandingPageEntity ? [$entity] : [],
            blogEntities: $entity instanceof BlogEntity ? [$entity] : [],
        );

        static::assertSame(
            [
                'receivers' => ['entity@example.com' => 'entity@example.com'],
                'message' => 'Entity message',
            ],
            $resolver->resolve($this->createChannelContext(), $slotId, $navigationId, $entityName)
        );
    }

    public static function entityProvider(): \Generator
    {
        yield 'category' => [CategoryEntity::class, CategoryDefinition::ENTITY_NAME];
        yield 'landing page' => [LandingPageEntity::class, LandingPageDefinition::ENTITY_NAME];
        yield 'blog' => [BlogEntity::class, BlogDefinition::ENTITY_NAME];
    }

    /**
     * @param array<string, array{value: list<string>|string}> $entityConfig
     * @param array{receivers: array<string, string>, message: string} $expected
     */
    #[DataProvider('partialConfigProvider')]
    public function testResolveInheritsEachFieldIndependently(array $entityConfig, array $expected): void
    {
        $slotId = Uuid::randomHex();
        $navigationId = Uuid::randomHex();

        $slot = new CmsSlotEntity();
        $slot->setId($slotId);
        $slot->setTranslated(['config' => $this->createSlotConfig('slot@example.com', 'Slot message')]);

        $landingPage = new LandingPageEntity();
        $landingPage->setId($navigationId);
        $landingPage->setSlotConfig([$slotId => $entityConfig]);

        $resolver = $this->createResolver(
            slotEntities: [$slot],
            landingPageEntities: [$landingPage],
        );

        static::assertSame(
            $expected,
            $resolver->resolve(
                $this->createChannelContext(),
                $slotId,
                $navigationId,
                LandingPageDefinition::ENTITY_NAME
            )
        );
    }

    public function testResolveIgnoresMalformedEntityConfigValues(): void
    {
        $slotId = Uuid::randomHex();
        $navigationId = Uuid::randomHex();
        $landingPage = new LandingPageEntity();
        $landingPage->setId($navigationId);
        $landingPage->setSlotConfig([$slotId => [
            'mailReceiver' => ['value' => null],
            'confirmationText' => ['value' => null],
        ]]);

        $resolver = $this->createResolver(landingPageEntities: [$landingPage]);

        static::assertSame(
            ['receivers' => ['' => ''], 'message' => ''],
            $resolver->resolve(
                $this->createChannelContext(),
                $slotId,
                $navigationId,
                LandingPageDefinition::ENTITY_NAME
            )
        );
    }

    public function testResolveUsesDefaultsWhenEntityHasNoSlotConfig(): void
    {
        $slotId = Uuid::randomHex();
        $navigationId = Uuid::randomHex();
        $landingPage = new LandingPageEntity();
        $landingPage->setId($navigationId);

        $resolver = $this->createResolver(landingPageEntities: [$landingPage]);

        static::assertSame(
            ['receivers' => ['' => ''], 'message' => ''],
            $resolver->resolve(
                $this->createChannelContext(),
                $slotId,
                $navigationId,
                LandingPageDefinition::ENTITY_NAME
            )
        );
    }

    public function testResolveUsesDefaultsWhenEntitySlotConfigIsNotAnArray(): void
    {
        $slotId = Uuid::randomHex();
        $navigationId = Uuid::randomHex();
        $landingPage = new LandingPageEntity();
        $landingPage->setId($navigationId);
        $landingPage->setSlotConfig([$slotId => null]);

        $resolver = $this->createResolver(landingPageEntities: [$landingPage]);

        static::assertSame(
            ['receivers' => ['' => ''], 'message' => ''],
            $resolver->resolve(
                $this->createChannelContext(),
                $slotId,
                $navigationId,
                LandingPageDefinition::ENTITY_NAME
            )
        );
    }

    public static function partialConfigProvider(): \Generator
    {
        yield 'entity receiver inherits message' => [
            ['mailReceiver' => ['value' => ['entity@example.com']]],
            ['receivers' => ['entity@example.com' => 'entity@example.com'], 'message' => 'Slot message'],
        ];

        yield 'entity message inherits receiver' => [
            ['confirmationText' => ['value' => 'Entity message']],
            ['receivers' => ['slot@example.com' => 'slot@example.com'], 'message' => 'Entity message'],
        ];

        yield 'explicit empty entity message is preserved' => [
            ['confirmationText' => ['value' => '']],
            ['receivers' => ['slot@example.com' => 'slot@example.com'], 'message' => ''],
        ];
    }

    public function testResolveUsesSystemDefaultsWhenNoConfigCanBeResolved(): void
    {
        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('getString')->willReturnCallback(
            static fn (string $key): string => match ($key) {
                'core.basicInformation.email' => 'default@example.com',
                'core.basicInformation.shopName' => 'Contena',
                default => '',
            }
        );

        $resolver = $this->createResolver(systemConfigService: $systemConfigService);

        static::assertSame(
            [
                'receivers' => ['default@example.com' => 'Contena'],
                'message' => '',
            ],
            $resolver->resolve($this->createChannelContext(), null, null, null)
        );
    }

    /**
     * @param array<int, CmsSlotEntity> $slotEntities
     * @param array<int, CategoryEntity> $categoryEntities
     * @param array<int, LandingPageEntity> $landingPageEntities
     * @param array<int, BlogEntity> $blogEntities
     */
    private function createResolver(
        array $slotEntities = [],
        array $categoryEntities = [],
        array $landingPageEntities = [],
        array $blogEntities = [],
        ?SystemConfigService $systemConfigService = null,
    ): CmsFormSlotConfigResolver {
        $cmsSlotRepository = StaticEntityRepository::of(CmsSlotCollection::class, [$slotEntities], new CmsSlotDefinition());
        $categoryRepository = StaticEntityRepository::of(CategoryCollection::class, [$categoryEntities], new CategoryDefinition());
        $landingPageRepository = StaticEntityRepository::of(LandingPageCollection::class, [$landingPageEntities], new LandingPageDefinition());
        $blogRepository = StaticEntityRepository::of(BlogCollection::class, [$blogEntities], new BlogDefinition());

        return new CmsFormSlotConfigResolver(
            $categoryRepository,
            $landingPageRepository,
            $blogRepository,
            $cmsSlotRepository,
            $systemConfigService ?? static::createStub(SystemConfigService::class),
        );
    }

    /**
     * @return array{
     *     mailReceiver: array{value: array<int, string>},
     *     confirmationText: array{value: string}
     * }
     */
    private function createSlotConfig(string $receiver, string $message): array
    {
        return [
            'mailReceiver' => ['value' => [$receiver]],
            'confirmationText' => ['value' => $message],
        ];
    }

    private function createChannelContext(): ChannelContext
    {
        return Generator::generateChannelContext(Context::createDefaultContext());
    }
}
