<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Comment;

use Contena\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\Comment\Event\BlogCommentFormEvent;
use Contena\Core\Content\Blog\Channel\Detail\AbstractBlogDetailRoute;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRoute;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class BlogCommentSaveRoute extends AbstractBlogCommentSaveRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<BlogCommentCollection> $repository
     */
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly DataValidator $validator,
        private readonly SystemConfigService $config,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractBlogDetailRoute $blogDetailRoute,
    ) {
    }

    public function getDecorated(): AbstractBlogCommentSaveRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/blog/{blogId}/comment',
        name: 'channel-api.blog-comment.save',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST],
    )]
    public function save(string $blogId, RequestDataBag $data, ChannelContext $context): NoContentResponse
    {
        $channelId = $context->getChannelId();
        if (!$this->config->getBool('core.listing.showComments', $channelId)) {
            throw BlogException::commentsNotActive();
        }

        $member = $context->getMember();
        \assert($member !== null);

        $data->set('name', $member->getName());
        $data->set('email', $member->getEmail());
        $data->set('memberId', $member->getId());
        $data->set('blogId', $blogId);
        $this->validate($data);

        $request = new Request();
        $request->attributes->set(BlogDetailRoute::SKIP_BREADCRUMB, true);
        $blog = $this->blogDetailRoute->load($blogId, $request, $context, new Criteria())->getBlog();

        $parentId = $this->parentId($data);
        if ($parentId !== null) {
            $this->validateParent($parentId, $blogId, $channelId, $context->getContext());
        }

        $comment = [
            'blogId' => $blogId,
            'memberId' => $member->getId(),
            'channelId' => $channelId,
            'languageId' => $context->getLanguageId(),
            'parentId' => $parentId,
            'externalUser' => $member->getName(),
            'externalEmail' => $member->getEmail(),
            'content' => $data->get('content'),
            'status' => false,
        ];

        $this->repository->create([$comment], $context->getContext());

        $this->eventDispatcher->dispatch(
            new BlogCommentFormEvent(
                $context->getContext(),
                $channelId,
                new MailRecipientStruct([$member->getEmail() => $member->getName()]),
                $data,
                $blogId,
                $member->getId(),
                $blog,
            ),
            BlogCommentFormEvent::EVENT_NAME,
        );

        return new NoContentResponse();
    }

    private function validate(DataBag $data): void
    {
        $definition = new DataValidationDefinition('blog.comment.create');
        $definition->add('content', new NotBlank(), new Length(max: 5000));
        $definition->add('parentId', new Uuid());

        $this->validator->validate($data->all(), $definition);
    }

    private function parentId(DataBag $data): ?string
    {
        $parentId = $data->get('parentId');

        return \is_string($parentId) && $parentId !== '' ? $parentId : null;
    }

    private function validateParent(string $parentId, string $blogId, string $channelId, Context $context): void
    {
        $criteria = new Criteria([$parentId]);
        $criteria->addFilter(
            new EqualsFilter('blogId', $blogId),
            new EqualsFilter('channelId', $channelId),
            new EqualsFilter('parentId', null),
        );

        if ($this->repository->searchIds($criteria, $context)->firstId() === null) {
            throw BlogException::commentParentInvalid($parentId);
        }
    }
}
