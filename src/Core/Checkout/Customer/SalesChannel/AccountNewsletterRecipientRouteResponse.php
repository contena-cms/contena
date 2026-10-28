<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<AccountNewsletterRecipientResult>
 */
#[Package('checkout')]
class AccountNewsletterRecipientRouteResponse extends StoreApiResponse
{
    /**
     * @param EntitySearchResult<NewsletterRecipientCollection> $newsletterRecipients
     */
    public function __construct(EntitySearchResult $newsletterRecipients)
    {
        $firstNewsletterRecipient = $newsletterRecipients->getEntities()->first();
        if ($firstNewsletterRecipient) {
            $accNlRecipientResult = new AccountNewsletterRecipientResult($firstNewsletterRecipient->getStatus());
            parent::__construct($accNlRecipientResult);

            return;
        }
        $accNlRecipientResult = new AccountNewsletterRecipientResult();
        parent::__construct($accNlRecipientResult);
    }

    public function getAccountNewsletterRecipient(): AccountNewsletterRecipientResult
    {
        return $this->object;
    }
}
