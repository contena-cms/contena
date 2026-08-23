<?php declare(strict_types=1);

namespace Contena\Core\System\Channel;

use Contena\Core\Framework\Context;
use Contena\Core\System\Channel\Context\LanguageInfo;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;

/**
 * Contains basic member-independent information of the current channel.
 *
 * @internal Use ChannelContext for extensions
 *
 * @codeCoverageIgnore
 */
class BaseChannelContext
{
    public function __construct(
        protected Context $context,
        protected ChannelEntity $channel,
        protected MemberGroupEntity $currentMemberGroup,
        protected CountryEntity $country,
        private readonly LanguageInfo $languageInfo,
    ) {
    }

    public function getCurrentMemberGroup(): MemberGroupEntity
    {
        return $this->currentMemberGroup;
    }

    public function getChannelId(): string
    {
        return $this->channel->getId();
    }

    public function getChannel(): ChannelEntity
    {
        return $this->channel;
    }

    public function getCountry(): CountryEntity
    {
        return $this->country;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getLanguageInfo(): LanguageInfo
    {
        return $this->languageInfo;
    }

    public function getApiAlias(): string
    {
        return 'base_channel_context';
    }
}
