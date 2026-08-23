<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\fixtures;

use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeEntity;

/**
 * @internal
 *
 * @phpstan-type ThemeFixture iterable<array{
 *     ids: array<string, mixed>,
 *     themeCollection: ThemeCollection,
 *     expected?: array<string, mixed>,
 *     expectedNotTranslated?: array<string, mixed>|null,
 *     expectedStructured?: array<string, mixed>,
 *     expectedStructuredNotTranslated?: array<string, mixed>
 * }>
 */
class ThemeFixtures
{
    /**
     * @return array<string, mixed>
     */
    public static function getThemeJsonConfig(): array
    {
        return [
            'name' => 'test',
            'fields' => [
                'contena-color-brand-primary' => [
                    'type' => 'color',
                    'value' => '#008490',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 100,
                ],

                'contena-color-brand-secondary' => [
                    'type' => 'color',
                    'value' => '#526e7f',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 200,
                ],

                'contena-border-color' => [
                    'type' => 'color',
                    'value' => '#bcc1c7',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 300,
                ],

                'contena-background-color' => [
                    'type' => 'color',
                    'value' => '#fff',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 400,
                ],

                'contena-color-success' => [
                    'type' => 'color',
                    'value' => '#3cc261',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 100,
                ],

                'contena-color-info' => [
                    'type' => 'color',
                    'value' => '#26b6cf',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 200,
                ],

                'contena-color-warning' => [
                    'type' => 'color',
                    'value' => '#ffbd5d',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 300,
                ],

                'contena-color-danger' => [
                    'type' => 'color',
                    'value' => '#e52427',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 400,
                ],

                'contena-font-family-base' => [
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 100,
                ],

                'contena-text-color' => [
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 200,
                ],

                'contena-font-family-headline' => [
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 300,
                ],

                'contena-headline-color' => [
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 400,
                ],

                'contena-color-content-accent' => [
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'content',
                    'order' => 100,
                ],

                'contena-color-primary-action' => [
                    'type' => 'color',
                    'value' => '#008490',
                    'editable' => true,
                    'block' => 'content',
                    'order' => 200,
                ],

                'contena-color-primary-action-text' => [
                    'type' => 'color',
                    'value' => '#fff',
                    'editable' => true,
                    'block' => 'content',
                    'order' => 300,
                ],

                'contena-logo-desktop' => [
                    'type' => 'media',
                    'value' => 'app/frontend/dist/assets/logo/default-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 100,
                    'fullWidth' => true,
                ],

                'contena-logo-tablet' => [
                    'type' => 'media',
                    'value' => 'app/frontend/dist/assets/logo/default-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 200,
                    'fullWidth' => true,
                ],

                'contena-logo-mobile' => [
                    'type' => 'media',
                    'value' => 'app/frontend/dist/assets/logo/default-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 300,
                    'fullWidth' => true,
                ],

                'contena-logo-share' => [
                    'type' => 'media',
                    'value' => '',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 400,
                ],

                'contena-logo-favicon' => [
                    'type' => 'media',
                    'value' => 'app/frontend/dist/assets/logo/favicon.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 500,
                ],
            ],
        ];
    }

