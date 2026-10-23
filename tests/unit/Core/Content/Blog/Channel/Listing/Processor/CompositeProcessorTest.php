<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Listing\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\Listing\Processor\AbstractListingProcessor;
use Contena\Core\Content\Blog\Channel\Listing\Processor\CompositeListingProcessor;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CompositeListingProcessor::class)]
class CompositeProcessorTest extends TestCase
{
    public function testPrepare(): void
    {
        $request = new Request();
        $criteria = new Criteria();
        $context = static::createStub(ChannelContext::class);

        $processor = new CompositeListingProcessor([
            $dummy = new DummyListingProcessor(),
        ]);

        $processor->prepare($request, $criteria, $context);

        static::assertTrue($dummy->called);
    }

    public function testProcess(): void
    {
        $request = new Request();
        $result = static::createStub(BlogListingResult::class);
        $context = static::createStub(ChannelContext::class);

        $processor = new CompositeListingProcessor([
            $dummy = new DummyListingProcessor(),
        ]);

        $processor->process($request, $result, $context);

        static::assertTrue($dummy->called);
    }
}

/**
 * @internal
 */
class DummyListingProcessor extends AbstractListingProcessor
{
    public bool $called = false;

    public function getDecorated(): AbstractListingProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function prepare(Request $request, Criteria $criteria, ChannelContext $context): void
    {
        $this->called = true;
    }

    public function process(Request $request, BlogListingResult $result, ChannelContext $context): void
    {
        $this->called = true;
    }
}
