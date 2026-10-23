<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\Content\Flow\Dispatching\Aware\NewsletterRecipientAware;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Shared\MailFlow\DataProvider\NewsletterRecipientProvider;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\FlowEventAware;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class NewsletterRecipientStorer extends FlowStorer
{
    /**
     * @internal
     *
     * @param EntityRepository<NewsletterRecipientCollection> $newsletterRecipientRepository
     */
    public function __construct(
        private readonly EntityRepository $newsletterRecipientRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly NewsletterRecipientProvider $newsletterRecipientProvider,
    ) {
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof NewsletterRecipientAware || isset($stored[NewsletterRecipientAware::NEWSLETTER_RECIPIENT_ID])) {
            return $stored;
        }

        $stored[NewsletterRecipientAware::NEWSLETTER_RECIPIENT_ID] = $event->getNewsletterRecipientId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(NewsletterRecipientAware::NEWSLETTER_RECIPIENT_ID)) {
            return;
        }

        $storable->lazy(
            NewsletterRecipientAware::NEWSLETTER_RECIPIENT,
            $this->lazyLoad(...)
        );
    }

}
