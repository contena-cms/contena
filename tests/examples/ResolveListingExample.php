<?php declare(strict_types=1);

namespace Contena\Tests\Examples;

use GuzzleHttp\ClientInterface;
use Contena\Core\Content\Blog\Extension\ResolveListingExtension;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class ResolveListingExample implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<BlogCollection> $repository
     */
    public function __construct(
        // you can inject your own services
        private ClientInterface $client,
        private EntityRepository $repository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'listing-loader.resolve.pre' => 'replace',
        ];
    }

    public function replace(ResolveListingExtension $event): void
    {
        $criteria = $event->criteria;

        // building a json aware array for the API call
        $context = [
            'channelId' => $event->context->getChannelId(),
            'currencyId' => $event->context->getCurrency(),
            'languageId' => $event->context->getLanguageId(),
        ];

        // do an api call against your own server or another storage, or whatever you want
        $ids = $this->client->request('GET', 'https://your-api.com/listing-ids', [
            'query' => [
                'criteria' => json_encode($criteria),
                'context' => json_encode($context),
            ],
        ]);

        $data = json_decode($ids->getBody()->getContents(), true);

        $criteria = new Criteria($data['ids']);

        $event->result = $this->repository->search($criteria, $event->context->getContext());

        // stop the event propagation, so the core function will not be executed
        $event->stopPropagation();
    }
}
