<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Category\Channel\fixtures;

use PHPUnit\Framework\Attributes\Group;
use Contena\Tests\Integration\Core\Content\Category\Channel\CategoryRouteTest;

/**
 * @internal
 *
 * @phpstan-import-type CmsInheritanceDataProviderIterator from CategoryRouteTest
 */
#[Group('store-api')]
final class CategoryRouteInheritanceFixtures
{
    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function noOverridesDataProvider(): iterable
    {
        // EN templates
        yield 'EN Frontend, EN templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [],
                'hasSlotOverride' => [],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Frontend, EN templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [],
                'hasSlotOverride' => [],
            ],
            'expected' => 'en Template',
        ];
        yield 'AT Frontend, EN templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [],
                'hasSlotOverride' => [],
            ],
            'expected' => 'en Template',
        ];

        // EN/DE templates
        yield 'EN Frontend, EN/DE templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Frontend, EN/DE templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'de Template',
        ];
        yield 'AT Frontend, EN/DE templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'de Template',
        ];

        // EN/AT templates
        yield 'EN Frontend, EN/AT templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Frontend, EN/AT templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'en Template',
        ];
        yield 'AT Frontend, EN/AT templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'at Template',
        ];

        // All templates
        yield 'EN Frontend, EN/DE/AT templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Frontend, EN/DE/AT templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'de Template',
        ];
        yield 'AT Frontend, EN/DE/AT templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'at Template',
        ];
    }

    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function allOverridesDataProvider(): iterable
    {
        yield 'EN Frontend, EN templates, All overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'en', 'de', 'at',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'DE Frontend, All templates, All overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'en', 'de', 'at',
                ],
            ],
            'expected' => 'de Override',
        ];
        yield 'AT Frontend, All templates, All overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'en', 'de', 'at',
                ],
            ],
            'expected' => 'at Override',
        ];
    }

    /**
     * Selection of override test cases, with multiple provided overrides.
     * Templates are obsolete and "randomly" chosen, since the other cases have shown, that overrides take priority over templates.
     *
     * @return CmsInheritanceDataProviderIterator
     */
    public static function mixedOverridesDataProvider(): iterable
    {
        // EN/DE overrides
        yield 'EN Frontend, EN templates, EN/DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'en', 'de',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'DE Frontend, EN templates, EN/DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'en', 'de',
                ],
            ],
            'expected' => 'de Override',
        ];
        yield 'AT Frontend, EN templates, EN/DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'en', 'de',
                ],
            ],
            'expected' => 'de Override',
        ];

        // EN/AT overrides
        yield 'EN Frontend, DE templates, EN/AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'en', 'at',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'DE Frontend, DE templates, EN/AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'en', 'at',
                ],
            ],
            'expected' => 'de Template',
        ];
        yield 'AT Frontend, DE templates, EN/AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'en', 'at',
                ],
            ],
            'expected' => 'at Override',
        ];

        // DE/AT overrides
        yield 'EN Frontend, AT templates, DE/AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [
                    'de', 'at',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Frontend, AT templates, DE/AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [
                    'de', 'at',
                ],
            ],
            'expected' => 'de Override',
        ];
        yield 'AT Frontend, AT templates, DE/AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [
                    'de', 'at',
                ],
            ],
            'expected' => 'at Override',
        ];
    }

    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function enOverridesDataProvider(): iterable
    {
        yield 'EN Frontend, EN templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'DE Frontend, EN/DE templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'de Template',
        ];
        yield 'AT Frontend, EN/DE templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'de Template',
        ];
        yield 'AT Frontend, EN/AT templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'at Template',
        ];
        yield 'AT Frontend, EN/DE/AT templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'at Template',
        ];
    }

    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function deOverridesDataProvider(): iterable
    {
        // EN templates
        yield 'EN Frontend, EN templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Frontend, EN templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'de Override',
        ];
        yield 'AT Frontend, EN templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'de Override',
        ];

        // All templates
        yield 'EN Frontend, EN/DE/AT templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Frontend, EN/DE/AT templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'de Override',
        ];
        yield 'AT Frontend, EN/DE/AT templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'de Override',
        ];
    }

    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function atOverridesDataProvider(): iterable
    {
        // EN templates
        yield 'EN Frontend, EN templates, AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'at',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Frontend, EN templates, AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'at',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'AT Frontend, EN templates, AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'at',
                ],
            ],
            'expected' => 'at Override',
        ];

        // All templates
        yield 'EN Frontend, EN/DE/AT templates, AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'at',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Frontend, EN/DE/AT templates, AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'at',
                ],
            ],
            'expected' => 'de Template',
        ];
        yield 'AT Frontend, EN/DE/AT templates, AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'at',
                ],
            ],
            'expected' => 'at Override',
        ];
    }

    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function duplicatesInLanguageChainDataProviderNoOverrides(): iterable
    {
        yield 'EN Frontend, EN templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [],
                'hasSlotOverride' => [],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Frontend, EN/DE templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'de Template',
        ];
    }

    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function duplicatesInLanguageChainDataProviderEnOverrides(): iterable
    {
        yield 'EN Frontend, EN/DE templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'DE Frontend, EN/DE templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'en Override',
        ];
    }

    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function duplicatesInLanguageChainDataProviderDeOverrides(): iterable
    {
        yield 'EN Frontend, EN/DE templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Frontend, EN/DE templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'de Override',
        ];
    }

    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function duplicatesInLanguageChainDataProviderAllOverrides(): iterable
    {
        yield 'EN Frontend, EN/DE templates, EN/DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'en', 'de',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'DE Frontend, EN/DE templates, EN/DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'en', 'de',
                ],
            ],
            'expected' => 'de Override',
        ];
    }
}
