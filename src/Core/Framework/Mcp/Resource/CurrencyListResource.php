<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\Json;
use Contena\Core\System\Currency\CurrencyCollection;

/**
 * @experimental stableVersion:v6.8.0
 */
#[McpResource(
    uri: 'contena://currencies',
    name: 'contena-currencies',
    description: 'All configured currencies with ISO codes, symbols, and conversion factors.'
)]
class CurrencyListResource
{
    /**
     * @internal
     *
     * @param EntityRepository<CurrencyCollection> $currencyRepository
     */
    public function __construct(
        private readonly EntityRepository $currencyRepository,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $result = $this->currencyRepository->search(new Criteria(), Context::createDefaultContext());

        $currencies = [];
        foreach ($result->getEntities() as $currency) {
            $currencies[] = [
                'id' => $currency->getId(),
                'isoCode' => $currency->getIsoCode(),
                'symbol' => $currency->getSymbol(),
                'factor' => $currency->getFactor(),
                'name' => $currency->getName(),
            ];
        }

        return [
            'uri' => 'contena://currencies',
            'mimeType' => 'application/json',
            'text' => Json::encode($currencies),
        ];
    }
}
