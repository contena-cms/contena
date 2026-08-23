<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Event;

use Contena\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Contena\Core\Content\Flow\Dispatching\Aware\MemberRecoveryAware;
use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ChannelContextAware;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\EventData\ScalarValueType;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\MemberAware;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryDefinition;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryEntity;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Member\MemberEntity;
use Symfony\Contracts\EventDispatcher\Event;

class MemberAccountRecoverRequestEvent extends Event implements ChannelContextAware, ContenaChannelEvent, MemberAware, MailAware, MemberRecoveryAware, ScalarValuesAware, FlowEventAware
{
    public const EVENT_NAME = 'member.recovery.request';

    private readonly string $channelName;

    private ?MailRecipientStruct $mailRecipientStruct = null;

    public function __construct(
        private readonly ChannelContext $channelContext,
        private readonly MemberRecoveryEntity $memberRecovery,
        private readonly string $resetUrl,
    ) {
        $this->channelName = $channelContext->getChannel()->getTranslation('name');
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            FlowMailVariables::RESET_URL => $this->resetUrl,
            FlowMailVariables::CHANNEL_NAME => $this->channelName,
        ];
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getMemberRecovery(): MemberRecoveryEntity
    {
        return $this->memberRecovery;
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection()
            ->add(MemberRecoveryAware::MEMBER_RECOVERY, new EntityType(MemberRecoveryDefinition::class))
            ->add(MemberAware::MEMBER, new EntityType(MemberDefinition::class))
            ->add(FlowMailVariables::RESET_URL, new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add(FlowMailVariables::CHANNEL_NAME, new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if (!$this->mailRecipientStruct) {
            $member = $this->memberRecovery->getMember();
            \assert($member !== null);

            $this->mailRecipientStruct = new MailRecipientStruct([
                $member->getEmail() => $member->getName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public function getChannelId(): string
    {
        return $this->channelContext->getChannelId();
    }

    public function getResetUrl(): string
    {
        return $this->resetUrl;
    }

    public function getChannelName(): string
    {
        return $this->channelName;
    }

    public function getMember(): ?MemberEntity
    {
        return $this->memberRecovery->getMember();
    }

    public function getMemberId(): string
    {
        return $this->getMemberRecovery()->getMemberId();
    }

    public function getMemberRecoveryId(): string
    {
        return $this->memberRecovery->getId();
    }
}
