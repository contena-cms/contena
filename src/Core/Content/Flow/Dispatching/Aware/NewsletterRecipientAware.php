<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Aware;

use Contena\Core\Framework\Event\IsFlowEventAware;

#[IsFlowEventAware]
interface NewsletterRecipientAware
{
    public const NEWSLETTER_RECIPIENT_ID = 'newsletterRecipientId';

    public const NEWSLETTER_RECIPIENT = 'newsletterRecipient';

    public function getNewsletterRecipientId(): string;
}
