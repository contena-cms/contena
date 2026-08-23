<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(EntityCacheKeyGenerator::class)]
class EntityCacheKeyGeneratorTest extends TestCase
{
    public function testBuildBlogTag(): void
    {
        static::assertSame('blog-foo', EntityCacheKeyGenerator::buildBlogTag('foo'));
    }

    #[DataProvider('criteriaHashProvider')]
    public function testCriteriaHash(Criteria $criteria, string $hash): void
    {
        $generator = new EntityCacheKeyGenerator();

        static::assertSame($hash, $generator->getCriteriaHash($criteria));
    }

    public static function criteriaHashProvider(): \Generator
    {
        yield 'empty' => [
            new Criteria(),
            '49df289864c88521854da972597eff49',
        ];

        yield 'prefix-filter' => [
            new Criteria()->addFilter(new PrefixFilter('foo', 'bar')),
            '082c27fcf54a9a5ba19d64db6f80252c',
        ];

        // this has a different hash because of a different filter type used
        yield 'suffix-filter' => [
            new Criteria()->addFilter(new SuffixFilter('foo', 'bar')),
            '1ce6be01a5bc50b7045d1b6b29f71ef4',
        ];

        yield 'filter+sort' => [
            new Criteria()->addFilter(new PrefixFilter('foo', 'bar'))->addSorting(new FieldSorting('foo')),
            'ac74a77eacd6195661012006d9509ac5',
        ];

        yield 'filter+sort+sort-desc' => [
            new Criteria()->addFilter(new PrefixFilter('foo', 'bar'))->addSorting(new FieldSorting('foo', FieldSorting::DESCENDING)),
            '7b07db627726837e01f22efac7057c66',
        ];

        yield 'filter+agg' => [
            new Criteria()->addFilter(new PrefixFilter('foo', 'bar'))->addAggregation(new TermsAggregation('foo', 'foo')),
            '6eb0a7a03aafdfb4d47ab8f0f851669d',
        ];
    }

    #[DataProvider('contextHashProvider')]
    public function testContextHash(ChannelContext $compared): void
    {
        $generator = new EntityCacheKeyGenerator();

        static::assertNotSame(
            $generator->getChannelContextHash(Generator::generateChannelContext(), ['content']),
            $generator->getChannelContextHash($compared, ['content'])
        );
    }

    public static function contextHashProvider(): \Generator
    {
        $channelIdContext = Generator::generateChannelContext();
        $channelIdContext->getChannel()->setId('foo');
        yield 'channel id considered for hash' => [$channelIdContext];

        $domainIdContext = Generator::generateChannelContext();
        $domainIdContext->setDomainId('foo');
        yield 'domain id considered for hash' => [$domainIdContext];

        $languageChainContext = Generator::generateChannelContext();
        $languageChainContext->getContext()->assign(['languageIdChain' => ['foo']]);
        yield 'language id chain considered for hash' => [$languageChainContext];

        $versionContext = Generator::generateChannelContext();
        $versionContext->getContext()->assign(['versionId' => 'foo']);
        yield 'version considered for hash' => [$versionContext];

        $rulesContext = Generator::generateChannelContext();
        $rulesContext->setAreaRuleIds(['content' => ['foo']]);
        yield 'rules considered for hash' => [$rulesContext];
    }
}
