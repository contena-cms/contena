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
                        'manufacturer',
                        'min-price',
                        'max-price',
                        'rating',
                        'shipping-free',
                        'properties',
                        'manufacturer-filter',
                        'price-filter',
                        'rating-filter',
                        'shipping-free-filter',
                        'property-filter',
                        'property-whitelist',
                        'reduce-aggregations',
                    ]
                ),
                'blog-listing-flags' => [
                    'no-aggregations',
                    'only-aggregations',
                ],
            ],
            'allowedList' => [
                '/store-api/blog-listing/{categoryId}' => ['@blog-listing', '@blog-listing-flags'],
                '/store-api/search' => ['@blog-listing', '@blog-listing-flags'],
                '/store-api/search-suggest' => ['@blog-listing', '@blog-listing-flags'],
                '/store-api/cms/{id}' => ['@blog-listing'],
                '/store-api/category' => ['@criteria'],
                '/store-api/category/{navigationId}' => ['@blog-listing', 'slots'],
                '/store-api/country-state/{countryId}' => ['@criteria'],
                '/store-api/country' => ['@criteria'],
                '/store-api/currency' => ['@criteria'],
                '/store-api/language' => ['@criteria'],
                '/store-api/landing-page/{landingPageId}' => ['@criteria', '@blog-listing'],
                '/store-api/media' => ['ids[]'],
                '/store-api/navigation/{activeId}/{rootId}' => ['@criteria', 'depth', 'buildTree'],
                '/store-api/payment-method' => ['@criteria'],
                '/store-api/blog' => ['@criteria'],
                '/store-api/blog/{blogId}' => ['skipConfigurator', 'skipCmsPage', '@criteria'],
                '/store-api/blog/{blogId}/find-variant' => ['options[]'],
                '/store-api/blog/{blogId}/reviews' => ['@criteria'],
                '/store-api/shipping-cost/blog/{blogId}' => ['@criteria'],
                '/store-api/blog/purchase-limit' => ['ids[]'],
                '/store-api/salutation' => ['@criteria'],
                '/store-api/seo-url' => ['@criteria'],
                '/store-api/shipping-method' => ['@criteria', 'onlyAvailable'],
                '/store-api/checkout/cart/line-item' => ['ids'],
                '/store-api/context/gateway' => ['data'],
            ],
        ];
    }
}
