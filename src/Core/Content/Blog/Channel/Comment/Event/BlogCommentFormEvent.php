<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Comment\Event;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\BlogAware;
use Contena\Core\Framework\Event\ChannelAware;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\FormDataObjectType;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\MemberAware;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Contracts\EventDispatcher\Event;

final class BlogCommentFormEvent extends Event implements ChannelAware, MailAware, BlogAware, MemberAware, ScalarValuesAware, FlowEventAware
{
    final public const string EVENT_NAME = 'blog_comment.created';

    /**
     * @var array<int|string, mixed>
     */
    private readonly array $commentFormData;

    public function __construct(
        private readonly Context $context,
        private readonly string $channelId,
        private readonly MailRecipientStruct $recipients,
        DataBag $commentFormData,
        private readonly string $blogId,
        private readonly string $memberId,
        private readonly BlogEntity $blog,
    ) {
        $this->commentFormData = $commentFormData->all();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection()
            ->add(FlowMailVariables::COMMENT_FORM_DATA, new FormDataObjectType())
            ->add(BlogAware::BLOG, new EntityType(BlogDefinition::class));
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [FlowMailVariables::COMMENT_FORM_DATA => $this->commentFormData];
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
    public function getCommentFormData(): array
    {
        return $this->commentFormData;
    }

    public function getBlogId(): string
    {
        return $this->blogId;
    }

    public function getBlog(): BlogEntity
    {
        return $this->blog;
    }

    public function getMemberId(): string
    {
        return $this->memberId;
    }
}
