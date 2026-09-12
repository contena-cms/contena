<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\Uuid\Uuid;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

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
    AND blog_search_config.data_scope_id = :dataScopeId
    AND blog_search_config_field.data_scope_id = :dataScopeId',
                [
                    'languageId' => Uuid::fromHexToBytes($languageId),
                    'dataScopeId' => Uuid::fromHexToBytes($context->getDataScopeId()),
                    'excludedFields' => self::NOT_SUPPORTED_FIELDS,
                ],
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

        throw DataAbstractionLayerException::configNotFound();
    }
}
