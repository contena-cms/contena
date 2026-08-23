<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\CustomField\CustomFieldDefinition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class CustomFieldSearchableSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ParameterBagInterface $parameterBag
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'onCustomFieldWritten',
        ];
    }

    public function onCustomFieldWritten(EntityWrittenContainerEvent $containerEvent): void
    {
        if ($this->parameterBag->has('elasticsearch.enabled') && $this->parameterBag->get('elasticsearch.enabled')) {
            return;
        }

        $customFieldIds = [];
        foreach ($containerEvent->getResults(CustomFieldDefinition::ENTITY_NAME)->withPayloadProperties('includeInSearch') as $writeResult) {
            if ($writeResult->getProperty('includeInSearch') !== false) {
                continue;
            }

            $customFieldIds[] = $writeResult->getPrimaryKey();
        }

        if ($customFieldIds === []) {
            return;
        }

        $this->handleBlogSearchConfig($customFieldIds);
    }

    /**
     * @param array<string> $customFieldIds
     */
    private function handleBlogSearchConfig(array $customFieldIds): void
    {
        $this->connection->executeStatement(
            'DELETE FROM blog_search_config_field
            WHERE custom_field_id IN (:customFieldIds)',
            ['customFieldIds' => Uuid::fromHexToBytesList($customFieldIds)],
            ['customFieldIds' => ArrayParameterType::BINARY]
        );
    }
}
