<?php declare(strict_types=1);

namespace Contena\Core\Content\Shared\MailFlow\DataProvider;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal
 *
 * @extends AbstractProvider<NewsletterRecipientEntity, NewsletterRecipientCollection>
 */
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
