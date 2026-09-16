<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Struct\Struct;

class ChannelContextServiceParameters extends Struct
{
    public function __construct(
        protected string $channelId,
        protected string $token,
        protected ?string $languageId = null,
        // used as fallback if no currency is set in the existing context
        protected ?string $currencyId = null,
        protected ?string $domainId = null,
        protected ?Context $originalContext = null,
        protected ?string $memberId = null,
        protected ?string $imitatingUserId = null,
        protected ?string $countryId = null,
        protected ?string $overwriteCurrencyId = null,
    ) {
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getLanguageId(): ?string
    {
        return $this->languageId;
    }

    public function getCurrencyId(): ?string
    {
        return $this->currencyId;
    }

    public function getOverwriteCurrencyId(): ?string
    {
        return $this->overwriteCurrencyId;
    }

    public function getDomainId(): ?string
    {
        return $this->domainId;
    }

    public function getOriginalContext(): ?Context
    {
        return $this->originalContext;
    }

    public function getMemberId(): ?string
    {
        return $this->memberId;
    }

    public function getImitatingUserId(): ?string
    {
        return $this->imitatingUserId;
    }

    public function getCountryId(): ?string
    {
        return $this->countryId;
    }
}
