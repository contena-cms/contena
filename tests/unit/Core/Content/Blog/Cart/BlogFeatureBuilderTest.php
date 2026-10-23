<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\CartDataCollection;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Content\Blog\Aggregate\BlogFeatureSet\BlogFeatureSetDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogFeatureSet\BlogFeatureSetEntity;
use Contena\Core\Content\Blog\Cart\BlogFeatureBuilder;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Contena\Core\Content\Property\PropertyGroupEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Exception\EntityRepositoryNotFoundException;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\LanguageInfo;
use Contena\Core\System\CustomField\CustomFieldCollection;
use Contena\Core\System\CustomField\CustomFieldDefinition;
use Contena\Core\System\CustomField\CustomFieldEntity;
use Contena\Core\System\CustomField\CustomFieldTypes;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\Unit\UnitEntity;
use Contena\Core\Test\Generator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(BlogFeatureBuilder::class)]
class BlogFeatureBuilderTest extends TestCase
{
    private const LANGUAGE_ID = 'language-id-123';

    private const CHILD_LANGUAGE_ID = 'child-language-id-123';

    private const LANGUAGE_CHAIN = [self::LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM];

    private const CHILD_LANGUAGE_CHAIN = [self::CHILD_LANGUAGE_ID, self::LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM];

    private const LOCALE_CODE = 'de-DE';

    private const CURRENCY_ID = 'currency-id-123';

    private const REFERENCED_ENTITY_NAME = 'blog';

    private const FIRST_ENTITY_ID = '0191c3a1c1a271b3a1a1c3a1c1a271b3';

    private const SECOND_ENTITY_ID = '0191c3a1c1a271b3a1a1c3a1c1a271b4';


    /**
     * @var Stub&EntityRepository<CustomFieldCollection>
     */
    private Stub&EntityRepository $customFieldRepository;

    private Stub&LanguageLocaleCodeProvider $languageLocaleProvider;

    private Stub&DefinitionInstanceRegistry $definitionRegistry;

    private Stub&TranslatorInterface $translator;

    private ChannelContext $channelContext;

    protected function setUp(): void
    {
        $this->customFieldRepository = static::createStub(EntityRepository::class);
        $this->languageLocaleProvider = static::createStub(LanguageLocaleCodeProvider::class);
        $this->definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $this->translator = static::createStub(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);
        $this->channelContext = Generator::generateChannelContext();

        $this->blogFeatureBuilder = new BlogFeatureBuilder(
            $this->customFieldRepository,
            $this->languageLocaleProvider,
            $this->definitionRegistry,
            $this->translator,
        );
    }

    public function testAddFeaturesIntoLineItems(): void
    {
        $blogId = 'blog-id-123';
        $lineItem = new LineItem($blogId, LineItem::BLOG_LINE_ITEM_TYPE, $blogId);

        $blog = new ChannelBlogEntity();
        $blog->setName('foo');
        $blog->setId($blogId);

        $group = new PropertyGroupEntity();
        $group->setTranslated(['name' => 'color']);
        $group->setId('group-1');

        $properties = new PropertyGroupOptionCollection();
        $property = new PropertyGroupOptionEntity();
        $property->setName('red');
        $property->setGroupId($group->getId());
        $property->setTranslated(['name' => 'red']);
        $property->setId('option-1');
        $property->setGroup($group);

        $properties->add($property);

        $unit = new UnitEntity();
        $unit->setTranslated(['name' => 'piece']);

        $blog->setProperties($properties);
        $blog->setUnit($unit);

        $referencePrice = new ReferencePrice(0.5, 1, 1, 'piece');

        $price = new CalculatedPrice(
            unitPrice: 1.0,
            totalPrice: 1.0,
            calculatedTaxes: new CalculatedTaxCollection(),
            taxRules: new TaxRuleCollection(),
            referencePrice: $referencePrice,
        );

        $lineItem->setPrice($price);

        $featureSet = new BlogFeatureSetEntity();
        $featureSet->setFeatures([
            ['name' => 'Color', 'id' => 'group-1', 'type' => BlogFeatureSetDefinition::TYPE_BLOG_PROPERTY, 'position' => 1],
            ['name' => 'name', 'id' => 'feature-2', 'type' => BlogFeatureSetDefinition::TYPE_BLOG_ATTRIBUTE, 'position' => 2],
            ['name' => 'Feature 4', 'id' => 'feature-4', 'type' => BlogFeatureSetDefinition::TYPE_BLOG_REFERENCE_PRICE, 'position' => 4],
        ]);

        $blog->setFeatureSet($featureSet);
        $data = new CartDataCollection();

        $data->set('blog-' . $blogId, $blog);

        $this->blogFeatureBuilder->add([$lineItem], $data, $this->channelContext);

        static::assertSame([
            [
                'label' => 'color',
                'value' => [
                    'option-1' => [
                        'id' => 'option-1',
                        'name' => 'red',
                        'mediaId' => null,
                        'colorHexCode' => null,
                    ],
                ],
                'type' => 'property',
            ],
            [
                'label' => 'name',
                'value' => 'foo',
                'type' => 'blog',
            ],
            [
                'label' => 'referencePrice',
                'value' => [
                    'price' => 0.5,
                    'purchaseUnit' => 1.0,
                    'referenceUnit' => 1.0,
                    'unitName' => 'piece',
                ],
                'type' => 'referencePrice',
            ],
        ], $lineItem->getPayload()['features']);
    }

