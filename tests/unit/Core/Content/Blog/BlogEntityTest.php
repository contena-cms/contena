<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\Adapter\Twig\TwigEnvironment;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(BlogEntity::class)]
class BlogEntityTest extends TestCase
{
    public function testStringify(): void
    {
        $entity = new BlogEntity();
        $entity->setId('fooId');

        static::assertSame('', (string) $entity);

        $entity->setName('foo');

        static::assertSame('foo', (string) $entity);

        $entity->setTranslated([
            'name' => 'translated foo',
        ]);

        static::assertSame('translated foo', (string) $entity);
    }

    #[DataProvider('twigOutputProvider')]
    public function testRendersInTwigAsItsDisplayName(BlogEntity $blog, string $expected): void
    {
        $twig = new TwigEnvironment(new ArrayLoader([
            'test.html.twig' => '{% if page.blog is empty %}empty{% else %}{{ page.blog }}{% endif %}',
        ]));

        static::assertSame($expected, $twig->render('test.html.twig', ['page' => ['blog' => $blog]]));
    }

    public static function twigOutputProvider(): \Generator
    {
        yield 'a blog without any name counts as empty' => [self::blog(name: null, translated: []), 'empty'];
        yield 'a blog prints its name' => [self::blog(name: 'foo', translated: []), 'foo'];
        yield 'a blog prints its translated name' => [self::blog(name: null, translated: ['name' => 'translated foo']), 'translated foo'];
        yield 'the translated name wins over the name' => [self::blog(name: 'foo', translated: ['name' => 'translated foo']), 'translated foo'];
    }

    public function testDeliveryDateSpansTomorrowToTheDayAfter(): void
    {
        $deliveryDate = (new BlogEntity())->getDeliveryDate();

        static::assertTrue($deliveryDate->getEarliest() < $deliveryDate->getLatest());
    }

    public function testRestockDeliveryDateShiftsByTheRestockTime(): void
    {
        $blog = new BlogEntity();
        $blog->setRestockTime(3);

        $deliveryDate = $blog->getDeliveryDate();
        $restockDate = $blog->getRestockDeliveryDate();

        static::assertEquals($deliveryDate->getEarliest()->modify('+3 day'), $restockDate->getEarliest());
    }

    public function testIsReleasedWithoutAReleaseDate(): void
    {
        static::assertTrue((new BlogEntity())->isReleased());
    }

    public function testIsReleasedComparesTheReleaseDateWithNow(): void
    {
        $released = new BlogEntity();
        $released->setReleaseDate(new \DateTimeImmutable('-1 day'));
        static::assertTrue($released->isReleased());

        $upcoming = new BlogEntity();
        $upcoming->setReleaseDate(new \DateTimeImmutable('+1 day'));
        static::assertFalse($upcoming->isReleased());
    }

    public function testIsGuaranteeConfirmedTreatsAnInheritedValueAsUnconfirmed(): void
    {
        $blog = new BlogEntity();

        static::assertNull($blog->get('guaranteeConfirmed'));
        static::assertFalse($blog->isGuaranteeConfirmed());

        $blog->setGuaranteeConfirmed(true);

        static::assertTrue($blog->isGuaranteeConfirmed());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testStatesRoundTripOnTheLegacyPath(): void
    {
        $entity = new BlogEntity();
        $entity->setStates(['is-physical']);

        static::assertSame(['is-physical'], $entity->getStates());
    }

    public function testGetStatesThrowsWhenFeatureActive(): void
    {
        $this->expectException(FeatureException::class);
        (new BlogEntity())->getStates();
    }

    public function testSetStatesThrowsWhenFeatureActive(): void
    {
        $this->expectException(FeatureException::class);
        (new BlogEntity())->setStates(['is-physical']);
    }

    /**
     * @param array<string, string> $translated
     */
    private static function blog(?string $name, array $translated): BlogEntity
    {
        $blog = new BlogEntity();
        $blog->setId('fooId');
        $blog->setName($name);
        $blog->setTranslated($translated);

        return $blog;
    }
}
