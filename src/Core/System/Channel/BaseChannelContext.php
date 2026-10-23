<?php declare(strict_types=1);

namespace Contena\Core\System\Channel;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\System\Channel\Context\LanguageInfo;
use Contena\Core\System\Currency\CurrencyEntity;
use Contena\Core\System\Tax\TaxCollection;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;

/**
 * Contains basic user-independent information of the current sales channel.
 *
 * @internal Use ChannelContext for extensions
 *
 * @codeCoverageIgnore
 */
class BaseChannelContext
{

    public function getCurrentUserGroup(): UserGroupEntity
    {
        return $this->currentUserGroup;
    }

    public function getCurrencyId(): string
    {
        return $this->currency->getId();
    }

    public function getCurrency(): CurrencyEntity
    {
        return $this->currency;
    }

    public function getChannelId(): string
    {
        return $this->channel->getId();
    }

    public function getChannel(): ChannelEntity
    {
        return $this->channel;
    }

    public function getTaxRules(): TaxCollection
    {
        return $this->taxRules;
    }




    public function getContext(): Context
    {
        return $this->context;
    }

    public function getTaxState(): string
    {
        return $this->context->getTaxState();
    }

    public function getTotalRounding(): CashRoundingConfig
    {
        return $this->totalRounding;
    }

    public function getItemRounding(): CashRoundingConfig
    {
        return $this->itemRounding;
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
