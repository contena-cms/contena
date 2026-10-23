<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Cart\BlogGateway;
use Contena\Core\Content\Blog\Events\BlogGatewayCriteriaEvent;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\Test\Generator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(BlogGateway::class)]
class BlogGatewayTest extends TestCase
{
    public function testSendCriteriaEvent(): void
    {
        $ids = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];

        $context = Generator::generateChannelContext();

        $repository = static::createStub(ChannelRepository::class);
        $emptySearchResult = new EntitySearchResult(
            'blog',
            0,
            new BlogCollection(),
            null,
            new Criteria(),
            $context->getContext()
        );
        $repository->method('search')->willReturn($emptySearchResult);

        $validator = static::callback(static fn ($subject) => $subject instanceof BlogGatewayCriteriaEvent);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch')->with($validator);

        $gateway = new BlogGateway(
            $repository,
            $eventDispatcher
        );

        $gateway->get($ids, $context);
    }
}
