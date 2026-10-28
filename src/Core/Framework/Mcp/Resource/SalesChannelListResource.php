<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\Json;
use Contena\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpResource(
    uri: 'contena://sales-channels',
    name: 'contena-sales-channels',
    description: 'All sales channels with their IDs, names, types, and domains.'
)]
class SalesChannelListResource
{
    /**
     * @internal
     *
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $criteria = new Criteria();
        $criteria->addAssociation('domains');
        $criteria->addAssociation('type');

        $result = $this->salesChannelRepository->search($criteria, Context::createDefaultContext());

        $channels = [];
        foreach ($result->getEntities() as $channel) {
            $domains = [];
            foreach ($channel->getDomains() ?? [] as $domain) {
                $domains[] = [
                    'url' => $domain->getUrl(),
                    'languageId' => $domain->getLanguageId(),
                ];
            }

            $channels[] = [
                'id' => $channel->getId(),
                'name' => $channel->getName(),
                'type' => $channel->getType()?->getName(),
                'active' => $channel->getActive(),
                'domains' => $domains,
            ];
        }

        return [
            'uri' => 'contena://sales-channels',
            'mimeType' => 'application/json',
            'text' => Json::encode($channels),
        ];
    }
}
