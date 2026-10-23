<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\StoreException;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Frontend\Framework\Store\ThemeExtensionRemovalValidator;
use Contena\Frontend\Theme\ThemeCollection;

/**
 * @internal
 */
#[CoversClass(ThemeExtensionRemovalValidator::class)]
class ThemeExtensionRemovalValidatorTest extends TestCase
{
    private const THEME_ID = 'theme-id';

    public function testExtensionWithoutThemeCanBeRemoved(): void
    {
        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository->expects($this->never())->method('aggregate');

        $validator = new ThemeExtensionRemovalValidator(
            StaticEntityRepository::of(ThemeCollection::class, [[]]),
            $channelRepository,
        );

        $validator->validateCanBeRemoved('SwagPlugin', 'extension-id', Context::createDefaultContext());
    }

    public function testThemeAssignedToAChannelCannotBeRemoved(): void
    {
        $validator = new ThemeExtensionRemovalValidator(
            StaticEntityRepository::of(ThemeCollection::class, [[self::THEME_ID]]),
            $this->buildChannelRepository(directlyAssigned: [self::THEME_ID], assignedChildren: []),
        );

        $this->expectExceptionObject(StoreException::extensionThemeStillInUse('extension-id'));

        $validator->validateCanBeRemoved('SwagTheme', 'extension-id', Context::createDefaultContext());
    }

    public function testThemeWithAnAssignedChildThemeCannotBeRemoved(): void
    {
        $validator = new ThemeExtensionRemovalValidator(
            StaticEntityRepository::of(ThemeCollection::class, [[self::THEME_ID]]),
            $this->buildChannelRepository(directlyAssigned: [], assignedChildren: [self::THEME_ID]),
        );

        $this->expectExceptionObject(StoreException::extensionThemeStillInUse('extension-id'));

        $validator->validateCanBeRemoved('SwagTheme', 'extension-id', Context::createDefaultContext());
    }

    public function testUnassignedThemeCanBeRemoved(): void
    {
        $channelRepository = $this->createMock(EntityRepository::class);
        $channelRepository
            ->expects($this->once())
            ->method('aggregate')
            ->willReturn($this->buildAggregationResult(directlyAssigned: [], assignedChildren: []));

        $validator = new ThemeExtensionRemovalValidator(
            StaticEntityRepository::of(ThemeCollection::class, [[self::THEME_ID]]),
            $channelRepository,
        );

        $validator->validateCanBeRemoved('SwagTheme', 'extension-id', Context::createDefaultContext());
    }

    /**
     * @param list<string> $directlyAssigned
     * @param list<string> $assignedChildren
     *
     * @return EntityRepository<ChannelCollection>
     */
    private function buildChannelRepository(array $directlyAssigned, array $assignedChildren): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('aggregate')->willReturn($this->buildAggregationResult($directlyAssigned, $assignedChildren));

        return $repository;
    }

    /**
     * @param list<string> $directlyAssigned
     * @param list<string> $assignedChildren
     */
    private function buildAggregationResult(array $directlyAssigned, array $assignedChildren): AggregationResultCollection
    {
        $toBucket = static fn (string $themeId): Bucket => new Bucket($themeId, 1, null);

        return new AggregationResultCollection([
            new TermsResult('assigned_theme', array_map($toBucket, $directlyAssigned)),
            new TermsResult('assigned_children', array_map($toBucket, $assignedChildren)),
        ]);
    }
}