    /**
     * @return ThemeFixture
     */
    public static function getThemeCollectionForThemeConfiguration(): iterable
    {
        $themeId = Uuid::randomHex();
        $parentThemeId = Uuid::randomHex();
        $baseThemeId = Uuid::randomHex();
        $databaseThemeId = Uuid::randomHex();

        // Test Case 1: Theme with parent theme inheritance and custom field extensions
        // Tests: Theme inherits from parent theme and has custom field extensions.
        yield [
            'ids' => [
                'themeId' => $themeId,
                'parentThemeId' => $parentThemeId,
                'baseThemeId' => $baseThemeId,
            ],
            'themeCollection' => new ThemeCollection(
                [
                    new ThemeEntity()->assign(
                        [
                            'id' => $themeId,
                            '_uniqueIdentifier' => $themeId,
                            'technicalName' => 'Test',
                            'parentThemeId' => $parentThemeId,
                            'baseConfig' => [
                                'configInheritance' => [
                                    '@ParentTheme',
                                ],
                                'config' => self::getThemeJsonConfig(),
                                'fields' => [
                                    'extend-parent-custom-config' => [
                                        'type' => 'int',
                                        'value' => '20',
                                        'editable' => true,
                                    ],
                                ],
                            ],
                            'configValues' => [
                                'test' => ['value' => ['no_test']],
                            ],
                        ]
                    ),
                    new ThemeEntity()->assign(
                        [
                            'id' => $baseThemeId,
                            'technicalName' => FrontendPluginRegistry::BASE_THEME_NAME,
                            '_uniqueIdentifier' => $baseThemeId,
                        ]
                    ),
                    new ThemeEntity()->assign(
                        [
                            'id' => $parentThemeId,
                            'technicalName' => 'ParentTheme',
                            'parentThemeId' => $baseThemeId,
                            '_uniqueIdentifier' => $parentThemeId,
                            'baseConfig' => [
                                'configInheritance' => [
                                    '@Frontend',
                                ],
                                'fields' => [
                                    'parent-custom-config' => [
                                        'type' => 'int',
                                        'value' => '20',
                                        'editable' => true,
                                    ],
                                ],
                            ],
                        ],
                    ),
                ]
            ),
            'expected' => [
                'fields' => self::getExtractedFields7(),
                'configInheritance' => self::getExtractedConfigInheritanceWithParent(),
                'config' => self::getExtractedConfig1(),
                'currentFields' => self::getExtractedCurrentFields5(),
                'baseThemeFields' => self::getExtractedBaseThemeFields5(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => self::getExtractedTabs10(),
                'themeTechnicalName' => 'Test',
                'configInheritance' => self::getExtractedConfigInheritanceWithParent(),
            ],
        ];

        // Test Case 2: Theme with parent theme inheritance and basic configuration
        // Tests: Theme inherits from parent theme with basic config.
        yield [
            'ids' => [
                'themeId' => $themeId,
                'parentThemeId' => $parentThemeId,
                'baseThemeId' => $baseThemeId,
            ],
            'themeCollection' => new ThemeCollection(
                [
                    new ThemeEntity()->assign(
                        [
                            'id' => $themeId,
                            '_uniqueIdentifier' => $themeId,
                            'technicalName' => 'Test',
                            'parentThemeId' => $parentThemeId,
                            'baseConfig' => [
                                'configInheritance' => [
                                    '@ParentTheme',
                                ],
                                'config' => self::getThemeJsonConfig(),
                            ],
                            'configValues' => [
                                'test' => ['value' => ['no_test']],
                            ],
                        ]
                    ),
                    new ThemeEntity()->assign(
                        [
                            'id' => $baseThemeId,
                            'technicalName' => FrontendPluginRegistry::BASE_THEME_NAME,
                            '_uniqueIdentifier' => $baseThemeId,
                        ]
                    ),
                    new ThemeEntity()->assign(
                        [
                            'id' => $parentThemeId,
                            'technicalName' => 'ParentTheme',
                            'parentThemeId' => $baseThemeId,
                            '_uniqueIdentifier' => $parentThemeId,
                        ]
                    ),
                ]
            ),
            'expected' => [
                'fields' => self::getExtractedFields1(),
                'configInheritance' => self::getExtractedConfigInheritanceWithParent(),
                'config' => self::getExtractedConfig1(),
                'currentFields' => self::getExtractedCurrentFields1(),
                'baseThemeFields' => self::getExtractedBaseThemeFields1(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => self::getExtractedTabs1(),
                'themeTechnicalName' => 'Test',
                'configInheritance' => self::getExtractedConfigInheritanceWithParent(),
            ],
        ];

        // Test Case 3: Theme with custom fields and help texts
        // Tests: Theme with custom fields defined in baseConfig and help texts.
        yield [
            'ids' => [
                'themeId' => $themeId,
                'parentThemeId' => $parentThemeId,
                'baseThemeId' => $baseThemeId,
            ],
            'themeCollection' => new ThemeCollection(
                [
                    new ThemeEntity()->assign(
                        [
                            'id' => $themeId,
                            '_uniqueIdentifier' => $themeId,
                            'technicalName' => 'Test',
                            'parentThemeId' => $parentThemeId,
                            'baseConfig' => [
                                'fields' => [
                                    'first' => [],
                                    'test' => [],
                                ],
                                'configInheritance' => [
                                    '@ParentTheme',
                                ],
                            ],
                            'configValues' => [
                                'test' => ['value' => ['no_test']],
                            ],
                        ]
                    ),
                    new ThemeEntity()->assign(
                        [
                            'id' => $baseThemeId,
                            'technicalName' => FrontendPluginRegistry::BASE_THEME_NAME,
                            '_uniqueIdentifier' => $baseThemeId,
                        ]
                    ),
                    new ThemeEntity()->assign(
                        [
                            'id' => $parentThemeId,
                            'technicalName' => 'ParentTheme',
                            'parentThemeId' => $baseThemeId,
                            '_uniqueIdentifier' => $parentThemeId,
                        ]
                    ),
                ]
            ),
            'expected' => [
                'fields' => self::getExtractedFields3(),
                'configInheritance' => self::getExtractedConfigInheritanceWithParent(),
                'currentFields' => self::getExtractedCurrentFields2(),
                'baseThemeFields' => self::getExtractedBaseThemeFields2(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => self::getExtractedTabs3(),
                'themeTechnicalName' => 'Test',
                'configInheritance' => self::getExtractedConfigInheritanceWithParent(),
            ],
        ];

        // Test Case 4: Theme with minimal configuration
        // Tests: Theme with only basic configuration and configValues, no baseConfig.
        yield [
            'ids' => [
                'themeId' => $themeId,
                'parentThemeId' => $parentThemeId,
                'baseThemeId' => $baseThemeId,
            ],
            'themeCollection' => new ThemeCollection(
                [
                    new ThemeEntity()->assign(
                        [
                            'id' => $themeId,
                            '_uniqueIdentifier' => $themeId,
                            'technicalName' => 'Test',
                            'parentThemeId' => $parentThemeId,
                            'configValues' => [
                                'test' => ['value' => ['no_test']],
                            ],
                        ]
                    ),
                    new ThemeEntity()->assign(
                        [
                            'id' => $baseThemeId,
                            'technicalName' => FrontendPluginRegistry::BASE_THEME_NAME,
                            '_uniqueIdentifier' => $baseThemeId,
                        ]
                    ),
                    new ThemeEntity()->assign(
                        [
                            'id' => $parentThemeId,
                            'parentThemeId' => $baseThemeId,
                            '_uniqueIdentifier' => $parentThemeId,
                        ]
                    ),
                ]
            ),
            'expected' => [
                'fields' => self::getExtractedFields2(),
                'currentFields' => self::getExtractedCurrentFields3(),
                'baseThemeFields' => self::getExtractedBaseThemeFields3(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
                'configInheritance' => self::getExtractedConfigInheritanceWithFrontend(),
            ],
            'expectedStructured' => [
                'tabs' => self::getExtractedTabs5(),
                'themeTechnicalName' => 'Test',
                'configInheritance' => self::getExtractedConfigInheritanceWithFrontend(),
            ],
        ];

        // Test Case 5: Theme with parent theme having false fields configuration
        // Tests: Parent theme with baseConfig.fields set to false.
        yield [
            'ids' => [
                'themeId' => $themeId,
                'parentThemeId' => $parentThemeId,
                'baseThemeId' => $baseThemeId,
            ],
            'themeCollection' => new ThemeCollection(
                [
                    new ThemeEntity()->assign(
                        [
                            'id' => $themeId,
                            '_uniqueIdentifier' => $themeId,
                            'technicalName' => 'Test',
                            'parentThemeId' => $parentThemeId,
                            'configValues' => [
                                'test' => ['value' => ['no_test']],
                            ],
                        ]
                    ),
                    new ThemeEntity()->assign(
                        [
                            'id' => $baseThemeId,
                            'technicalName' => FrontendPluginRegistry::BASE_THEME_NAME,
                            '_uniqueIdentifier' => $baseThemeId,
                        ]
                    ),
                    new ThemeEntity()->assign(
                        [
                            'id' => $parentThemeId,
                            'parentThemeId' => $baseThemeId,
                            '_uniqueIdentifier' => $parentThemeId,
                            'baseConfig' => [
                                'fields' => false,
                            ],
                        ]
                    ),
                ]
            ),
            'expected' => [
                'fields' => self::getExtractedFields5(),
                'currentFields' => self::getExtractedCurrentFields3(),
                'baseThemeFields' => self::getExtractedBaseThemeFields3(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
                'configInheritance' => self::getExtractedConfigInheritanceWithFrontend(),
            ],
            'expectedStructured' => [
                'tabs' => self::getExtractedTabs5(),
                'themeTechnicalName' => 'Test',
                'configInheritance' => self::getExtractedConfigInheritanceWithFrontend(),
            ],
        ];

        // Test Case 6: Theme with parent theme having empty fields configuration
        // Tests: Parent theme with baseConfig.fields set to empty array.
        yield [
            'ids' => [
                'themeId' => $themeId,
                'parentThemeId' => $parentThemeId,
                'baseThemeId' => $baseThemeId,
            ],
            'themeCollection' => new ThemeCollection(
                [
                    new ThemeEntity()->assign(
                        [
                            'id' => $themeId,
                            '_uniqueIdentifier' => $themeId,
                            'technicalName' => 'Test',
                            'parentThemeId' => $parentThemeId,
                            'configValues' => [
                                'test' => ['value' => ['no_test']],
                            ],
                        ]
                    ),
                    new ThemeEntity()->assign(
                        [
                            'id' => $baseThemeId,
                            'technicalName' => FrontendPluginRegistry::BASE_THEME_NAME,
                            '_uniqueIdentifier' => $baseThemeId,
                        ]
                    ),
                    new ThemeEntity()->assign(
                        [
                            'id' => $parentThemeId,
                            'parentThemeId' => $baseThemeId,
                            '_uniqueIdentifier' => $parentThemeId,
                            'baseConfig' => [
                                'fields' => [],
                            ],
                        ]
                    ),
                ]
            ),
            'expected' => [
                'fields' => self::getExtractedFields2(),
                'currentFields' => self::getExtractedCurrentFields3(),
                'baseThemeFields' => self::getExtractedBaseThemeFields3(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
                'configInheritance' => self::getExtractedConfigInheritanceWithFrontend(),
            ],
            'expectedStructured' => [
                'tabs' => self::getExtractedTabs5(),
                'themeTechnicalName' => 'Test',
                'configInheritance' => self::getExtractedConfigInheritanceWithFrontend(),
            ],
        ];

        // Test Case 7: Theme without parent theme
        // Tests: Theme directly inheriting from base theme without parent theme.
        yield [
            'ids' => [
                'themeId' => $themeId,
                'parentThemeId' => $parentThemeId,
                'baseThemeId' => $baseThemeId,
            ],
            'themeCollection' => new ThemeCollection(
                [
                    new ThemeEntity()->assign(
                        [
                            'id' => $themeId,
                            '_uniqueIdentifier' => $themeId,
                            'technicalName' => 'Test',
                            'configValues' => [
                                'test' => ['value' => ['no_test']],
                            ],
                        ]
                    ),
                    new ThemeEntity()->assign(
                        [
                            'id' => $baseThemeId,
                            'technicalName' => FrontendPluginRegistry::BASE_THEME_NAME,
                            '_uniqueIdentifier' => $baseThemeId,
                        ]
                    ),
                ]
            ),
            'expected' => [
                'fields' => self::getExtractedFields2(),
                'currentFields' => self::getExtractedCurrentFields3(),
                'baseThemeFields' => self::getExtractedBaseThemeFields3(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
                'configInheritance' => self::getExtractedConfigInheritanceWithFrontend(),
            ],
            'expectedStructured' => [
                'tabs' => self::getExtractedTabs5(),
                'themeTechnicalName' => 'Test',
                'configInheritance' => self::getExtractedConfigInheritanceWithFrontend(),
            ],
        ];

        // Test Case 8: Theme with configValues in base theme
        // Tests: Theme with empty configValues but base theme has configValues.
        yield [
            'ids' => [
                'themeId' => $themeId,
                'parentThemeId' => $parentThemeId,
                'baseThemeId' => $baseThemeId,
            ],
            'themeCollection' => new ThemeCollection(
                [
                    new ThemeEntity()->assign(
                        [
                            'id' => $themeId,
                            '_uniqueIdentifier' => $themeId,
                            'technicalName' => 'Test',
                            'configValues' => [],
                        ]
                    ),
                    new ThemeEntity()->assign(
                        [
                            'id' => $baseThemeId,
                            'technicalName' => FrontendPluginRegistry::BASE_THEME_NAME,
                            '_uniqueIdentifier' => $baseThemeId,
                            'configValues' => [
                                'test' => ['value' => ['no_test']],
                            ],
                        ]
                    ),
                ]
            ),
            'expected' => [
                'fields' => self::getExtractedFields5(),
                'currentFields' => self::getExtractedBaseThemeFields8(),
                'baseThemeFields' => self::getExtractedCurrentFields8(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
                'configInheritance' => self::getExtractedConfigInheritanceWithFrontend(),
            ],
            'expectedStructured' => [
                'tabs' => self::getExtractedTabs5(),
                'themeTechnicalName' => 'Test',
                'configInheritance' => self::getExtractedConfigInheritanceWithFrontend(),
            ],
        ];

        // Test Case 9: Theme with custom field overrides and select options
        // Tests: Theme with custom field overrides including select component with options.
        yield [
            'ids' => [
                'themeId' => $themeId,
                'parentThemeId' => $parentThemeId,
                'baseThemeId' => $baseThemeId,
            ],
            'themeCollection' => new ThemeCollection(
                [
                    new ThemeEntity()->assign(
                        [
                            'id' => $themeId,
                            'technicalName' => 'Theme',
                            '_uniqueIdentifier' => $themeId,
                            'baseConfig' => [
                                'fields' => [
                                    'contena-color-brand-primary' => [
                                        'value' => '#adbd00',
                                    ],
                                    'test-something-with-options' => [
                                        'type' => 'text',
                                        'editable' => true,
                                        'block' => 'media',
                                        'order' => 600,
                                        'value' => 'Hello',
                                        'fullWidth' => null,
                                        'custom' => [
                                            'componentName' => 'contena-single-select',
                                            'options' => [
                                                [
                                                    'value' => 'Hello',
                                                ],
                                                [
                                                    'value' => 'World',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ]
                    ),
                    new ThemeEntity()->assign(
                        [
                            'id' => $baseThemeId,
                            'technicalName' => FrontendPluginRegistry::BASE_THEME_NAME,
                            '_uniqueIdentifier' => $baseThemeId,
                            'baseConfig' => self::getThemeJsonConfig(),
                        ]
                    ),
                ]
            ),
            'expected' => [
                'fields' => self::getExtractedFields10(),
                'currentFields' => self::getExtractedCurrentFields6(),
                'baseThemeFields' => self::getExtractedBaseThemeFields6(),
                'name' => 'test',
                'themeTechnicalName' => 'Theme',
                'configInheritance' => self::getExtractedConfigInheritanceWithFrontend(),
            ],
            'expectedStructured' => [
                'tabs' => self::getExtractedTabsNameTheme(),
                'themeTechnicalName' => 'Theme',
                'configInheritance' => self::getExtractedConfigInheritanceWithFrontend(),
            ],
        ];

        // Test Case 10: Database child theme
        // Tests: Database child theme with parent theme inheritance and custom field extensions.
        yield [
            'ids' => [
                'themeId' => $databaseThemeId,
                'physicalThemeId' => $themeId,
                'parentThemeId' => $parentThemeId,
                'baseThemeId' => $baseThemeId,
            ],
            'themeCollection' => new ThemeCollection(
                [
                    new ThemeEntity()->assign(
                        [
                            'id' => $databaseThemeId,
                            '_uniqueIdentifier' => $databaseThemeId,
                            'technicalName' => null, // Database child themes don't have a technical name.
                            'parentThemeId' => $themeId,
                            'configValues' => [
                                'contena-color-brand-primary' => ['value' => '#db0f80'],
                            ],
                        ]
                    ),
                    new ThemeEntity()->assign(
                        [
                            'id' => $themeId,
                            '_uniqueIdentifier' => $themeId,
                            'technicalName' => 'Test',
                            'parentThemeId' => $parentThemeId,
                            'baseConfig' => [
                                'configInheritance' => [
                                    '@ParentTheme',
                                ],
                                'config' => self::getThemeJsonConfig(),
                                'fields' => [
                                    'extend-parent-custom-config' => [
                                        'type' => 'int',
                                        'value' => '20',
                                        'editable' => true,
                                    ],
                                ],
                            ],
                            'configValues' => [
                                'parent-custom-config' => ['value' => '40'],
                            ],
                        ]
                    ),
                    new ThemeEntity()->assign(
                        [
                            'id' => $parentThemeId,
                            'technicalName' => 'ParentTheme',
                            'parentThemeId' => $baseThemeId,
                            '_uniqueIdentifier' => $parentThemeId,
                            'baseConfig' => [
                                'configInheritance' => [
                                    '@Frontend',
                                ],
                                'fields' => [
                                    'parent-custom-config' => [
                                        'type' => 'int',
                                        'value' => '20',
                                        'editable' => true,
                                    ],
                                ],
                            ],
                        ],
                    ),
                    new ThemeEntity()->assign(
                        [
                            'id' => $baseThemeId,
                            'technicalName' => FrontendPluginRegistry::BASE_THEME_NAME,
                            '_uniqueIdentifier' => $baseThemeId,
                        ]
                    ),
                ]
            ),
            'expected' => [
                'fields' => self::getExtractedFields11(),
                'configInheritance' => self::getExtractedConfigInheritanceWithParent(),
                'config' => self::getExtractedConfig1(),
                'currentFields' => self::getExtractedCurrentFields9(),
                'baseThemeFields' => self::getExtractedBaseThemeFields9(),
                'name' => 'test',
                'themeTechnicalName' => 'Test',
            ],
            'expectedStructured' => [
                'tabs' => self::getExtractedTabs11(),
                'themeTechnicalName' => 'Test',
                'configInheritance' => self::getExtractedConfigInheritanceWithParent(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedFields1(): array
    {
        return [
            'contena-color-brand-primary' => [
                'extensions' => [
                ],
                'name' => 'contena-color-brand-primary',
                'type' => 'color',
                'value' => '#008490',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-brand-secondary' => [
                'extensions' => [
                ],
                'name' => 'contena-color-brand-secondary',
                'type' => 'color',
                'value' => '#526e7f',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-border-color' => [
                'extensions' => [
                ],
                'name' => 'contena-border-color',
                'type' => 'color',
                'value' => '#bcc1c7',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-background-color' => [
                'extensions' => [
                ],
                'name' => 'contena-background-color',
                'type' => 'color',
                'value' => '#fff',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-success' => [
                'extensions' => [
                ],
                'name' => 'contena-color-success',
                'type' => 'color',
                'value' => '#3cc261',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-info' => [
                'extensions' => [
                ],
                'name' => 'contena-color-info',
                'type' => 'color',
                'value' => '#26b6cf',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-warning' => [
                'extensions' => [
                ],
                'name' => 'contena-color-warning',
                'type' => 'color',
                'value' => '#ffbd5d',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-danger' => [
                'extensions' => [
                ],
                'name' => 'contena-color-danger',
                'type' => 'color',
                'value' => '#e52427',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-font-family-base' => [
                'extensions' => [
                ],
                'name' => 'contena-font-family-base',
                'type' => 'fontFamily',
                'value' => '\'Inter\', sans-serif',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-text-color' => [
                'extensions' => [
                ],
                'name' => 'contena-text-color',
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-font-family-headline' => [
                'extensions' => [
                ],
                'name' => 'contena-font-family-headline',
                'type' => 'fontFamily',
                'value' => '\'Inter\', sans-serif',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-headline-color' => [
                'extensions' => [
                ],
                'name' => 'contena-headline-color',
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-content-accent' => [
                'extensions' => [
                ],
                'name' => 'contena-color-content-accent',
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => true,
                'block' => 'content',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-primary-action' => [
                'extensions' => [
                ],
                'name' => 'contena-color-primary-action',
                'type' => 'color',
                'value' => '#008490',
                'editable' => true,
                'block' => 'content',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-primary-action-text' => [
                'extensions' => [
                ],
                'name' => 'contena-color-primary-action-text',
                'type' => 'color',
                'value' => '#fff',
                'editable' => true,
                'block' => 'content',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-logo-desktop' => [
                'extensions' => [
                ],
                'name' => 'contena-logo-desktop',
                'type' => 'media',
                'value' => 'app/frontend/dist/assets/logo/default-logo.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'contena-logo-tablet' => [
                'extensions' => [
                ],
                'name' => 'contena-logo-tablet',
                'type' => 'media',
                'value' => 'app/frontend/dist/assets/logo/default-logo.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'contena-logo-mobile' => [
                'extensions' => [
                ],
                'name' => 'contena-logo-mobile',
                'type' => 'media',
                'value' => 'app/frontend/dist/assets/logo/default-logo.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'contena-logo-share' => [
                'extensions' => [
                ],
                'name' => 'contena-logo-share',
                'type' => 'media',
                'value' => null,
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-logo-favicon' => [
                'extensions' => [
                ],
                'name' => 'contena-logo-favicon',
                'type' => 'media',
                'value' => 'app/frontend/dist/assets/logo/favicon.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 500,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'test' => [
                'extensions' => [
                ],
                'name' => 'test',
                'type' => null,
                'value' => [
                    0 => 'no_test',
                ],
                'editable' => null,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function getExtractedConfigInheritanceWithFrontend(): array
    {
        return [
            0 => '@Frontend',
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function getExtractedConfigInheritanceWithParent(): array
    {
        return [
            0 => '@ParentTheme',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedConfig1(): array
    {
        return [
            'name' => 'test',
            'fields' => [
                'contena-color-brand-primary' => [
                    'type' => 'color',
                    'value' => '#008490',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 100,
                ],
                'contena-color-brand-secondary' => [
                    'type' => 'color',
                    'value' => '#526e7f',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 200,
                ],
                'contena-border-color' => [
                    'type' => 'color',
                    'value' => '#bcc1c7',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 300,
                ],
                'contena-background-color' => [
                    'type' => 'color',
                    'value' => '#fff',
                    'editable' => true,
                    'block' => 'themeColors',
                    'order' => 400,
                ],
                'contena-color-success' => [
                    'type' => 'color',
                    'value' => '#3cc261',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 100,
                ],
                'contena-color-info' => [
                    'type' => 'color',
                    'value' => '#26b6cf',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 200,
                ],
                'contena-color-warning' => [
                    'type' => 'color',
                    'value' => '#ffbd5d',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 300,
                ],
                'contena-color-danger' => [
                    'type' => 'color',
                    'value' => '#e52427',
                    'editable' => true,
                    'block' => 'statusColors',
                    'order' => 400,
                ],
                'contena-font-family-base' => [
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 100,
                ],
                'contena-text-color' => [
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 200,
                ],
                'contena-font-family-headline' => [
                    'type' => 'fontFamily',
                    'value' => '\'Inter\', sans-serif',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 300,
                ],
                'contena-headline-color' => [
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'typography',
                    'order' => 400,
                ],
                'contena-color-content-accent' => [
                    'type' => 'color',
                    'value' => '#4a545b',
                    'editable' => true,
                    'block' => 'content',
                    'order' => 100,
                ],
                'contena-color-primary-action' => [
                    'type' => 'color',
                    'value' => '#008490',
                    'editable' => true,
                    'block' => 'content',
                    'order' => 200,
                ],
                'contena-color-primary-action-text' => [
                    'type' => 'color',
                    'value' => '#fff',
                    'editable' => true,
                    'block' => 'content',
                    'order' => 300,
                ],
                'contena-logo-desktop' => [
                    'type' => 'media',
                    'value' => 'app/frontend/dist/assets/logo/default-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 100,
                    'fullWidth' => true,
                ],
                'contena-logo-tablet' => [
                    'type' => 'media',
                    'value' => 'app/frontend/dist/assets/logo/default-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 200,
                    'fullWidth' => true,
                ],
                'contena-logo-mobile' => [
                    'type' => 'media',
                    'value' => 'app/frontend/dist/assets/logo/default-logo.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 300,
                    'fullWidth' => true,
                ],
                'contena-logo-share' => [
                    'type' => 'media',
                    'value' => null,
                    'editable' => true,
                    'block' => 'media',
                    'order' => 400,
                ],
                'contena-logo-favicon' => [
                    'type' => 'media',
                    'value' => 'app/frontend/dist/assets/logo/favicon.png',
                    'editable' => true,
                    'block' => 'media',
                    'order' => 500,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedCurrentFields1(): array
    {
        return [
            'contena-color-brand-primary' => [
                'isInherited' => null,
                'value' => '#008490',
            ],
            'contena-color-brand-secondary' => [
                'isInherited' => null,
                'value' => '#526e7f',
            ],
            'contena-border-color' => [
                'isInherited' => null,
                'value' => '#bcc1c7',
            ],
            'contena-background-color' => [
                'isInherited' => null,
                'value' => '#fff',
            ],
            'contena-color-success' => [
                'isInherited' => null,
                'value' => '#3cc261',
            ],
            'contena-color-info' => [
                'isInherited' => null,
                'value' => '#26b6cf',
            ],
            'contena-color-warning' => [
                'isInherited' => null,
                'value' => '#ffbd5d',
            ],
            'contena-color-danger' => [
                'isInherited' => null,
                'value' => '#e52427',
            ],
            'contena-font-family-base' => [
                'isInherited' => null,
                'value' => '\'Inter\', sans-serif',
            ],
            'contena-text-color' => [
                'isInherited' => null,
                'value' => '#4a545b',
            ],
            'contena-font-family-headline' => [
                'isInherited' => null,
                'value' => '\'Inter\', sans-serif',
            ],
            'contena-headline-color' => [
                'isInherited' => null,
                'value' => '#4a545b',
            ],
            'contena-color-content-accent' => [
                'isInherited' => null,
                'value' => '#4a545b',
            ],
            'contena-color-primary-action' => [
                'isInherited' => null,
                'value' => '#008490',
            ],
            'contena-color-primary-action-text' => [
                'isInherited' => null,
                'value' => '#fff',
            ],
            'contena-logo-desktop' => [
                'isInherited' => null,
                'value' => 'app/frontend/dist/assets/logo/default-logo.png',
            ],
            'contena-logo-tablet' => [
                'isInherited' => null,
                'value' => 'app/frontend/dist/assets/logo/default-logo.png',
            ],
            'contena-logo-mobile' => [
                'isInherited' => null,
                'value' => 'app/frontend/dist/assets/logo/default-logo.png',
            ],
            'contena-logo-share' => [
                'isInherited' => null,
                'value' => null,
            ],
            'contena-logo-favicon' => [
                'isInherited' => null,
                'value' => 'app/frontend/dist/assets/logo/favicon.png',
            ],
            'test' => [
                'isInherited' => null,
                'value' => [
                    0 => 'no_test',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedBaseThemeFields1(): array
    {
        return [
            'contena-color-brand-primary' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-color-brand-secondary' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-border-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-background-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-color-success' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-color-info' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-color-warning' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-color-danger' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-font-family-base' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-text-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-font-family-headline' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-headline-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-color-content-accent' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-color-primary-action' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-color-primary-action-text' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-logo-desktop' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-logo-tablet' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-logo-mobile' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-logo-share' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-logo-favicon' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'test' => [
                'isInherited' => 1,
                'value' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedFields3(): array
    {
        return [
            'contena-color-brand-primary' => [
                'extensions' => [
                ],
                'name' => 'contena-color-brand-primary',
                'type' => 'color',
                'value' => '#008490',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-brand-secondary' => [
                'extensions' => [
                ],
                'name' => 'contena-color-brand-secondary',
                'type' => 'color',
                'value' => '#526e7f',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-border-color' => [
                'extensions' => [
                ],
                'name' => 'contena-border-color',
                'type' => 'color',
                'value' => '#bcc1c7',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-background-color' => [
                'extensions' => [
                ],
                'name' => 'contena-background-color',
                'type' => 'color',
                'value' => '#fff',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-success' => [
                'extensions' => [
                ],
                'name' => 'contena-color-success',
                'type' => 'color',
                'value' => '#3cc261',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-info' => [
                'extensions' => [
                ],
                'name' => 'contena-color-info',
                'type' => 'color',
                'value' => '#26b6cf',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-warning' => [
                'extensions' => [
                ],
                'name' => 'contena-color-warning',
                'type' => 'color',
                'value' => '#ffbd5d',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-danger' => [
                'extensions' => [
                ],
                'name' => 'contena-color-danger',
                'type' => 'color',
                'value' => '#e52427',
                'editable' => true,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-font-family-base' => [
                'extensions' => [
                ],
                'name' => 'contena-font-family-base',
                'type' => 'fontFamily',
                'value' => '\'Inter\', sans-serif',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-text-color' => [
                'extensions' => [
                ],
                'name' => 'contena-text-color',
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-font-family-headline' => [
                'extensions' => [
                ],
                'name' => 'contena-font-family-headline',
                'type' => 'fontFamily',
                'value' => '\'Inter\', sans-serif',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-headline-color' => [
                'extensions' => [
                ],
                'name' => 'contena-headline-color',
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => true,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-content-accent' => [
                'extensions' => [
                ],
                'name' => 'contena-color-content-accent',
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => true,
                'block' => 'content',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-primary-action' => [
                'extensions' => [
                ],
                'name' => 'contena-color-primary-action',
                'type' => 'color',
                'value' => '#008490',
                'editable' => true,
                'block' => 'content',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-primary-action-text' => [
                'extensions' => [
                ],
                'name' => 'contena-color-primary-action-text',
                'type' => 'color',
                'value' => '#fff',
                'editable' => true,
                'block' => 'content',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-logo-desktop' => [
                'extensions' => [
                ],
                'name' => 'contena-logo-desktop',
                'type' => 'media',
                'value' => 'app/frontend/dist/assets/logo/default-logo.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'contena-logo-tablet' => [
                'extensions' => [
                ],
                'name' => 'contena-logo-tablet',
                'type' => 'media',
                'value' => 'app/frontend/dist/assets/logo/default-logo.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'contena-logo-mobile' => [
                'extensions' => [
                ],
                'name' => 'contena-logo-mobile',
                'type' => 'media',
                'value' => 'app/frontend/dist/assets/logo/default-logo.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'contena-logo-share' => [
                'extensions' => [
                ],
                'name' => 'contena-logo-share',
                'type' => 'media',
                'value' => null,
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-logo-favicon' => [
                'extensions' => [
                ],
                'name' => 'contena-logo-favicon',
                'type' => 'media',
                'value' => 'app/frontend/dist/assets/logo/favicon.png',
                'editable' => true,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 500,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'first' => [
                'extensions' => [
                ],
                'name' => 'first',
                'type' => null,
                'value' => null,
                'editable' => null,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'test' => [
                'extensions' => [
                ],
                'name' => 'test',
                'type' => null,
                'value' => [
                    0 => 'no_test',
                ],
                'editable' => null,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedCurrentFields2(): array
    {
        return [
            'contena-color-brand-primary' => [
                'isInherited' => null,
                'value' => '#008490',
            ],
            'contena-color-brand-secondary' => [
                'isInherited' => null,
                'value' => '#526e7f',
            ],
            'contena-border-color' => [
                'isInherited' => null,
                'value' => '#bcc1c7',
            ],
            'contena-background-color' => [
                'isInherited' => null,
                'value' => '#fff',
            ],
            'contena-color-success' => [
                'isInherited' => null,
                'value' => '#3cc261',
            ],
            'contena-color-info' => [
                'isInherited' => null,
                'value' => '#26b6cf',
            ],
            'contena-color-warning' => [
                'isInherited' => null,
                'value' => '#ffbd5d',
            ],
            'contena-color-danger' => [
                'isInherited' => null,
                'value' => '#e52427',
            ],
            'contena-font-family-base' => [
                'isInherited' => null,
                'value' => '\'Inter\', sans-serif',
            ],
            'contena-text-color' => [
                'isInherited' => null,
                'value' => '#4a545b',
            ],
            'contena-font-family-headline' => [
                'isInherited' => null,
                'value' => '\'Inter\', sans-serif',
            ],
            'contena-headline-color' => [
                'isInherited' => null,
                'value' => '#4a545b',
            ],
            'contena-color-content-accent' => [
                'isInherited' => null,
                'value' => '#4a545b',
            ],
            'contena-color-primary-action' => [
                'isInherited' => null,
                'value' => '#008490',
            ],
            'contena-color-primary-action-text' => [
                'isInherited' => null,
                'value' => '#fff',
            ],
            'contena-logo-desktop' => [
                'isInherited' => null,
                'value' => 'app/frontend/dist/assets/logo/default-logo.png',
            ],
            'contena-logo-tablet' => [
                'isInherited' => null,
                'value' => 'app/frontend/dist/assets/logo/default-logo.png',
            ],
            'contena-logo-mobile' => [
                'isInherited' => null,
                'value' => 'app/frontend/dist/assets/logo/default-logo.png',
            ],
            'contena-logo-share' => [
                'isInherited' => null,
                'value' => null,
            ],
            'contena-logo-favicon' => [
                'isInherited' => null,
                'value' => 'app/frontend/dist/assets/logo/favicon.png',
            ],
            'first' => [
                'isInherited' => null,
                'value' => null,
            ],
            'test' => [
                'isInherited' => null,
                'value' => [
                    0 => 'no_test',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedBaseThemeFields2(): array
    {
        return [
            'contena-color-brand-primary' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-color-brand-secondary' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-border-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-background-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-color-success' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-color-info' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-color-warning' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-color-danger' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-font-family-base' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-text-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-font-family-headline' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-headline-color' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-color-content-accent' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-color-primary-action' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-color-primary-action-text' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-logo-desktop' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-logo-tablet' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-logo-mobile' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-logo-share' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'contena-logo-favicon' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'first' => [
                'isInherited' => 1,
                'value' => null,
            ],
            'test' => [
                'isInherited' => 1,
                'value' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedFields5(): array
    {
        return [
            ...self::getExtractedFieldsSub1(),
            'test' => [
                'extensions' => [
                ],
                'name' => 'test',
                'type' => null,
                'value' => [
                    0 => 'no_test',
                ],
                'editable' => null,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedCurrentFields3(): array
    {
        return [
            ...self::getExtractedCurrentFields1(),
            'test' => [
                'isInherited' => null,
                'value' => [
                    0 => 'no_test',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedBaseThemeFields3(): array
    {
        return [
            ...self::getExtractedBaseThemeFields1(),
            'test' => [
                'isInherited' => 1,
                'value' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedTabs5(): array
    {
        return [
            'default' => [
                'labelSnippetKey' => 'default.label',
                'blocks' => [
                    'default' => [
                        'labelSnippetKey' => 'default.default.label',
                        'sections' => [
                            'default' => [
                                'labelSnippetKey' => 'default.default.default.label',
                                'fields' => [
                                    'test' => [
                                        'labelSnippetKey' => 'default.default.default.test.label',
                                        'helpTextSnippetKey' => 'default.default.default.test.helpText',
                                        'type' => null,
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'themeColors' => [
                        'labelSnippetKey' => 'default.themeColors.label',
                        'sections' => self::getExtractedSectionsThemeColors(),
                    ],
                    'statusColors' => [
                        'labelSnippetKey' => 'default.statusColors.label',
                        'sections' => self::getExtractedSectionsStatusColors(),
                    ],
                    'typography' => [
                        'labelSnippetKey' => 'default.typography.label',
                        'sections' => self::getExtractedSectionsTypography(),
                    ],
                    'content' => [
                        'labelSnippetKey' => 'default.content.label',
                        'sections' => self::getExtractedSectionsECommerce(),
                    ],
                    'media' => [
                        'labelSnippetKey' => 'default.media.label',
                        'sections' => self::getExtractedSectionsMedia(),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedFields7(): array
    {
        return [...self::getExtractedFields1(), ...[
            'parent-custom-config' => [
                'extensions' => [
                ],
                'name' => 'parent-custom-config',
                'type' => 'int',
                'value' => '20',
                'editable' => true,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'extend-parent-custom-config' => [
                'extensions' => [
                ],
                'name' => 'extend-parent-custom-config',
                'type' => 'int',
                'value' => '20',
                'editable' => true,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedFields10(): array
    {
        $fields = self::getExtractedFields9();

        foreach ($fields as $key => $field) {
            if ($field['editable'] === 1) {
                $fields[$key]['editable'] = true;
            }

            if ($field['fullWidth'] === 1) {
                $fields[$key]['fullWidth'] = true;
            }
        }

        $fields['test-something-with-options'] = [
            'name' => 'test-something-with-options',
            'extensions' => [],
            'type' => 'text',
            'value' => 'Hello',
            'editable' => true,
            'block' => 'media',
            'section' => null,
            'tab' => null,
            'order' => 600,
            'sectionOrder' => null,
            'blockOrder' => null,
            'tabOrder' => null,
            'custom' => [
                'componentName' => 'contena-single-select',
                'options' => [
                    [
                        'value' => 'Hello',
                    ],
                    [
                        'value' => 'World',
                    ],
                ],
            ],
            'scss' => null,
            'fullWidth' => null,
        ];

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedFields11(): array
    {
        $fields = self::getExtractedFields7();

        $fields['parent-custom-config']['value'] = '40';
        $fields['contena-color-brand-primary']['value'] = '#db0f80';

        unset($fields['test']);
        unset($fields['test-something-with-options']);

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedCurrentFields5(): array
    {
        return [...self::getExtractedCurrentFields1(), ...[
            'parent-custom-config' => [
                'value' => null,
                'isInherited' => true,
            ],
            'extend-parent-custom-config' => [
                'value' => '20',
                'isInherited' => false,
            ],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedCurrentFields6(): array
    {
        return [
            'contena-color-brand-primary' => [
                'isInherited' => false,
                'value' => '#adbd00',
            ],
            'contena-color-brand-secondary' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-border-color' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-background-color' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-color-success' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-color-info' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-color-warning' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-color-danger' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-font-family-base' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-text-color' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-font-family-headline' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-headline-color' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-color-content-accent' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-color-primary-action' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-color-primary-action-text' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-logo-desktop' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-logo-tablet' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-logo-mobile' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-logo-share' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-logo-favicon' => [
                'isInherited' => true,
                'value' => null,
            ],
            'test-something-with-options' => [
                'value' => 'Hello',
                'isInherited' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedCurrentFields8(): array
    {
        $currentFields = self::getExtractedCurrentFields6();

        $currentFields['contena-color-brand-primary'] = [
            'isInherited' => true,
            'value' => null,
        ];

        $currentFields['test'] = [
            'isInherited' => null,
            'value' => [
                0 => 'no_test',
            ],
        ];

        unset($currentFields['test-something-with-options']);

        return $currentFields;
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedCurrentFields9(): array
    {
        $currentFields = [
            'contena-color-brand-primary' => [
                'isInherited' => false,
                'value' => '#db0f80',
            ],
            'contena-color-brand-secondary' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-border-color' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-background-color' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-color-success' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-color-info' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-color-warning' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-color-danger' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-font-family-base' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-text-color' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-font-family-headline' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-headline-color' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-color-content-accent' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-color-primary-action' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-color-primary-action-text' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-logo-desktop' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-logo-tablet' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-logo-mobile' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-logo-share' => [
                'isInherited' => true,
                'value' => null,
            ],
            'contena-logo-favicon' => [
                'isInherited' => true,
                'value' => null,
            ],
            'parent-custom-config' => [
                'isInherited' => true,
                'value' => null,
            ],
            'extend-parent-custom-config' => [
                'isInherited' => true,
                'value' => null,
            ],
        ];

        return $currentFields;
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedBaseThemeFields5(): array
    {
        return [...self::getExtractedBaseThemeFields1(), ...[
            'parent-custom-config' => [
                'isInherited' => 0,
                'value' => 20,
            ],
            'extend-parent-custom-config' => [
                'isInherited' => 1,
                'value' => null,
            ],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedBaseThemeFields6(): array
    {
        return [
            'contena-color-brand-primary' => [
                'isInherited' => false,
                'value' => '#008490',
            ],
            'contena-color-brand-secondary' => [
                'isInherited' => false,
                'value' => '#526e7f',
            ],
            'contena-border-color' => [
                'isInherited' => false,
                'value' => '#bcc1c7',
            ],
            'contena-background-color' => [
                'isInherited' => false,
                'value' => '#fff',
            ],
            'contena-color-success' => [
                'isInherited' => false,
                'value' => '#3cc261',
            ],
            'contena-color-info' => [
                'isInherited' => false,
                'value' => '#26b6cf',
            ],
            'contena-color-warning' => [
                'isInherited' => false,
                'value' => '#ffbd5d',
            ],
            'contena-color-danger' => [
                'isInherited' => false,
                'value' => '#e52427',
            ],
            'contena-font-family-base' => [
                'isInherited' => false,
                'value' => '\'Inter\', sans-serif',
            ],
            'contena-text-color' => [
                'isInherited' => false,
                'value' => '#4a545b',
            ],
            'contena-font-family-headline' => [
                'isInherited' => false,
                'value' => '\'Inter\', sans-serif',
            ],
            'contena-headline-color' => [
                'isInherited' => false,
                'value' => '#4a545b',
            ],
            'contena-color-content-accent' => [
                'isInherited' => false,
                'value' => '#4a545b',
            ],
            'contena-color-primary-action' => [
                'isInherited' => false,
                'value' => '#008490',
            ],
            'contena-color-primary-action-text' => [
                'isInherited' => false,
                'value' => '#fff',
            ],
            'contena-logo-desktop' => [
                'isInherited' => false,
                'value' => 'app/frontend/dist/assets/logo/default-logo.png',
            ],
            'contena-logo-tablet' => [
                'isInherited' => false,
                'value' => 'app/frontend/dist/assets/logo/default-logo.png',
            ],
            'contena-logo-mobile' => [
                'isInherited' => false,
                'value' => 'app/frontend/dist/assets/logo/default-logo.png',
            ],
            'contena-logo-share' => [
                'isInherited' => false,
                'value' => null,
            ],
            'contena-logo-favicon' => [
                'isInherited' => false,
                'value' => 'app/frontend/dist/assets/logo/favicon.png',
            ],
            'test-something-with-options' => [
                'isInherited' => true,
                'value' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedBaseThemeFields8(): array
    {
        $baseThemeFields = self::getExtractedBaseThemeFields6();

        $baseThemeFields['test'] = [
            'isInherited' => true,
            'value' => null,
        ];

        unset($baseThemeFields['test-something-with-options']);

        return $baseThemeFields;
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedBaseThemeFields9(): array
    {
        $baseThemeFields = self::getExtractedBaseThemeFields6();

        $baseThemeFields['parent-custom-config'] = [
            'isInherited' => false,
            'value' => '40',
        ];

        $baseThemeFields['extend-parent-custom-config'] = [
            'isInherited' => false,
            'value' => '20',
        ];

        unset($baseThemeFields['test-something-with-options']);

        return $baseThemeFields;
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedTabs10(): array
    {
        return [
            'default' => [
                'labelSnippetKey' => 'default.label',
                'blocks' => [
                    'themeColors' => [
                        'labelSnippetKey' => 'default.themeColors.label',
                        'sections' => self::getExtractedSectionsThemeColors(),
                    ],
                    'statusColors' => [
                        'labelSnippetKey' => 'default.statusColors.label',
                        'sections' => self::getExtractedSectionsStatusColors(),
                    ],
                    'typography' => [
                        'labelSnippetKey' => 'default.typography.label',
                        'sections' => self::getExtractedSectionsTypography(),
                    ],
                    'content' => [
                        'labelSnippetKey' => 'default.content.label',
                        'sections' => self::getExtractedSectionsECommerce(),
                    ],
                    'media' => [
                        'labelSnippetKey' => 'default.media.label',
                        'sections' => self::getExtractedSectionsMediaNoHelpTexts(),
                    ],
                    'default' => [
                        'labelSnippetKey' => 'default.default.label',
                        'sections' => [
                            'default' => [
                                'labelSnippetKey' => 'default.default.default.label',
                                'fields' => [
                                    'test' => [
                                        'labelSnippetKey' => 'default.default.default.test.label',
                                        'helpTextSnippetKey' => 'default.default.default.test.helpText',
                                        'type' => null,
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'parent-custom-config' => [
                                        'labelSnippetKey' => 'default.default.default.parent-custom-config.label',
                                        'helpTextSnippetKey' => 'default.default.default.parent-custom-config.helpText',
                                        'type' => 'int',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'extend-parent-custom-config' => [
                                        'labelSnippetKey' => 'default.default.default.extend-parent-custom-config.label',
                                        'helpTextSnippetKey' => 'default.default.default.extend-parent-custom-config.helpText',
                                        'type' => 'int',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedTabs11(): array
    {
        return [
            'default' => [
                'labelSnippetKey' => 'default.label',
                'blocks' => [
                    'themeColors' => [
                        'labelSnippetKey' => 'default.themeColors.label',
                        'sections' => self::getExtractedSectionsThemeColors(),
                    ],
                    'statusColors' => [
                        'labelSnippetKey' => 'default.statusColors.label',
                        'sections' => self::getExtractedSectionsStatusColors(),
                    ],
                    'typography' => [
                        'labelSnippetKey' => 'default.typography.label',
                        'sections' => self::getExtractedSectionsTypography(),
                    ],
                    'content' => [
                        'labelSnippetKey' => 'default.content.label',
                        'sections' => self::getExtractedSectionsECommerce(),
                    ],
                    'media' => [
                        'labelSnippetKey' => 'default.media.label',
                        'sections' => self::getExtractedSectionsMediaNoHelpTexts(),
                    ],
                    'default' => [
                        'labelSnippetKey' => 'default.default.label',
                        'sections' => [
                            'default' => [
                                'labelSnippetKey' => 'default.default.default.label',
                                'fields' => [
                                    'parent-custom-config' => [
                                        'labelSnippetKey' => 'default.default.default.parent-custom-config.label',
                                        'helpTextSnippetKey' => 'default.default.default.parent-custom-config.helpText',
                                        'type' => 'int',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'extend-parent-custom-config' => [
                                        'labelSnippetKey' => 'default.default.default.extend-parent-custom-config.label',
                                        'helpTextSnippetKey' => 'default.default.default.extend-parent-custom-config.helpText',
                                        'type' => 'int',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedTabsNameTheme(): array
    {
        return [
            'default' => [
                'labelSnippetKey' => 'default.label',
                'blocks' => [
                    'themeColors' => [
                        'labelSnippetKey' => 'default.themeColors.label',
                        'sections' => [
                            'default' => [
                                'labelSnippetKey' => 'default.themeColors.default.label',
                                'fields' => [
                                    'contena-color-brand-primary' => [
                                        'labelSnippetKey' => 'default.themeColors.default.contena-color-brand-primary.label',
                                        'helpTextSnippetKey' => 'default.themeColors.default.contena-color-brand-primary.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'contena-color-brand-secondary' => [
                                        'labelSnippetKey' => 'default.themeColors.default.contena-color-brand-secondary.label',
                                        'helpTextSnippetKey' => 'default.themeColors.default.contena-color-brand-secondary.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'contena-border-color' => [
                                        'labelSnippetKey' => 'default.themeColors.default.contena-border-color.label',
                                        'helpTextSnippetKey' => 'default.themeColors.default.contena-border-color.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'contena-background-color' => [
                                        'labelSnippetKey' => 'default.themeColors.default.contena-background-color.label',
                                        'helpTextSnippetKey' => 'default.themeColors.default.contena-background-color.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'statusColors' => [
                        'labelSnippetKey' => 'default.statusColors.label',
                        'sections' => [
                            'default' => [
                                'labelSnippetKey' => 'default.statusColors.default.label',
                                'fields' => [
                                    'contena-color-success' => [
                                        'labelSnippetKey' => 'default.statusColors.default.contena-color-success.label',
                                        'helpTextSnippetKey' => 'default.statusColors.default.contena-color-success.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'contena-color-info' => [
                                        'labelSnippetKey' => 'default.statusColors.default.contena-color-info.label',
                                        'helpTextSnippetKey' => 'default.statusColors.default.contena-color-info.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'contena-color-warning' => [
                                        'labelSnippetKey' => 'default.statusColors.default.contena-color-warning.label',
                                        'helpTextSnippetKey' => 'default.statusColors.default.contena-color-warning.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'contena-color-danger' => [
                                        'labelSnippetKey' => 'default.statusColors.default.contena-color-danger.label',
                                        'helpTextSnippetKey' => 'default.statusColors.default.contena-color-danger.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'typography' => [
                        'labelSnippetKey' => 'default.typography.label',
                        'sections' => [
                            'default' => [
                                'labelSnippetKey' => 'default.typography.default.label',
                                'fields' => [
                                    'contena-font-family-base' => [
                                        'labelSnippetKey' => 'default.typography.default.contena-font-family-base.label',
                                        'helpTextSnippetKey' => 'default.typography.default.contena-font-family-base.helpText',
                                        'type' => 'fontFamily',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'contena-text-color' => [
                                        'labelSnippetKey' => 'default.typography.default.contena-text-color.label',
                                        'helpTextSnippetKey' => 'default.typography.default.contena-text-color.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'contena-font-family-headline' => [
                                        'labelSnippetKey' => 'default.typography.default.contena-font-family-headline.label',
                                        'helpTextSnippetKey' => 'default.typography.default.contena-font-family-headline.helpText',
                                        'type' => 'fontFamily',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'contena-headline-color' => [
                                        'labelSnippetKey' => 'default.typography.default.contena-headline-color.label',
                                        'helpTextSnippetKey' => 'default.typography.default.contena-headline-color.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'content' => [
                        'labelSnippetKey' => 'default.content.label',
                        'sections' => [
                            'default' => [
                                'labelSnippetKey' => 'default.content.default.label',
                                'fields' => [
                                    'contena-color-content-accent' => [
                                        'labelSnippetKey' => 'default.content.default.contena-color-content-accent.label',
                                        'helpTextSnippetKey' => 'default.content.default.contena-color-content-accent.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'contena-color-primary-action' => [
                                        'labelSnippetKey' => 'default.content.default.contena-color-primary-action.label',
                                        'helpTextSnippetKey' => 'default.content.default.contena-color-primary-action.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'contena-color-primary-action-text' => [
                                        'labelSnippetKey' => 'default.content.default.contena-color-primary-action-text.label',
                                        'helpTextSnippetKey' => 'default.content.default.contena-color-primary-action-text.helpText',
                                        'type' => 'color',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'media' => [
                        'labelSnippetKey' => 'default.media.label',
                        'sections' => [
                            'default' => [
                                'labelSnippetKey' => 'default.media.default.label',
                                'fields' => [
                                    'contena-logo-desktop' => [
                                        'labelSnippetKey' => 'default.media.default.contena-logo-desktop.label',
                                        'helpTextSnippetKey' => 'default.media.default.contena-logo-desktop.helpText',
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => true,
                                    ],
                                    'contena-logo-tablet' => [
                                        'labelSnippetKey' => 'default.media.default.contena-logo-tablet.label',
                                        'helpTextSnippetKey' => 'default.media.default.contena-logo-tablet.helpText',
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => true,
                                    ],
                                    'contena-logo-mobile' => [
                                        'labelSnippetKey' => 'default.media.default.contena-logo-mobile.label',
                                        'helpTextSnippetKey' => 'default.media.default.contena-logo-mobile.helpText',
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => true,
                                    ],
                                    'contena-logo-share' => [
                                        'labelSnippetKey' => 'default.media.default.contena-logo-share.label',
                                        'helpTextSnippetKey' => 'default.media.default.contena-logo-share.helpText',
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'contena-logo-favicon' => [
                                        'labelSnippetKey' => 'default.media.default.contena-logo-favicon.label',
                                        'helpTextSnippetKey' => 'default.media.default.contena-logo-favicon.helpText',
                                        'type' => 'media',
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'test-something-with-options' => [
                                        'type' => 'text',
                                        'labelSnippetKey' => 'default.media.default.test-something-with-options.label',
                                        'helpTextSnippetKey' => 'default.media.default.test-something-with-options.helpText',
                                        'fullWidth' => null,
                                        'custom' => [
                                            'componentName' => 'contena-single-select',
                                            'options' => [
                                                [
                                                    'value' => 'Hello',
                                                    'labelSnippetKey' => 'default.media.default.test-something-with-options.0.label',
                                                ],
                                                [
                                                    'value' => 'World',
                                                    'labelSnippetKey' => 'default.media.default.test-something-with-options.1.label',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedTabs3(): array
    {
        return [
            'default' => [
                'labelSnippetKey' => 'default.label',
                'blocks' => [
                    'themeColors' => [
                        'labelSnippetKey' => 'default.themeColors.label',
                        'sections' => self::getExtractedSectionsThemeColors(),
                    ],
                    'statusColors' => [
                        'labelSnippetKey' => 'default.statusColors.label',
                        'sections' => self::getExtractedSectionsStatusColors(),
                    ],
                    'typography' => [
                        'labelSnippetKey' => 'default.typography.label',
                        'sections' => self::getExtractedSectionsTypography(),
                    ],
                    'content' => [
                        'labelSnippetKey' => 'default.content.label',
                        'sections' => self::getExtractedSectionsECommerce(),
                    ],
                    'media' => [
                        'labelSnippetKey' => 'default.media.label',
                        'sections' => self::getExtractedSectionsMediaNoHelpTexts(),
                    ],
                    'default' => [
                        'labelSnippetKey' => 'default.default.label',
                        'sections' => [
                            'default' => [
                                'labelSnippetKey' => 'default.default.default.label',
                                'fields' => [
                                    'first' => [
                                        'labelSnippetKey' => 'default.default.default.first.label',
                                        'helpTextSnippetKey' => 'default.default.default.first.helpText',
                                        'type' => null,
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                    'test' => [
                                        'labelSnippetKey' => 'default.default.default.test.label',
                                        'helpTextSnippetKey' => 'default.default.default.test.helpText',
                                        'type' => null,
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedTabs1(): array
    {
        return [
            'default' => [
                'labelSnippetKey' => 'default.label',
                'blocks' => [
                    'themeColors' => [
                        'labelSnippetKey' => 'default.themeColors.label',
                        'sections' => self::getExtractedSectionsThemeColors(),
                    ],
                    'statusColors' => [
                        'labelSnippetKey' => 'default.statusColors.label',
                        'sections' => self::getExtractedSectionsStatusColors(),
                    ],
                    'typography' => [
                        'labelSnippetKey' => 'default.typography.label',
                        'sections' => self::getExtractedSectionsTypography(),
                    ],
                    'content' => [
                        'labelSnippetKey' => 'default.content.label',
                        'sections' => self::getExtractedSectionsECommerce(),
                    ],
                    'media' => [
                        'labelSnippetKey' => 'default.media.label',
                        'sections' => self::getExtractedSectionsMediaNoHelpTexts(),
                    ],
                    'default' => [
                        'labelSnippetKey' => 'default.default.label',
                        'sections' => [
                            'default' => [
                                'labelSnippetKey' => 'default.default.default.label',
                                'fields' => [
                                    'test' => [
                                        'labelSnippetKey' => 'default.default.default.test.label',
                                        'helpTextSnippetKey' => 'default.default.default.test.helpText',
                                        'type' => null,
                                        'custom' => null,
                                        'fullWidth' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedFields2(): array
    {
        return [...self::getExtractedFieldsSub1(), ...[
            'test' => [
                'extensions' => [
                ],
                'name' => 'test',
                'type' => null,
                'value' => [
                    0 => 'no_test',
                ],
                'editable' => null,
                'block' => null,
                'section' => null,
                'tab' => null,
                'order' => null,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedFieldsSub1(): array
    {
        return [
            'contena-color-brand-primary' => [
                'extensions' => [
                ],
                'name' => 'contena-color-brand-primary',
                'type' => 'color',
                'value' => '#008490',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-brand-secondary' => [
                'extensions' => [
                ],
                'name' => 'contena-color-brand-secondary',
                'type' => 'color',
                'value' => '#526e7f',
                'editable' => true,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-border-color' => [
                'extensions' => [
                ],
                'name' => 'contena-border-color',
                'type' => 'color',
                'value' => '#bcc1c7',
                'editable' => 1,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-background-color' => [
                'extensions' => [
                ],
                'name' => 'contena-background-color',
                'type' => 'color',
                'value' => '#fff',
                'editable' => 1,
                'block' => 'themeColors',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-success' => [
                'extensions' => [
                ],
                'name' => 'contena-color-success',
                'type' => 'color',
                'value' => '#3cc261',
                'editable' => 1,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-info' => [
                'extensions' => [
                ],
                'name' => 'contena-color-info',
                'type' => 'color',
                'value' => '#26b6cf',
                'editable' => 1,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-warning' => [
                'extensions' => [
                ],
                'name' => 'contena-color-warning',
                'type' => 'color',
                'value' => '#ffbd5d',
                'editable' => 1,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-danger' => [
                'extensions' => [
                ],
                'name' => 'contena-color-danger',
                'type' => 'color',
                'value' => '#e52427',
                'editable' => 1,
                'block' => 'statusColors',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-font-family-base' => [
                'extensions' => [
                ],
                'name' => 'contena-font-family-base',
                'type' => 'fontFamily',
                'value' => '\'Inter\', sans-serif',
                'editable' => 1,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-text-color' => [
                'extensions' => [
                ],
                'name' => 'contena-text-color',
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => 1,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-font-family-headline' => [
                'extensions' => [
                ],
                'name' => 'contena-font-family-headline',
                'type' => 'fontFamily',
                'value' => '\'Inter\', sans-serif',
                'editable' => 1,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-headline-color' => [
                'extensions' => [
                ],
                'name' => 'contena-headline-color',
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => 1,
                'block' => 'typography',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-content-accent' => [
                'extensions' => [
                ],
                'name' => 'contena-color-content-accent',
                'type' => 'color',
                'value' => '#4a545b',
                'editable' => 1,
                'block' => 'content',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-primary-action' => [
                'extensions' => [
                ],
                'name' => 'contena-color-primary-action',
                'type' => 'color',
                'value' => '#008490',
                'editable' => 1,
                'block' => 'content',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-color-primary-action-text' => [
                'extensions' => [
                ],
                'name' => 'contena-color-primary-action-text',
                'type' => 'color',
                'value' => '#fff',
                'editable' => 1,
                'block' => 'content',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-logo-desktop' => [
                'extensions' => [
                ],
                'name' => 'contena-logo-desktop',
                'type' => 'media',
                'value' => 'app/frontend/dist/assets/logo/default-logo.png',
                'editable' => 1,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 100,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'contena-logo-tablet' => [
                'extensions' => [
                ],
                'name' => 'contena-logo-tablet',
                'type' => 'media',
                'value' => 'app/frontend/dist/assets/logo/default-logo.png',
                'editable' => 1,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 200,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'contena-logo-mobile' => [
                'extensions' => [
                ],
                'name' => 'contena-logo-mobile',
                'type' => 'media',
                'value' => 'app/frontend/dist/assets/logo/default-logo.png',
                'editable' => 1,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 300,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => true,
            ],
            'contena-logo-share' => [
                'extensions' => [
                ],
                'name' => 'contena-logo-share',
                'type' => 'media',
                'value' => null,
                'editable' => 1,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 400,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
            'contena-logo-favicon' => [
                'extensions' => [
                ],
                'name' => 'contena-logo-favicon',
                'type' => 'media',
                'value' => 'app/frontend/dist/assets/logo/favicon.png',
                'editable' => 1,
                'block' => 'media',
                'section' => null,
                'tab' => null,
                'order' => 500,
                'sectionOrder' => null,
                'blockOrder' => null,
                'tabOrder' => null,
                'custom' => null,
                'scss' => null,
                'fullWidth' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedFields9(): array
    {
        $fields = self::getExtractedFieldsSub1();

        $fields['contena-color-brand-primary']['value'] = '#adbd00';

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedSectionsThemeColors(): array
    {
        return [
            'default' => [
                'labelSnippetKey' => 'default.themeColors.default.label',
                'fields' => [
                    'contena-color-brand-primary' => [
                        'labelSnippetKey' => 'default.themeColors.default.contena-color-brand-primary.label',
                        'helpTextSnippetKey' => 'default.themeColors.default.contena-color-brand-primary.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'contena-color-brand-secondary' => [
                        'labelSnippetKey' => 'default.themeColors.default.contena-color-brand-secondary.label',
                        'helpTextSnippetKey' => 'default.themeColors.default.contena-color-brand-secondary.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'contena-border-color' => [
                        'labelSnippetKey' => 'default.themeColors.default.contena-border-color.label',
                        'helpTextSnippetKey' => 'default.themeColors.default.contena-border-color.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'contena-background-color' => [
                        'labelSnippetKey' => 'default.themeColors.default.contena-background-color.label',
                        'helpTextSnippetKey' => 'default.themeColors.default.contena-background-color.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedSectionsStatusColors(): array
    {
        return [
            'default' => [
                'labelSnippetKey' => 'default.statusColors.default.label',
                'fields' => [
                    'contena-color-success' => [
                        'labelSnippetKey' => 'default.statusColors.default.contena-color-success.label',
                        'helpTextSnippetKey' => 'default.statusColors.default.contena-color-success.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'contena-color-info' => [
                        'labelSnippetKey' => 'default.statusColors.default.contena-color-info.label',
                        'helpTextSnippetKey' => 'default.statusColors.default.contena-color-info.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'contena-color-warning' => [
                        'labelSnippetKey' => 'default.statusColors.default.contena-color-warning.label',
                        'helpTextSnippetKey' => 'default.statusColors.default.contena-color-warning.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'contena-color-danger' => [
                        'labelSnippetKey' => 'default.statusColors.default.contena-color-danger.label',
                        'helpTextSnippetKey' => 'default.statusColors.default.contena-color-danger.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedSectionsTypography(): array
    {
        return [
            'default' => [
                'labelSnippetKey' => 'default.typography.default.label',
                'fields' => [
                    'contena-font-family-base' => [
                        'labelSnippetKey' => 'default.typography.default.contena-font-family-base.label',
                        'helpTextSnippetKey' => 'default.typography.default.contena-font-family-base.helpText',
                        'type' => 'fontFamily',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'contena-text-color' => [
                        'labelSnippetKey' => 'default.typography.default.contena-text-color.label',
                        'helpTextSnippetKey' => 'default.typography.default.contena-text-color.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'contena-font-family-headline' => [
                        'labelSnippetKey' => 'default.typography.default.contena-font-family-headline.label',
                        'helpTextSnippetKey' => 'default.typography.default.contena-font-family-headline.helpText',
                        'type' => 'fontFamily',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'contena-headline-color' => [
                        'labelSnippetKey' => 'default.typography.default.contena-headline-color.label',
                        'helpTextSnippetKey' => 'default.typography.default.contena-headline-color.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedSectionsECommerce(): array
    {
        return [
            'default' => [
                'labelSnippetKey' => 'default.content.default.label',
                'fields' => [
                    'contena-color-content-accent' => [
                        'labelSnippetKey' => 'default.content.default.contena-color-content-accent.label',
                        'helpTextSnippetKey' => 'default.content.default.contena-color-content-accent.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'contena-color-primary-action' => [
                        'labelSnippetKey' => 'default.content.default.contena-color-primary-action.label',
                        'helpTextSnippetKey' => 'default.content.default.contena-color-primary-action.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'contena-color-primary-action-text' => [
                        'labelSnippetKey' => 'default.content.default.contena-color-primary-action-text.label',
                        'helpTextSnippetKey' => 'default.content.default.contena-color-primary-action-text.helpText',
                        'type' => 'color',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedSectionsMedia(): array
    {
        return [
            'default' => [
                'labelSnippetKey' => 'default.media.default.label',
                'fields' => [
                    'contena-logo-desktop' => [
                        'labelSnippetKey' => 'default.media.default.contena-logo-desktop.label',
                        'helpTextSnippetKey' => 'default.media.default.contena-logo-desktop.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => true,
                    ],
                    'contena-logo-tablet' => [
                        'labelSnippetKey' => 'default.media.default.contena-logo-tablet.label',
                        'helpTextSnippetKey' => 'default.media.default.contena-logo-tablet.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => true,
                    ],
                    'contena-logo-mobile' => [
                        'labelSnippetKey' => 'default.media.default.contena-logo-mobile.label',
                        'helpTextSnippetKey' => 'default.media.default.contena-logo-mobile.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => true,
                    ],
                    'contena-logo-share' => [
                        'labelSnippetKey' => 'default.media.default.contena-logo-share.label',
                        'helpTextSnippetKey' => 'default.media.default.contena-logo-share.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'contena-logo-favicon' => [
                        'labelSnippetKey' => 'default.media.default.contena-logo-favicon.label',
                        'helpTextSnippetKey' => 'default.media.default.contena-logo-favicon.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getExtractedSectionsMediaNoHelpTexts(): array
    {
        return [
            'default' => [
                'labelSnippetKey' => 'default.media.default.label',
                'fields' => [
                    'contena-logo-desktop' => [
                        'labelSnippetKey' => 'default.media.default.contena-logo-desktop.label',
                        'helpTextSnippetKey' => 'default.media.default.contena-logo-desktop.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => true,
                    ],
                    'contena-logo-tablet' => [
                        'labelSnippetKey' => 'default.media.default.contena-logo-tablet.label',
                        'helpTextSnippetKey' => 'default.media.default.contena-logo-tablet.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => true,
                    ],
                    'contena-logo-mobile' => [
                        'labelSnippetKey' => 'default.media.default.contena-logo-mobile.label',
                        'helpTextSnippetKey' => 'default.media.default.contena-logo-mobile.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => true,
                    ],
                    'contena-logo-share' => [
                        'labelSnippetKey' => 'default.media.default.contena-logo-share.label',
                        'helpTextSnippetKey' => 'default.media.default.contena-logo-share.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                    'contena-logo-favicon' => [
                        'labelSnippetKey' => 'default.media.default.contena-logo-favicon.label',
                        'helpTextSnippetKey' => 'default.media.default.contena-logo-favicon.helpText',
                        'type' => 'media',
                        'custom' => null,
                        'fullWidth' => null,
                    ],
                ],
            ],
        ];
    }
}
