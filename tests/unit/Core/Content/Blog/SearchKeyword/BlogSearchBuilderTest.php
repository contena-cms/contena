<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\SearchKeyword;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchBuilder;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchTermInterpreterInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogSearchBuilder::class)]
class BlogSearchBuilderTest extends TestCase
{
    public function testFallbackToCriteriaTermWhenSearchKeywordIndexingIsDisabled(): void
    {
        $termInterpreter = $this->createMock(BlogSearchTermInterpreterInterface::class);
        $logger = static::createStub(LoggerInterface::class);
        $searchBuilder = new BlogSearchBuilder(
            $termInterpreter,
            $logger,
            20,
            false
        );

        $mockChannelContext = static::createStub(ChannelContext::class);
        $mockChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $criteria = new Criteria();
        $request = new Request();
        $request->query->set('search', 'ring saphir');

        $termInterpreter->expects($this->never())->method('interpret');

        $searchBuilder->build($request, $criteria, $mockChannelContext);

        static::assertSame('ring saphir', $criteria->getTerm());
    }

    public function testSearchTermMaxLengthReached(): void
    {
        $termInterpreter = $this->createMock(BlogSearchTermInterpreterInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $searchBuilder = new BlogSearchBuilder(
            $termInterpreter,
            $logger,
            20
        );

        $mockChannelContext = static::createStub(ChannelContext::class);
        $mockChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $criteria = new Criteria();
        $request = new Request();

        $request->query->set('search', 'This search term\'s length is over 20 characters');

        $logger
            ->expects($this->once())
            ->method('notice')
            ->with(
                'The search term "{term}" was trimmed because it exceeded the maximum length of {maxLength} characters.',
                [
                    'term' => 'This search term\'s length is over 20 characters',
                    'maxLength' => 20,
                ]
            );
        $termInterpreter->expects($this->once())
            ->method('interpret')
            ->with('This search term\'s l', static::isInstanceOf(Context::class));
        $searchBuilder->build($request, $criteria, $mockChannelContext);
    }
}
