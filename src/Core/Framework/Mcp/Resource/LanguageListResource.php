<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Util\Json;
use Contena\Core\System\Language\LanguageCollection;

#[McpResource(
    uri: 'contena://languages',
    name: 'contena-languages',
    description: 'All configured languages with locale codes.'
)]
class LanguageListResource
{
    /**
     * @internal
     *
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        private readonly EntityRepository $languageRepository,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $criteria = new Criteria();
        $criteria->addAssociation('locale');

        $result = $this->languageRepository->search($criteria, Context::createDefaultContext());

        $languages = [];
        foreach ($result->getEntities() as $language) {
            $languages[] = [
                'id' => $language->getId(),
                'name' => $language->getName(),
                'localeCode' => $language->getLocale()?->getCode(),
            ];
        }

        return [
            'uri' => 'contena://languages',
            'mimeType' => 'application/json',
            'text' => Json::encode($languages),
        ];
    }
}
