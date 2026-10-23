<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Currency\CurrencyEntity;
use Contena\Core\System\Tax\TaxCollection;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
#[CoversClass(EntityCacheKeyGenerator::class)]
class EntityCacheKeyGeneratorTest extends TestCase
{
    public function testBuildCmsTag(): void
    {
        static::assertSame('cms-page-foo', EntityCacheKeyGenerator::buildCmsTag('foo'));
    }

    public function testBuildBlogTag(): void
    {
        static::assertSame('blog-foo', EntityCacheKeyGenerator::buildBlogTag('foo'));
    }

    public function testBuildStreamTag(): void
    {
        static::assertSame('blog-stream-foo', EntityCacheKeyGenerator::buildStreamTag('foo'));
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
            (new Criteria())->addFilter(new PrefixFilter('foo', 'bar')),
            'c92cf15754e96eb739e2d8e21925702b',
        ];

        // this has a different hash because of a different filter type used
        yield 'suffix-filter' => [
            (new Criteria())->addFilter(new SuffixFilter('foo', 'bar')),
            'cb65ddf8710573000d93528724ae175d',
        ];

        yield 'filter+sort' => [
            (new Criteria())->addFilter(new PrefixFilter('foo', 'bar'))->addSorting(new FieldSorting('foo')),
            'db15d2a554df1f67ea076b62e7f95717',
        ];

        yield 'filter+sort+sort-desc' => [
            'a5e5fa202a90ddb4c187028e20e421eb',
        ];

        yield 'filter+agg' => [
            (new Criteria())->addFilter(new PrefixFilter('foo', 'bar'))->addAggregation(new TermsAggregation('foo', 'foo')),
            '11bbaf1e7a219491e111f26ce1b5b2c9',
        ];
    }

    #[DataProvider('contextHashProvider')]
    public function testContextHash(ChannelContext $compared): void
    {
        $generator = new EntityCacheKeyGenerator();

        static::assertNotSame(
            $generator->getChannelContextHash(new DummyContext(), ['test']),
            $generator->getChannelContextHash($compared, ['test'])
        );
    }

    public static function contextHashProvider(): \Generator
    {
        yield 'tax state considered for hash' => [
        ];

        yield 'currency id considered for hash' => [
        ];

        yield 'sales channel id considered for hash' => [
        ];

        yield 'language id chain considered for hash' => [
        ];

        yield 'version considered for hash' => [
        ];

        yield 'rounding mode considered for hash' => [
        ];

        yield 'rules considered for hash' => [
        ];
    }
}

/**
 * @internal
 */
class DummyContext extends ChannelContext
{
    public function __construct()
    {
        $source = new ChannelApiSource(TestDefaults::CHANNEL);

        parent::__construct(
            new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM], Defaults::LIVE_VERSION, 1.0, true, CartPrice::TAX_STATE_GROSS),
            'token',
            'domain-id',
            new UserGroupEntity(),
            new TaxCollection(),
            new UserEntity(),
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            Generator::createLanguageInfo(),
        );
    }

    public function setChannelId(string $channelId): self
    {
        $this->channel = (new ChannelEntity())->assign(['id' => $channelId]);

        return $this;
    }

    public function setCurrencyId(string $currencyId): self
    {
        $this->currency = (new CurrencyEntity())->assign(['id' => $currencyId]);

        return $this;
    }

    /**
     * @param non-empty-list<string> $chain
     */
    public function setLanguageChain(array $chain): self
    {
        $this->context->assign(['languageIdChain' => $chain]);

        return $this;
    }

    public function setVersionId(string $versionId): self
    {
        $this->context->assign(['versionId' => $versionId]);

        return $this;
    }

    public function setTaxStateFluent(string $taxState): self
    {
        $this->context->setTaxState($taxState);

        return $this;
    }

    /**
     * @param array<string, string[]> $rules
     */
    public function setAreaRuleIdsFluent(array $rules): self
    {
        $this->setAreaRuleIds($rules);

        return $this;
    }

    public function setItemRoundingFluent(CashRoundingConfig $rounding): self
    {
        $this->itemRounding = $rounding;

        return $this;
    }
}
