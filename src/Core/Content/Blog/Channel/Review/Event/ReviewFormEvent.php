<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Review\Event;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Contena\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Contena\Core\Framework\Event\BlogAware;
use Contena\Core\Framework\Event\ChannelAware;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\FormDataObjectType;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Contracts\EventDispatcher\Event;

final class ReviewFormEvent extends Event implements ChannelAware, MailAware, BlogAware, UserAware, ScalarValuesAware, FlowEventAware
{
    public const EVENT_NAME = 'review_form.send';

    /**
     * @var array<int|string, mixed>
     */
    private readonly array $reviewFormData;

    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'blog', newType: BlogEntity::class, description: 'The parameter loses its null default and becomes required.')]
    public function __construct(
        private readonly Context $context,
        private readonly string $channelId,
        private readonly MailRecipientStruct $recipients,
        DataBag $reviewFormData,
        private readonly string $blogId,
        private readonly string $userId,
        private readonly ?BlogEntity $blog = null,
    ) {
        $this->reviewFormData = $reviewFormData->all();

        if ($blog === null) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Not passing $blog to ' . static::class . ' is deprecated and will be required in v6.8.0.');
        }
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(FlowMailVariables::REVIEW_FORM_DATA, new FormDataObjectType())
            ->add(BlogAware::BLOG, new EntityType(BlogDefinition::class));
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [FlowMailVariables::REVIEW_FORM_DATA => $this->reviewFormData];
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return $this->recipients;
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getReviewFormData(): array
    {
        return $this->reviewFormData;
    }

    public function getBlogId(): string
    {
        return $this->blogId;
    }

    // @phpstan-ignore-next-line contena.bcChangeAttribute (The constructor still permits a missing blog, so the return type cannot be narrowed yet.)
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: BlogEntity::class, description: 'The return value becomes non-nullable once constructing the event without a blog is removed.')]
    public function getBlog(): ?BlogEntity
    {
        return $this->blog;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }
}
