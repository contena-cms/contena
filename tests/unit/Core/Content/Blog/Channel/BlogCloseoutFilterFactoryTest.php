<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\BlogCloseoutFilter;
use Contena\Core\Content\Blog\Channel\BlogCloseoutFilterFactory;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
#[CoversClass(BlogCloseoutFilterFactory::class)]
class BlogCloseoutFilterFactoryTest extends TestCase
{
    public function testCreatesBlogCloseoutFilter(): void
    {
        $context = static::createStub(ChannelContext::class);
        $filter = (new BlogCloseoutFilterFactory())->create($context);

        static::assertEquals(new BlogCloseoutFilter(), $filter);
    }

    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        static::expectException(DecorationPatternException::class);
        (new BlogCloseoutFilterFactory())->getDecorated();
    }
}
