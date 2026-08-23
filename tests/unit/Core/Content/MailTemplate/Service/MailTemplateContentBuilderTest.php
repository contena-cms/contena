<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterCollection;
use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterEntity;
use Contena\Core\Content\MailTemplate\Service\MailTemplateContentBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(MailTemplateContentBuilder::class)]
class MailTemplateContentBuilderTest extends TestCase
{
    public function testItWrapsContentWithTheGlobalDefaultHeaderAndFooter(): void
    {
        $headerFooter = new MailHeaderFooterEntity()->assign([
            'id' => Uuid::randomHex(),
            'systemDefault' => true,
            'translated' => [
                'headerHtml' => '<header>head</header>',
                'footerHtml' => '<footer>foot</footer>',
                'headerPlain' => 'head-',
                'footerPlain' => '-foot',
            ],
        ]);

        /** @var StaticEntityRepository<MailHeaderFooterCollection> $repository */
        $repository = new StaticEntityRepository([
            static function (Criteria $criteria, Context $context) use ($headerFooter): MailHeaderFooterCollection {
                static::assertSame(1, $criteria->getLimit());
                static::assertSame('mail-template::load-default-header-footer', $criteria->getTitle());
                static::assertTrue(self::hasEqualsFilter($criteria, 'systemDefault', true));

                return new MailHeaderFooterCollection([$headerFooter]);
            },
        ]);

        $result = new MailTemplateContentBuilder($repository)->build([
            'contentPlain' => 'plain',
            'contentHtml' => '<p>html</p>',
        ], Context::createDefaultContext());

        static::assertSame('head-plain-foot', $result['contentPlain']);
        static::assertSame('<header>head</header><p>html</p><footer>foot</footer>', $result['contentHtml']);
    }

    public function testItReturnsOriginalContentWithoutAGlobalDefault(): void
    {
        /** @var StaticEntityRepository<MailHeaderFooterCollection> $repository */
        $repository = new StaticEntityRepository([new MailHeaderFooterCollection()]);
        $content = [
            'contentPlain' => 'plain',
            'contentHtml' => '<p>html</p>',
        ];

        static::assertSame(
            $content,
            new MailTemplateContentBuilder($repository)->build($content, Context::createDefaultContext()),
        );
    }

    private static function hasEqualsFilter(Criteria $criteria, string $field, string|bool $value): bool
    {
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof EqualsFilter && $filter->getField() === $field && $filter->getValue() === $value) {
                return true;
            }
        }

        return false;
    }
}