    public function testPrepareCollectsCustomFieldsFromTheTranslatedBlogValue(): void
    {
        $blog = new ChannelBlogEntity();
        $blog->setId('blog-id-123');
        $blog->setCustomFields(null);
        $blog->setTranslated(['customFields' => ['custom_material' => 'wood']]);
        $blog->setFeatureSet($this->createCustomFieldFeatureSet());

        $customField = $this->createCustomField(['en-GB' => 'Material']);

        $this->customFieldRepository->method('search')->willReturn(new EntitySearchResult(
            CustomFieldDefinition::ENTITY_NAME,
            1,
            new CustomFieldCollection([$customField]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        $data = new CartDataCollection();
        $data->set('blog-blog-id-123', $blog);

        $lineItem = new LineItem('blog-id-123', LineItem::BLOG_LINE_ITEM_TYPE, 'blog-id-123');

        $this->blogFeatureBuilder->prepare([$lineItem], $data, $this->channelContext);

        static::assertSame($customField, $data->get('custom-field-custom_material'));
    }

    public function testCustomFieldLabelUsesTheLanguageOfTheContext(): void
    {
        $features = $this->buildCustomFieldFeatures([
            'en-GB' => 'Material',
            'de-DE' => 'Werkstoff',
        ]);

        static::assertSame([
            [
                'label' => 'Werkstoff',
                'value' => [
                    'id' => 'custom-field-id',
                    'type' => CustomFieldTypes::TEXT,
                    'content' => 'wood',
                ],
                'type' => 'customField',
            ],
        ], $features);
    }

    public function testCustomFieldLabelPrefersTheSnippetOfTheCustomField(): void
    {
        $this->useSnippets([self::LOCALE_CODE => ['customFields.custom_material' => 'Holzart']]);

        $features = $this->buildCustomFieldFeatures([
            'en-GB' => 'Material',
            'de-DE' => 'Werkstoff',
        ]);

        static::assertSame('Holzart', $features[0]['label']);
    }

    /**
     * Only Frontend requests set the translator locale, so the snippet has to be read in the
     * locale of the context for Store API and Admin API requests to label the feature correctly.
     */
    public function testCustomFieldLabelReadsTheSnippetInTheLocaleOfTheContext(): void
    {
        $this->useSnippets([
            'en-GB' => ['customFields.custom_material' => 'Fabric'],
            self::LOCALE_CODE => ['customFields.custom_material' => 'Stoff'],
        ]);

        $features = $this->buildCustomFieldFeatures([
            'en-GB' => 'Material',
            'de-DE' => 'Werkstoff',
        ]);

        static::assertSame('Stoff', $features[0]['label']);
    }

    /**
     * @param array<string, string> $snippets
     */
    #[DataProvider('unusableSnippetProvider')]
    public function testCustomFieldLabelFallsBackToTheConfigLabelWithoutAUsableSnippet(array $snippets): void
    {
        $this->useSnippets([self::LOCALE_CODE => $snippets]);

        $features = $this->buildCustomFieldFeatures([
            'en-GB' => 'Material',
            'de-DE' => 'Werkstoff',
        ]);

        static::assertSame('Werkstoff', $features[0]['label']);
    }

    /**
     * A child language inherits the label of its parent language before the system language is used.
     *
     * @param array<string, string> $labels
     */
    #[DataProvider('parentLanguageLabelProvider')]
    public function testCustomFieldLabelFallsBackToTheParentLanguage(array $labels): void
    {
        $features = $this->buildCustomFieldFeatures($labels, self::CHILD_LANGUAGE_CHAIN);

        static::assertSame('Werkstoff', $features[0]['label']);
    }

    /**
     * @param array<string, string> $labels
     */
    #[DataProvider('missingLabelProvider')]
    public function testCustomFieldLabelFallsBackToTheSystemLanguage(array $labels): void
    {
        $features = $this->buildCustomFieldFeatures($labels);

        static::assertSame('Material', $features[0]['label']);
    }

    /**
     * @param array<string, string> $labels
     */
    #[DataProvider('technicalNameLabelProvider')]
    public function testCustomFieldLabelFallsBackToTheTechnicalName(array $labels): void
    {
        $features = $this->buildCustomFieldFeatures($labels);

        static::assertSame('custom_material', $features[0]['label']);
    }

    public function testSelectCustomFieldExposesTheLabelsOfTheSelectedOptions(): void
    {
        $features = $this->buildCustomFieldFeatures(
            ['de-DE' => 'Werkstoff'],
            content: ['oak', 'pine', 'unknown'],
            type: CustomFieldTypes::SELECT,
            config: [
                'options' => [
                    ['value' => 'oak', 'label' => ['en-GB' => 'Oak', 'de-DE' => 'Eiche']],
                    ['value' => 'pine', 'label' => ['en-GB' => 'Pine']],
                ],
            ]
        );

        static::assertSame(['Eiche', 'Pine', 'unknown'], $features[0]['value']['display']);
    }

    public function testSingleSelectCustomFieldExposesOneLabel(): void
    {
        $features = $this->buildCustomFieldFeatures(
            ['de-DE' => 'Werkstoff'],
            content: 'oak',
            type: CustomFieldTypes::SELECT,
            config: ['options' => [['value' => 'oak', 'label' => ['de-DE' => 'Eiche']]]]
        );

        static::assertSame(['Eiche'], $features[0]['value']['display']);
    }

    public function testEntityCustomFieldExposesTheNamesOfTheReferencedEntities(): void
    {
        $features = $this->buildCustomFieldFeatures(
            ['de-DE' => 'Werkstoff'],
            content: [self::FIRST_ENTITY_ID, self::SECOND_ENTITY_ID, Uuid::randomHex()],
            type: CustomFieldTypes::ENTITY,
            config: ['entity' => 'blog'],
            referencedEntities: [
                self::FIRST_ENTITY_ID => ['name' => 'Oak'],
                self::SECOND_ENTITY_ID => ['name' => 'Pine'],
            ]
        );

        static::assertSame(['Oak', 'Pine'], $features[0]['value']['display']);
    }

    public function testEntityCustomFieldJoinsMultipleLabelProperties(): void
    {
        $features = $this->buildCustomFieldFeatures(
            ['de-DE' => 'Werkstoff'],
            content: self::FIRST_ENTITY_ID,
            type: CustomFieldTypes::SELECT,
            config: ['entity' => 'user', 'labelProperty' => ['firstName', 'lastName']],
            referencedEntities: [self::FIRST_ENTITY_ID => ['name' => 'Max', 'lastName' => 'Mustermann']]
        );

        static::assertSame(['Max Mustermann'], $features[0]['value']['display']);
    }

    public function testEntityCustomFieldIsSkippedWhenTheEntityIsUnknown(): void
    {
        $this->definitionRegistry
            ->method('getRepository')
            ->willThrowException(new EntityRepositoryNotFoundException('lorem_ipsum'));

        $features = $this->buildCustomFieldFeatures(
            ['de-DE' => 'Werkstoff'],
            content: self::FIRST_ENTITY_ID,
            type: CustomFieldTypes::ENTITY,
            config: ['entity' => 'lorem_ipsum']
        );

        static::assertSame([], $features);
    }

    public function testPriceCustomFieldIsResolvedForTheCurrencyOfTheContext(): void
    {
        $features = $this->buildCustomFieldFeatures(
            ['de-DE' => 'Aufpreis'],
            content: [
                ['currencyId' => Defaults::CURRENCY, 'net' => 10.0, 'gross' => 11.9, 'linked' => true],
                ['currencyId' => self::CURRENCY_ID, 'net' => 20.0, 'gross' => 23.8, 'linked' => true],
            ],
            type: CustomFieldTypes::PRICE,
            context: $this->createChannelContext(currencyId: self::CURRENCY_ID)
        );

        static::assertSame(23.8, $features[0]['value']['display']);
    }

    public function testPriceCustomFieldUsesTheNetPriceForANetContext(): void
    {
        $context = $this->createChannelContext();
        $context->setTaxState(CartPrice::TAX_STATE_NET);

        $features = $this->buildCustomFieldFeatures(
            ['de-DE' => 'Aufpreis'],
            content: [['currencyId' => Defaults::CURRENCY, 'net' => 10.0, 'gross' => 11.9, 'linked' => true]],
            type: CustomFieldTypes::PRICE,
            context: $context
        );

        static::assertSame(10.0, $features[0]['value']['display']);
    }

    public function testPriceCustomFieldFallsBackToTheDefaultCurrencyTimesItsFactor(): void
    {
        $features = $this->buildCustomFieldFeatures(
            ['de-DE' => 'Aufpreis'],
            content: [['currencyId' => Defaults::CURRENCY, 'net' => 10.0, 'gross' => 11.0, 'linked' => true]],
            type: CustomFieldTypes::PRICE,
            context: $this->createChannelContext(currencyId: self::CURRENCY_ID, currencyFactor: 1.5)
        );

        static::assertSame(16.5, $features[0]['value']['display']);
    }

    public function testPriceCustomFieldIsSkippedWithoutAMatchingCurrency(): void
    {
        $features = $this->buildCustomFieldFeatures(
            ['de-DE' => 'Aufpreis'],
            content: [],
            type: CustomFieldTypes::PRICE
        );

        static::assertSame([], $features);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function parentLanguageLabelProvider(): iterable
    {
        yield 'no label for the child language' => [['en-GB' => 'Material', 'de-DE' => 'Werkstoff']];
        yield 'empty label for the child language' => [['en-GB' => 'Material', 'de-DE' => 'Werkstoff', 'de-AT' => '']];
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function missingLabelProvider(): iterable
    {
        yield 'no label for the context language' => [['en-GB' => 'Material']];
        yield 'empty label for the context language' => [['en-GB' => 'Material', 'de-DE' => '']];
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function technicalNameLabelProvider(): iterable
    {
        yield 'no label at all' => [[]];
        yield 'no label for any language of the chain' => [['fr-FR' => 'Matériau']];
        yield 'empty system language label' => [['en-GB' => '']];
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function unusableSnippetProvider(): iterable
    {
        yield 'no snippet for the custom field' => [['customFields.custom_other' => 'Sonstiges']];
        yield 'empty snippet for the custom field' => [['customFields.custom_material' => '']];
        yield 'snippet is the technical name' => [['customFields.custom_material' => 'custom_material']];
    }

    /**
     * The translator echoes the key of every snippet that is not defined in the requested locale,
     * as the real translator does.
     *
     * @param array<string, array<string, string>> $snippetsByLocale
     */
    private function useSnippets(array $snippetsByLocale): void
    {
        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters, ?string $domain, ?string $locale): string => $snippetsByLocale[$locale ?? ''][$id] ?? $id
        );

        $this->blogFeatureBuilder = new BlogFeatureBuilder(
            $this->customFieldRepository,
            $this->languageLocaleProvider,
            $this->definitionRegistry,
            $translator,
        );
    }

    /**
     * Builds the features of a line item referencing a blog with a single custom field feature.
     * The context defaults to the `de-DE` language chain; the system language is `en-GB` throughout.
     *
     * @param array<string, string> $labels
     * @param non-empty-list<string> $languageIdChain
     * @param array<string, mixed> $config
     * @param array<string, array<string, string>> $referencedEntities
     *
     * @return array<int, array{label: string, value: mixed, type: string}>
     */
    private function buildCustomFieldFeatures(
        array $labels,
        array $languageIdChain = self::LANGUAGE_CHAIN,
        mixed $content = 'wood',
        string $type = CustomFieldTypes::TEXT,
        array $config = [],
        array $referencedEntities = [],
        ?ChannelContext $context = null
    ): array {
        $this->languageLocaleProvider->method('getLocaleForLanguageId')->willReturnMap([
            [self::CHILD_LANGUAGE_ID, 'de-AT'],
            [self::LANGUAGE_ID, 'de-DE'],
            [Defaults::LANGUAGE_SYSTEM, 'en-GB'],
        ]);

        $this->definitionRegistry
            ->method('getRepository')
            ->willReturn($this->createEntityRepository($referencedEntities));

        $blogId = 'blog-id-123';
        $lineItem = new LineItem($blogId, LineItem::BLOG_LINE_ITEM_TYPE, $blogId);

        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);
        $blog->setTranslated(['customFields' => ['custom_material' => $content]]);
        $blog->setFeatureSet($this->createCustomFieldFeatureSet());

        $customField = $this->createCustomField($labels, $type, $config);

        $data = new CartDataCollection();
        $data->set('blog-' . $blogId, $blog);
        $data->set('custom-field-custom_material', $customField);

        $context ??= $this->createChannelContext(languageIdChain: $languageIdChain);

        $this->blogFeatureBuilder->prepare([$lineItem], $data, $context);
        $this->blogFeatureBuilder->add([$lineItem], $data, $context);

        return $lineItem->getPayload()['features'];
    }

    /**
     * @param non-empty-list<string> $languageIdChain
     */
    private function createChannelContext(
        array $languageIdChain = self::LANGUAGE_CHAIN,
        string $currencyId = Defaults::CURRENCY,
        float $currencyFactor = 1.0
    ): ChannelContext {
        return Generator::generateChannelContext(
            baseContext: new Context(
                new SystemSource(),
                currencyId: $currencyId,
                languageIdChain: $languageIdChain,
                currencyFactor: $currencyFactor,
            ),
            languageInfo: new LanguageInfo('Deutsch', self::LOCALE_CODE),
        );
    }


    /**
     * @param array<string, string> $labels
     * @param array<string, mixed> $config
     */
    private function createCustomField(array $labels, string $type = CustomFieldTypes::TEXT, array $config = []): CustomFieldEntity
    {
        $customField = new CustomFieldEntity();
        $customField->setId('custom-field-id');
        $customField->setName('custom_material');
        $customField->setType($type);
        $customField->setConfig(['label' => $labels] + $config);

        return $customField;
    }

    /**
     * @param array<string, array<string, string>> $entities
     *
     * @return Stub&EntityRepository<EntityCollection<Entity>>
     */
    private function createEntityRepository(array $entities): Stub&EntityRepository
    {
        $elements = [];

        foreach ($entities as $id => $values) {
            $entity = new ArrayEntity($values);
            $entity->setUniqueIdentifier($id);
            $entity->internalSetEntityData(self::REFERENCED_ENTITY_NAME, new FieldVisibility([]));

            $elements[] = $entity;
        }

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            self::REFERENCED_ENTITY_NAME,
            \count($elements),
            new EntityCollection($elements),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return $repository;
    }
}
