<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Comment;

use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Channel\Comment\BlogCommentSaveRoute;
use Contena\Core\Content\Blog\Channel\Detail\AbstractBlogDetailRoute;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRouteResponse;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(BlogCommentSaveRoute::class)]
class BlogCommentSaveRouteTest extends TestCase
{
    public function testSaveCreatesPendingReplyForLoggedInMember(): void
    {
        $member = new MemberEntity();
        $member->setId('f0a6b6f2bd9f4c3f8da44c58cdb0fc01');
        $member->setName('Member');
        $member->setEmail('member@example.com');
        $context = Generator::generateChannelContext(member: $member);
        $blogId = '9a5d9343c1aa4ec2b544dd77e7f398e8';
        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);

        $detailRoute = $this->createMock(AbstractBlogDetailRoute::class);
        $detailRoute->expects($this->once())->method('load')->willReturn(new BlogDetailRouteResponse($blog));
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('create')->with(
            [[
                'blogId' => $blogId,
                'memberId' => $member->getId(),
                'channelId' => $context->getChannelId(),
                'languageId' => $context->getLanguageId(),
                'parentId' => null,
                'externalUser' => 'Member',
                'externalEmail' => 'member@example.com',
                'content' => 'A useful comment',
                'status' => false,
            ]],
            $context->getContext(),
        );

        $route = new BlogCommentSaveRoute(
            $repository,
            static::createStub(DataValidator::class),
            new StaticSystemConfigService([
                $context->getChannelId() => ['core.listing.showComments' => true],
            ]),
            static::createStub(EventDispatcherInterface::class),
            $detailRoute,
        );

        $route->save($blogId, new RequestDataBag(['content' => 'A useful comment']), $context);
    }
}
