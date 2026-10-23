<?php

declare(strict_types=1);

use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Test\TestDefaults;

return [
    [
        new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('visibilities.channelId', TestDefaults::CHANNEL),
            ]
        ),
        ['s-1', 's-2', 's-3'],
    ],
    [
        new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('visibilities.visibility', BlogVisibilityDefinition::VISIBILITY_ALL),
            ]
        ),
        ['s-1', 's-4'],
    ],
    [
        new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('visibilities.channelId', TestDefaults::CHANNEL),
                new EqualsFilter('visibilities.visibility', BlogVisibilityDefinition::VISIBILITY_LINK),
            ]
        ),
        ['s-2'],
    ],
    [
        new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('visibilities.channelId', TestDefaults::CHANNEL),
                new EqualsFilter('visibilities.visibility', BlogVisibilityDefinition::VISIBILITY_SEARCH),
            ]
        ),
        ['s-3'],
    ],
    [
        new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
                new MultiFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('visibilities.channelId', TestDefaults::CHANNEL),
                        new EqualsFilter('visibilities.visibility', BlogVisibilityDefinition::VISIBILITY_ALL),
                    ]
                ),
                new MultiFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('visibilities.channelId', Defaults::CHANNEL_TYPE_FRONTEND),
                        new EqualsFilter('visibilities.visibility', BlogVisibilityDefinition::VISIBILITY_LINK),
                    ]
                ),
            ]
        ),
        ['s-1', 's-3'],
    ],
    [
        new MultiFilter(
            MultiFilter::CONNECTION_XOR,
            [
                new MultiFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('visibilities.channelId', TestDefaults::CHANNEL),
                        new EqualsFilter('visibilities.visibility', BlogVisibilityDefinition::VISIBILITY_SEARCH),
                    ]
                ),
                new MultiFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('visibilities.channelId', Defaults::CHANNEL_TYPE_FRONTEND),
                        new EqualsFilter('visibilities.visibility', BlogVisibilityDefinition::VISIBILITY_SEARCH),
                    ]
                ),
            ]
        ),
        ['s-2', 's-3'],
    ],
];
