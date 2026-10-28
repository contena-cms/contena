<?php declare(strict_types=1);

namespace Contena\Core\Content\Shared\MailFlow\DataProvider;

use Contena\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends AbstractProvider<NewsletterRecipientEntity, NewsletterRecipientCollection>
 */
#[Package('after-sales')]
class NewsletterRecipientProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return NewsletterRecipientDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        return new Criteria([$entityId]);
    }
}
