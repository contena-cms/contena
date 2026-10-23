<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterEntity;
use Contena\Core\Content\MailTemplate\Service\MailTemplateContentBuilder;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
#[CoversClass(MailTemplateContentBuilder::class)]
class MailTemplateContentBuilderTest extends TestCase
{
    public function testBuildReturnsOriginalContentWithoutChannel(): void
    {
        $builder = new MailTemplateContentBuilder();

        $content = [
            'contentPlain' => 'plain',
            'contentHtml' => '<p>html</p>',
        ];

        static::assertSame($content, $builder->build($content, null));
    }

    public function testBuildWrapsContentWithTranslatedHeaderAndFooter(): void
    {
        $builder = new MailTemplateContentBuilder();

        $mailHeaderFooter = new MailHeaderFooterEntity();
        $mailHeaderFooter->setTranslated([
            'headerHtml' => '<header>head</header>',
            'footerHtml' => '<footer>foot</footer>',
            'headerPlain' => 'head-',
            'footerPlain' => '-foot',
        ]);

        $channel = new ChannelEntity();
        $channel->setMailHeaderFooter($mailHeaderFooter);

        $result = $builder->build([
            'contentPlain' => 'plain',
            'contentHtml' => '<p>html</p>',
        ], $channel);

        static::assertSame('head-plain-foot', $result['contentPlain']);
        static::assertSame('<header>head</header><p>html</p><footer>foot</footer>', $result['contentHtml']);
    }

    public function testBuildFallsBackToEmptyStringsForMissingTranslations(): void
    {
        $builder = new MailTemplateContentBuilder();

        $mailHeaderFooter = new MailHeaderFooterEntity();
        $mailHeaderFooter->setTranslated([]);

        $channel = new ChannelEntity();
        $channel->setMailHeaderFooter($mailHeaderFooter);

        $content = [
            'contentPlain' => 'plain',
            'contentHtml' => '<p>html</p>',
        ];

        static::assertSame($content, $builder->build($content, $channel));
    }
}
