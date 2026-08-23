<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\fixtures;

/**
 * @internal
 */
class QueryParameterAllowList
{
    /**
     * @return array{
     *     groups: array<string, list<string>>,
     *     allowedList: array<string, list<string>>
     * }
     */
    public static function getQueryParameterAllowList(): array
    {
        $criteria = [
            'page',
            'limit',
            'term',
            'filter[]',
            'ids[]',
            'query',
            'associations',
            'post-filter[]',
            'sort[]',
            'aggregations[]',
            'fields[]',
            'grouping[]',
            'total-count-mode',
            'includes',
            'excludes',
        ];

        return [
            'groups' => [
                'criteria' => $criteria,
                'blog-listing' => array_merge(
                    $criteria,
                    [
                        'order',
                        'p',
                        'reduce-aggregations',
                    ]
                ),
                'blog-listing-flags' => [
                    'no-aggregations',
                    'only-aggregations',
                ],
            ],
            'allowedList' => [
                '/channel-api/blog-listing/{categoryId}' => ['@blog-listing', '@blog-listing-flags'],
                '/channel-api/search' => ['@blog-listing', '@blog-listing-flags'],
                '/channel-api/search-suggest' => ['@blog-listing', '@blog-listing-flags'],
                '/channel-api/category' => ['@criteria'],
                '/channel-api/category/{navigationId}' => ['@criteria'],
                '/channel-api/region/{countryId}' => ['@criteria'],
                '/channel-api/country' => ['@criteria'],
                '/channel-api/language' => ['@criteria'],
                '/channel-api/landing-page/{landingPageId}' => ['@criteria'],
                '/channel-api/media' => ['ids[]'],
                '/channel-api/navigation/{activeId}/{rootId}' => ['@criteria', 'depth', 'buildTree'],
                '/channel-api/blog' => ['@criteria'],
                '/channel-api/blog/{blogId}' => ['@criteria'],
                '/channel-api/seo-url' => ['@criteria'],
            ],
        ];
    }
}
