<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Comment;

use Contena\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Contena\Core\Content\Blog\Aggregate\BlogComment\BlogCommentDefinition;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\Comment\Event\CommentFormEvent;
use Contena\Core\Content\Blog\Extension\BlogCommentSaveRouteExtension;
use Contena\Core\Content\Shared\MailFlow\DataProvider\BlogProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityNotExists;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\Service\EmailIdnConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
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
        private readonly BlogProvider $blogProvider,
        private readonly ExtensionDispatcher $extensions,
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
        methods: [Request::METHOD_POST]
    )]
    public function save(string $blogId, RequestDataBag $data, ChannelContext $context): NoContentResponse
    {
        return $this->extensions->publish(
            name: BlogCommentSaveRouteExtension::NAME,
            extension: new BlogCommentSaveRouteExtension($blogId, $data, $context),
            function: $this->_save(...),
        );
    }

    private function _save(string $blogId, RequestDataBag $data, ChannelContext $context): NoContentResponse
    {
        $channelId = $context->getChannelId();
        if (!$this->config->getBool('core.listing.showComment', $channelId)) {
            throw BlogException::commentNotActive();
        }

        $user = $context->getUser();
        \assert($user !== null);

        $userId = $user->getId();

        EmailIdnConverter::encodeDataBag($data);
        if (!$data->has('name')) {
            $data->set('name', $user->getName());
        }

        if (!$data->has('email')) {
            $data->set('email', $user->getEmail());
        }

        $data->set('userId', $userId);
        $data->set('blogId', $blogId);
        $this->validate($data, $context->getContext());

        $comment = [
            'blogId' => $blogId,
            'userId' => $userId,
            'channelId' => $channelId,
            'languageId' => $context->getLanguageId(),
            'externalUser' => $data->get('name'),
            'externalEmail' => $data->get('email'),
            'title' => $data->get('title'),
            'content' => $data->get('content'),
            'status' => false,
        ];

        if ($data->get('id')) {
            $comment['id'] = $data->get('id');
        }

        $blog = $this->blogProvider->getData($blogId, $context->getContext());
        if ($blog === null) {
            throw BlogException::blogNotFound($blogId);
        }

        $this->repository->upsert([$comment], $context->getContext());

        $mail = $comment['externalEmail'];
        $mail = \is_string($mail) ? $mail : '';
        $event = new CommentFormEvent(
            $context->getContext(),
            $channelId,
            new MailRecipientStruct([$mail => (string) $comment['externalUser']]),
            $data,
            $blogId,
            $userId,
            $blog
        );

        $this->eventDispatcher->dispatch(
            $event,
            CommentFormEvent::EVENT_NAME
        );

        return new NoContentResponse();
    }

    private function validate(DataBag $data, Context $context): void
    {
        $definition = new DataValidationDefinition('blog.create_comment');

        $definition->add('name', new NotBlank());
        $definition->add('title', new NotBlank(), new Length(min: 5));
        $definition->add('content', new NotBlank(), new Length(min: 40));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userId', $data->get('userId')));

        if ($data->get('id')) {
            $criteria->addFilter(new EqualsFilter('id', $data->get('id')));

            $definition->add('id', new EntityExists(
                entity: BlogCommentDefinition::ENTITY_NAME,
                context: $context,
                criteria: $criteria,
            ));
        } else {
            $criteria->addFilter(new EqualsFilter('blogId', $data->get('blogId')));

            $definition->add('userId', new EntityNotExists(
                entity: BlogCommentDefinition::ENTITY_NAME,
                context: $context,
                primaryProperty: 'userId',
                criteria: $criteria,
            ));
        }

        $this->validator->validate($data->all(), $definition);

        $violations = $this->validator->getViolations($data->all(), $definition);

        if (!$violations->count()) {
            return;
        }

        throw new ConstraintViolationException($violations, $data->all());
    }
}
