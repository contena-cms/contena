<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\SystemCheck\Util;

use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextService;

/**
 * @internal
 *
 * Creates the sales channel context an anonymous visitor gets on the given domain, so the readiness checks
 * can pick the page to probe with the same criteria processing the probe request is answered with.
 */
class ChannelDomainContextFactory
{

    /**
     * @description Language, currency and domain are taken from the domain whose URL is probed, not from the
     * sales channel defaults, because all of them feed the criteria processing. The rule IDs are detected the
     * same way ChannelContextService::get() detects them for a visitor without a cart: by calculating a
     * new cart. It is created through the CartFactory like the request's cart, so extensions seeding a new cart
     * via the CartCreatedEvent affect the matching rules here as well. The cart only lives in memory:
     * persistence is skipped explicitly, because a cart processor adding a line item or an error would
     * otherwise store a cart under a token nobody uses again. Unlike ChannelContextService::get() this
     * does not touch the current request, its session or the cart service, which matters when the check runs
     * inside an Admin API request.
     */
    public function create(ChannelDomain $domain): ChannelContext
    {
        $token = Uuid::randomHex();

        $context = $this->channelContextFactory->create($token, $domain->channelId, [
            ChannelContextService::DOMAIN_ID => $domain->id,
            ChannelContextService::LANGUAGE_ID => $domain->languageId,
            ChannelContextService::CURRENCY_ID => $domain->currencyId,
        ]);

        return $context;
    }
}
