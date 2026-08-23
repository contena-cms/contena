<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @phpstan-type SearchConfig array{and_logic: string, excluded_terms: array<string>, min_search_length: int, field: string, tokenize: int, ranking: float, use_exact_subfield: int}
 */
class SearchConfigLoader
{
    private const NOT_SUPPORTED_FIELDS = [
        'categories.customFields',
    ];

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<SearchConfig>
     */
    public function load(Context $context): array
    {
        foreach ($context->getLanguageIdChain() as $languageId) {
            foreach ($this->tenantScopes($context) as $tenantId) {
                $parameters = [
                    'languageId' => Uuid::fromHexToBytes($languageId),
                    'excludedFields' => self::NOT_SUPPORTED_FIELDS,
                ];

                $tenantFilter = 'blog_search_config.tenant_id IS NULL AND blog_search_config_field.tenant_id IS NULL';
                if ($tenantId !== null) {
                    $tenantFilter = 'blog_search_config.tenant_id = :tenantId AND blog_search_config_field.tenant_id = :tenantId';
                    $parameters['tenantId'] = Uuid::fromHexToBytes($tenantId);
                }

                $config = $this->connection->fetchAllAssociative(
                    'SELECT
blog_search_config.and_logic,
LOWER(blog_search_config.excluded_terms) as `excluded_terms`,
blog_search_config.`min_search_length`,
blog_search_config_field.field,
blog_search_config_field.tokenize,
blog_search_config_field.ranking,
blog_search_config_field.use_exact_subfield

FROM blog_search_config
INNER JOIN blog_search_config_field ON(blog_search_config_field.blog_search_config_id = blog_search_config.id)
WHERE blog_search_config.language_id = :languageId
    AND blog_search_config_field.searchable = 1
    AND blog_search_config_field.field NOT IN(:excludedFields)
    AND ' . $tenantFilter,
                    $parameters,
                    ['excludedFields' => ArrayParameterType::STRING]
                );

                if ($config !== []) {
                    return array_map(static function (array $item): array {
                        return [
                            'and_logic' => $item['and_logic'],
                            'excluded_terms' => json_decode($item['excluded_terms'], true),
                            'min_search_length' => (int) $item['min_search_length'],
                            'field' => $item['field'],
                            'tokenize' => (int) $item['tokenize'],
                            'ranking' => (float) $item['ranking'],
                            'use_exact_subfield' => (int) $item['use_exact_subfield'],
                        ];
                    }, $config);
                }
            }
        }

        throw DataAbstractionLayerException::configNotFound();
    }

    /**
     * @return list<string|null>
     */
    private function tenantScopes(Context $context): array
    {
        if ($context->getTenantId() === null) {
            return [null];
        }

        return [$context->getTenantId(), null];
    }
}
