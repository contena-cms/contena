<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;
use Contena\Core\Framework\Util\Json;
use Contena\Core\System\Channel\ChannelCollection;

#[McpResource(
    uri: 'contena://channels',
    name: 'contena-channels',
    description: 'All channels with their IDs, names, types, and domains.'
)]
class ChannelListResource
{
    /**
     * @internal
     *
     * @param EntityRepository<ChannelCollection> $channelRepository
     */
    public function __construct(
        private readonly EntityRepository $channelRepository,
        private readonly McpContextProvider $contextProvider,
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

        $result = $this->channelRepository->search($criteria, $this->contextProvider->getContext());

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
            'uri' => 'contena://channels',
            'mimeType' => 'application/json',
            'text' => Json::encode($channels),
        ];
    }
}
