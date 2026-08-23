<?php declare(strict_types=1);

use Contena\Core\Framework\Adapter\Twig\SwTwigFunction;

return [
    'filePatterns' => [
        '**/Test/**', // Testing
        '**/src/Core/Framework/Update/**', // Updater
        '**/src/Core/TestBootstrapper.php', // Testing
        '**/src/Core/Framework/Demodata/Faker/Commerce.php', // dev dependency
        '**/src/Core/DevOps/StaticAnalyze/**', // dev dependency
        '**/src/Core/Profiling/Doctrine/BacktraceDebugDataHolder.php', // dev dependency
        '**/src/Core/Migration/Traits/MigrationUntouchedDbTestTrait.php', // Test code in prod
        '**src/Core/Framework/Script/ServiceStubs.php', // never intended to be extended
        '**/tests/unit/Core/DevOps/Docs/Script/_fixtures/**', // Testing
        '**/src/Core/Framework/App/AppException.php', // intended to be internal
    ],
    'errors' => [
        // Don't complain about doctrine library changes
        'Doctrine\\\\DBAL',

        // Will be typed in Symfony 8 (maybe)
        preg_quote('Symfony\Component\Console\Command\Command#configure() changed from no type to void', '/'),

        // False positive, when an object extends Symfony Command and has its own constructor
        '.* was added to Method __construct\(\) of class Symfony\\\\Component\\\\Console\\\\Command\\\\Command',
        preg_quote('Symfony\Component\Console\Command\Command#__construct()', '/'),

        // Cannot be inspected through reflection https://github.com/Roave/BetterReflection/issues/1376
        'An enum expression .* is not supported in .*',

        // Expected to be appended when a new event is added
        preg_quote('Value of constant Contena\Core\Framework\Webhook\Hookable', '/'),

        // Intentional rename of the technical-term analyzer chain so the public
        // identifier matches how the chain is referenced everywhere else
        // (constants, `buildTextFieldConfig(technicalTerms: true)`, the
        // architecture doc). Contena-internal users were already going through
        // `ElasticsearchFieldBuilder::ANALYZER_WHITESPACE_TECHNICAL_*` and the
        // `TECHNICAL_TERM_SEARCH_FIELD` const — both still resolve correctly;
        // only the underlying analyzer string moved from
        // `sw_*_word_delimiter_*_analyzer` to `sw_*_technical_term_*_analyzer`.
        // Documented in UPGRADE-6.8.md.
        preg_quote('Value of constant Contena\Elasticsearch\Framework\ElasticsearchFieldBuilder::ANALYZER_WHITESPACE_TECHNICAL_INDEX', '/'),
        preg_quote('Value of constant Contena\Elasticsearch\Framework\ElasticsearchFieldBuilder::ANALYZER_WHITESPACE_TECHNICAL_SEARCH', '/'),
        preg_quote('Value of constant Contena\Elasticsearch\Framework\AbstractElasticsearchDefinition::TECHNICAL_TERM_SEARCH_FIELD', '/'),

        // Had a typo in the internal annotation
        preg_quote('CHANGED: Contena\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder was marked "@internal"', '/'),

        // SystemDumpDatabaseCommand was not marked @internal
        preg_quote('CHANGED: Contena\\Core\\DevOps\\System\\Command\\SystemDumpDatabaseCommand was marked "@internal"', '/'),
        preg_quote('REMOVED: Method Contena\\Core\\DevOps\\System\\Command\\SystemDumpDatabaseCommand#getIgnoreTableStmt() was removed', '/'),

        // Plugin lifecycle command constructors were not marked @internal
        preg_quote('REMOVED: Method Contena\Core\Framework\Plugin\Command\Lifecycle\AbstractPluginLifecycleCommand#__construct() was removed', '/'),
        preg_quote('ADDED: Parameter projectDir was added to Method __construct() of class Contena\Core\Framework\Plugin\Command\Lifecycle\AbstractPluginLifecycleCommand', '/'),
        preg_quote('CHANGED: Contena\Core\Framework\Plugin\Command\Lifecycle\AbstractPluginLifecycleCommand#__construct() was marked "@internal"', '/'),
        preg_quote('CHANGED: The number of required arguments for Contena\Core\Framework\Plugin\Command\Lifecycle\AbstractPluginLifecycleCommand#__construct() increased from 3 to 4', '/'),

        // No break as all existing NoContentResponse usages are still valid with the widened StoreApiResponse return type
        'CHANGED: The return type of Contena\\\\Core\\\\Content\\\\Newsletter\\\\SalesChannel\\\\.* changed from Contena\\\\Core\\\\System\\\\SalesChannel\\\\NoContentResponse to (?:the non-covariant )?Contena\\\\Core\\\\System\\\\SalesChannel\\\\StoreApiResponse',

        // class is @final, so making a parameter nullable is not a breaking change
        preg_quote('CHANGED: The parameter $fileType of Contena\Core\Checkout\Document\Service\DocumentGenerator#readDocument() changed from string to string|null', '/'),

        // SystemRestoreDatabaseCommand was marked @internal
        preg_quote('CHANGED: Contena\\Core\\DevOps\\System\\Command\\SystemRestoreDatabaseCommand was marked "@internal"', '/'),

        // Unused protected method from final class can be removed safely
        preg_quote('REMOVED: Method Contena\Core\Framework\Store\InAppPurchase\Services\DecodedPurchaseStruct#throwException() was removed', '/'),

        // TaxProviderPersister was mistakenly not marked @internal
        preg_quote('CHANGED: Contena\Core\Framework\App\Lifecycle\Persister\TaxProviderPersister was marked "@internal"', '/'),
        preg_quote('REMOVED: Method Contena\Core\Framework\App\Lifecycle\Persister\TaxProviderPersister#updateTaxProviders() was removed', '/'),

        // Constants should be `float` to reflect the expected type
        preg_quote('CHANGED: Value of constant Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking::', '/'),

        // Return type is still of type "self" but more specific. Could never be something different from the InvalidSortQueryException, so this should be fine
        'CHANGED: The return type of Contena\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\DataAbstractionLayerException.* changed from self to (?:the non-covariant )?Contena\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\Exception\\\\InvalidSortQueryException',

        // minor library update, no break
        preg_quote(' OpenSearch\Client', '/'),
        // widening input argument in exception factory, no break
        preg_quote('CHANGED: The parameter $previous of Contena\Elasticsearch\Product\ElasticsearchProductException::cannotChangeFieldType() changed from OpenSearch\Common\Exceptions\BadRequest400Exception to OpenSearch\Common\Exceptions\BadRequest400Exception|OpenSearch\Exception\BadRequestHttpException', '/'),
        preg_quote('CHANGED: The parameter $previous of Contena\Elasticsearch\Product\ElasticsearchProductException::cannotChangeCustomFieldType() changed from OpenSearch\Common\Exceptions\BadRequest400Exception to OpenSearch\Common\Exceptions\BadRequest400Exception|OpenSearch\Exception\BadRequestHttpException', '/'),
        // constructor changes of internal decorator, no break
        preg_quote('ADDED: Parameter transport was added to Method __construct() of class Contena\Elasticsearch\Profiler\ClientProfiler', '/'),
        preg_quote('CHANGED: Parameter 0 of Contena\Elasticsearch\Profiler\ClientProfiler#__construct() changed name from client to transport', '/'),

        /** Internal annotation on {@see SwTwigFunction} was not recognized correctly */
        preg_quote('CHANGED: Contena\Core\Framework\Adapter\Twig\SwTwigFunction was marked "@internal"', '/'),
        preg_quote('REMOVED: Method Contena\Core\Framework\Adapter\Twig\SwTwigFunction::escapeFilter() was removed', '/'),
        preg_quote('REMOVED: Method Contena\Core\Framework\Adapter\Twig\SwTwigFunction::resetEscapeCache() was removed', '/'),

        // The implemented Twig extension contract already documents this as array<NodeVisitorInterface>
        preg_quote('CHANGED: The return type of Twig\Extension\AbstractExtension#getNodeVisitors() changed from no type to array', '/'),

        // Twig added this method in 3.27 via https://github.com/twigphp/Twig/pull/4816
        preg_quote('REMOVED: Method Twig\TokenParser\AbstractTokenParser#isAlwaysAllowedInSandbox() was removed', '/'),

        // MailDataSimulatorFieldEvent no longer exposes Faker in the runtime simulate feature
        preg_quote('REMOVED: Property Contena\Core\Content\MailTemplate\Service\Event\MailDataSimulatorFieldEvent#$faker was removed', '/'),
        preg_quote('REMOVED: Parameter faker was removed from Method Contena\Core\Content\MailTemplate\Service\Event\MailDataSimulatorFieldEvent::__construct()', '/'),

        // Optional parameter added with default null; existing callers are unaffected
        preg_quote('ADDED: Parameter introducedIn was added to Method triggerDeprecationOrThrow() of class Contena\Core\Framework\Feature', '/'),

        // Rule classes are tagged @final
        preg_quote('CHANGED: Type of property Contena\Core\Checkout\Customer\Rule\CustomerBirthdayRule#$birthday changed from string|null to string|array|null', '/'),
        preg_quote('CHANGED: Type of property Contena\Core\Checkout\Cart\Rule\LineItemReleaseDateRule#$lineItemReleaseDate changed from string|null to string|array|null', '/'),
        preg_quote('CHANGED: Type of property Contena\Core\Checkout\Cart\Rule\LineItemCreationDateRule#$lineItemCreationDate changed from string|null to string|array|null', '/'),
        preg_quote('REMOVED: Property Contena\Core\Checkout\Cart\Rule\LineItemPurchasePriceRule#$isNet was removed', '/'),
        preg_quote('CHANGED: The return type of Contena\Core\Framework\Rule\Rule#getConfig() changed from Contena\Core\Framework\Rule\RuleConfig|null to Contena\Core\Framework\Rule\RuleConfig', '/'),

        // DefinitionValidator is @final; optional parameter added with default [], existing callers are unaffected
        preg_quote('ADDED: Parameter toleratedNonStandardForeignKeys was added to Method validate() of class Contena\Core\Framework\DataAbstractionLayer\DefinitionValidator', '/'),

        // DocumentType translations were incorrectly typed as product translations
        preg_quote('CHANGED: Type of property Contena\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity#$translations changed from Contena\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection|null', '/'),
        preg_quote('CHANGED: The return type of Contena\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity#getTranslations() changed from Contena\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection|null', '/'),
        preg_quote('CHANGED: The parameter $translations of Contena\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity#setTranslations() changed from Contena\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection', '/'),

        // Contravariant widening so the filter also accepts PartialEntity media from partial listing loading
        preg_quote('The parameter $media of Contena\Storefront\Framework\Twig\Extension\UrlEncodingTwigFilter#encodeMediaUrl() changed from', '/'),

        // Experimental MCP feature (gated behind the MCP_SERVER flag, all MCP classes are
        // @experimental stableVersion:v6.8.0). The MCP rate-limit route was split per API
        // scope, replacing the single RateLimiter::MCP constant with MCP_ADMIN_API /
        // MCP_STORE_API. The constant lived on the non-experimental RateLimiter class so it
        // was not auto-skipped, but it is part of the still-experimental MCP surface.
        preg_quote('REMOVED: Constant Contena\Core\Framework\RateLimiter\RateLimiter::MCP was removed', '/'),

        // EntitySearchResult::merge() takes EntityCollection (not self) so it accepts any collection, not just other search results.
        'CHANGED: The parameter \$collection of Contena\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\EntityCollection#merge\(\) changed from self to (?:a non-contravariant )?Contena\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\EntityCollection',

        // Translated CustomerGroupEntity properties are now nullable like the translation entity (fixes #16461).
        preg_quote('CHANGED: Type of property Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#$registrationTitle changed from string to string|null', '/'),
        preg_quote('CHANGED: Type of property Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#$registrationIntroduction changed from string to string|null', '/'),
        preg_quote('CHANGED: Type of property Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#$registrationOnlyCompanyRegistration changed from bool to bool|null', '/'),
        preg_quote('CHANGED: Type of property Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#$registrationSeoMetaDescription changed from string to string|null', '/'),
        preg_quote('CHANGED: The return type of Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#getRegistrationTitle() changed from string', '/'),
        preg_quote('CHANGED: The return type of Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#getRegistrationIntroduction() changed from string', '/'),
        preg_quote('CHANGED: The return type of Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#getRegistrationOnlyCompanyRegistration() changed from bool', '/'),
        preg_quote('CHANGED: The return type of Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#getRegistrationSeoMetaDescription() changed from string', '/'),

        // parent method has no type. not really a break
        preg_quote('CHANGED: The return type of Contena\Core\Framework\Migration\Command\RefreshMigrationCommand#configure() changed from void to ', '/'),
    ],
];
