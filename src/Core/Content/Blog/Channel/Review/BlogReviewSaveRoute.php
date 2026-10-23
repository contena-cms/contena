<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Review;

use Contena\Core\System\User\Service\EmailIdnConverter;
use Contena\Core\Content\Blog\Extension\BlogReviewSaveRouteExtension;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\Review\Event\ReviewFormEvent;
use Contena\Core\Content\Shared\MailFlow\DataProvider\BlogProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityNotExists;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class BlogReviewSaveRoute extends AbstractBlogReviewSaveRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<BlogReviewCollection> $repository
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

    public function getDecorated(): AbstractBlogReviewSaveRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/blog/{blogId}/review',
        name: 'store-api.blog-review.save',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function save(string $blogId, RequestDataBag $data, ChannelContext $context): NoContentResponse
    {
        return $this->extensions->publish(
            name: BlogReviewSaveRouteExtension::NAME,
            extension: new BlogReviewSaveRouteExtension($blogId, $data, $context),
            function: $this->_save(...),
        );
    }

    private function _save(string $blogId, RequestDataBag $data, ChannelContext $context): NoContentResponse
    {
        $channelId = $context->getChannelId();
        if (!$this->config->getBool('core.listing.showReview', $channelId)) {
            throw BlogException::reviewNotActive();
        }

        $user = $context->getUser();
        \assert($user !== null);

        $userId = $user->getId();

        EmailIdnConverter::encodeDataBag($data);
        if (!$data->has('name')) {
            $data->set('name', $user->getFirstName());
        }

        if (!$data->has('lastName')) {
            $data->set('lastName', $user->getLastName());
        }

        if (!$data->has('email')) {
            $data->set('email', $user->getEmail());
        }

        $data->set('userId', $userId);
        $data->set('blogId', $blogId);
        $this->validate($data, $context->getContext());

        $review = [
            'blogId' => $blogId,
            'userId' => $userId,
            'channelId' => $channelId,
            'languageId' => $context->getLanguageId(),
            'externalUser' => $data->get('name'),
            'externalEmail' => $data->get('email'),
            'title' => $data->get('title'),
            'content' => $data->get('content'),
            'points' => $data->get('points'),
            'status' => false,
        ];

        if ($data->get('id')) {
            $review['id'] = $data->get('id');
        }

        $blog = $this->blogProvider->getData($blogId, $context->getContext());
        if ($blog === null) {
            throw BlogException::blogNotFound($blogId);
        }

        $this->repository->upsert([$review], $context->getContext());

        $mail = $review['externalEmail'];
        $mail = \is_string($mail) ? $mail : '';
        $event = new ReviewFormEvent(
            $context->getContext(),
            $channelId,
            new MailRecipientStruct([$mail => $review['externalUser'] . ' ' . $data->get('lastName')]),
            $data,
            $blogId,
            $userId,
            $blog
        );

        $this->eventDispatcher->dispatch(
            $event,
            ReviewFormEvent::EVENT_NAME
        );

        return new NoContentResponse();
    }

    private function validate(DataBag $data, Context $context): void
    {
        $definition = new DataValidationDefinition('blog.create_rating');

        $definition->add('name', new NotBlank());
        $definition->add('title', new NotBlank(), new Length(min: 5));
        $definition->add('content', new NotBlank(), new Length(min: 40));

        if (Feature::isActive('v6.8.0.0')) {
            $definition->add('points', new NotBlank(), new GreaterThanOrEqual(1), new LessThanOrEqual(5));
        } else {
            $definition->add('points', new GreaterThanOrEqual(1), new LessThanOrEqual(5));
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userId', $data->get('userId')));

        if ($data->get('id')) {
            $criteria->addFilter(new EqualsFilter('id', $data->get('id')));

            $definition->add('id', new EntityExists(
                entity: 'blog_review',
                context: $context,
                criteria: $criteria,
            ));
        } else {
            $criteria->addFilter(new EqualsFilter('blogId', $data->get('blogId')));

            $definition->add('userId', new EntityNotExists(
                entity: 'blog_review',
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
