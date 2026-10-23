<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cart;

use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\LineItem\CartDataCollection;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Content\Blog\Aggregate\BlogFeatureSet\BlogFeatureSetDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Exception\EntityRepositoryNotFoundException;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\CustomField\CustomFieldCollection;
use Contena\Core\System\CustomField\CustomFieldEntity;
use Contena\Core\System\CustomField\CustomFieldTypes;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\Translation\TranslatorInterface;

class BlogFeatureBuilder
{
    private const CUSTOM_FIELD_KEY_PREFIX = 'custom-field-';

    private const ENTITY_LABEL_KEY_PREFIX = 'custom-field-entity-';

    private const CUSTOM_FIELD_SNIPPET_PREFIX = 'customFields.';

    /**
     * @internal
     *
     * @param EntityRepository<CustomFieldCollection> $customFieldRepository
     */
    public function __construct(
        private readonly EntityRepository $customFieldRepository,
        private readonly LanguageLocaleCodeProvider $languageLocaleProvider,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param iterable<LineItem> $lineItems
     */
    public function prepare(iterable $lineItems, CartDataCollection $data, ChannelContext $context): void
    {
        $blogs = [];

        foreach ($lineItems as $lineItem) {
            $blogId = $lineItem->getReferencedId();
            if ($blogId === null) {
                continue;
            }

            $blog = $data->get($this->getDataKey($blogId));
            if ($blog instanceof ChannelBlogEntity) {
                $blogs[$blogId] = $blog;
            }
        }

        $this->loadCustomFields($blogs, $data, $context);
        $this->loadCustomFieldEntityLabels($blogs, $data, $context);
    }

    /**
     * @param iterable<LineItem> $lineItems
     *
     * @throws CartException
     */
    public function add(iterable $lineItems, CartDataCollection $data, ChannelContext $context): void
    {
        foreach ($lineItems as $lineItem) {
            $blogId = $lineItem->getReferencedId();
            if ($blogId === null) {
                continue;
            }

            $blog = $data->get($this->getDataKey($blogId));
            if (!$blog instanceof ChannelBlogEntity) {
                continue;
            }

            $lineItem->replacePayload([
                'features' => $this->buildFeatures($data, $lineItem, $blog, $context),
            ]);
        }
    }

    /**
     * @throws CartException
     *
     * @return array<int, array{label: string, value: mixed, type: string}>
     */
    private function buildFeatures(CartDataCollection $data, LineItem $lineItem, ChannelBlogEntity $blog, ChannelContext $context): array
    {
        $sortedFeatures = $blog->getFeatureSet()?->getFeatures();
        if ($sortedFeatures === null) {
            return [];
        }

        usort($sortedFeatures, static fn (array $a, array $b) => $a['position'] <=> $b['position']);

        $features = [];
        foreach ($sortedFeatures as $feature) {
            if ($feature['type'] === BlogFeatureSetDefinition::TYPE_BLOG_ATTRIBUTE) {
                $features[] = $this->getAttribute($feature['name'], $blog);

                continue;
            }

            if ($feature['type'] === BlogFeatureSetDefinition::TYPE_BLOG_PROPERTY) {
                $features[] = $this->getProperty($feature['id'], $blog);

                continue;
            }

            if ($feature['type'] === BlogFeatureSetDefinition::TYPE_BLOG_CUSTOM_FIELD) {
                $features[] = $this->getCustomField($feature['name'], $data, $blog, $context);

                continue;
            }

            if ($feature['type'] === BlogFeatureSetDefinition::TYPE_BLOG_REFERENCE_PRICE) {
                $features[] = $this->getReferencePrice($lineItem, $blog);
            }
        }

        return array_filter($features);
    }

    /**
     * @param array<string, ChannelBlogEntity> $blogs
     */
    private function loadCustomFields(array $blogs, CartDataCollection $data, ChannelContext $context): void
    {
        $required = [];

        foreach ($blogs as $blog) {
            foreach (array_keys($this->getCustomFieldValues($blog)) as $name) {
                if (!$this->isRequiredCustomField($name, $blog)) {
                    continue;
                }

                $key = self::CUSTOM_FIELD_KEY_PREFIX . $name;

                if ($data->has($key)) {
                    // Custom field already loaded
                    continue;
                }

                $required[] = $name;
            }
        }

        if ($required === []) {
            return;
        }

        $criteria = (new Criteria())->addFilter(new EqualsAnyFilter('name', $required));

        $customFields = $this->customFieldRepository->search($criteria, $context->getContext())->getEntities();
        foreach ($customFields as $field) {
            $key = self::CUSTOM_FIELD_KEY_PREFIX . $field->getName();
            $data->set($key, $field);
        }
    }

    /**
     * Entity select custom fields only store ids, so the label of every referenced entity is resolved
     * once per custom field and kept as an id to label map next to the custom field itself.
     *
     * @param array<string, ChannelBlogEntity> $blogs
     */
    private function loadCustomFieldEntityLabels(array $blogs, CartDataCollection $data, ChannelContext $context): void
    {
        $customFields = [];
        $requiredIds = [];

        foreach ($blogs as $blog) {
            foreach ($this->getCustomFieldValues($blog) as $name => $content) {
                $customField = $data->get(self::CUSTOM_FIELD_KEY_PREFIX . $name);

                if (!$customField instanceof CustomFieldEntity || $this->getReferencedEntityName($customField) === null) {
                    continue;
                }

                $customFields[$name] = $customField;

                foreach ($this->toStringList($content) as $id) {
                    $requiredIds[$name][$id] = $id;
                }
            }
        }

        foreach ($requiredIds as $name => $ids) {
            $key = self::ENTITY_LABEL_KEY_PREFIX . $name;

            $known = $data->get($key);
            $known = \is_array($known) ? $known : [];

            $missing = array_values(array_diff_key($ids, $known));
            if ($missing === []) {
                continue;
            }

            $data->set($key, $known + $this->fetchEntityLabels($customFields[$name], $missing, $context));
        }
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, ?string>
     */
    private function fetchEntityLabels(CustomFieldEntity $customField, array $ids, ChannelContext $context): array
    {
        $labels = array_fill_keys($ids, null);

        $entityName = $this->getReferencedEntityName($customField);
        if ($entityName === null) {
            return $labels;
        }

        $ids = array_values(array_filter($ids, Uuid::isValid(...)));
        if ($ids === []) {
            return $labels;
        }

        try {
            $repository = $this->definitionRegistry->getRepository($entityName);
        } catch (EntityRepositoryNotFoundException) {
            return $labels;
        }

        $properties = $this->getLabelProperties($customField);

        foreach ($repository->search(new Criteria($ids), $context->getContext())->getEntities() as $entity) {
            $labels[$entity->getUniqueIdentifier()] = $this->getEntityLabel($entity, $properties);
        }

        return $labels;
    }

    /**
     * Checks whether a custom field name is part of the provided blog's feature set
     */
    private function isRequiredCustomField(string $name, ChannelBlogEntity $blog): bool
    {
        $features = $blog->getFeatureSet()?->getFeatures();
        if ($features === null) {
            return false;
        }

        foreach ($features as $feature) {
            if ($feature['type'] !== BlogFeatureSetDefinition::TYPE_BLOG_CUSTOM_FIELD) {
                continue;
            }

            if ($feature['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCustomFieldValues(ChannelBlogEntity $blog): array
    {
        $customFields = $blog->getTranslation('customFields');
        if (!\is_array($customFields)) {
            return [];
        }

        $values = [];
        foreach ($customFields as $name => $content) {
            $values[(string) $name] = $content;
        }

        return $values;
    }

    /**
     * @return array{label: string, value: mixed, type: string}
     */
    private function getAttribute(string $name, ChannelBlogEntity $blog): array
    {
        $translated = $blog->getTranslated();
        $value = $blog->get($name);

        if (\array_key_exists($name, $translated)) {
            $value = $translated[$name];
        }

        if ($value instanceof \DateTimeInterface) {
            $value = $value->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        }

        return [
            'label' => $name,
            'value' => $value,
            'type' => BlogFeatureSetDefinition::TYPE_BLOG_ATTRIBUTE,
        ];
    }

    /**
     * @return ?array{label: string, value: mixed, type: string}
     */
    private function getProperty(string $id, ChannelBlogEntity $blog): ?array
    {
        $properties = $blog->getProperties();
        if ($properties === null) {
            return null;
        }

        $group = $properties->getGroups()->get($id);
        if ($group === null) {
            return null;
        }

        $properties = $properties->fmap(
            static function (PropertyGroupOptionEntity $property) use ($id) {
                if ($property->getGroupId() !== $id) {
                    return null;
                }

                return [
                    'id' => $property->getId(),
                    'name' => $property->getTranslation('name'),
                    'mediaId' => $property->getMediaId(),
                    'colorHexCode' => $property->getColorHexCode(),
                ];
            }
        );

        if ($properties === []) {
            return null;
        }

        $label = $group->getTranslation('name');
        if (!\is_string($label)) {
            return null;
        }

        return [
            'label' => $label,
            'value' => $properties,
            'type' => BlogFeatureSetDefinition::TYPE_BLOG_PROPERTY,
        ];
    }

    /**
     * @throws CartException
     *
     * @return array{label: string, value: array{id: string, type: string, content: mixed, display?: list<string>|float}, type: string}|null
     */
    private function getCustomField(string $name, CartDataCollection $data, ChannelBlogEntity $blog, ChannelContext $context): ?array
    {
        $fieldKey = self::CUSTOM_FIELD_KEY_PREFIX . $name;
        $translation = $this->getCustomFieldValues($blog);

        if (!\array_key_exists($name, $translation)) {
            return null;
        }

        if (!$data->has($fieldKey)) {
            return null;
        }

        $customField = $data->get($fieldKey);
        if (!$customField instanceof CustomFieldEntity) {
            throw CartException::wrongCartDataType($fieldKey, CustomFieldEntity::class);
        }

        $display = $this->getDisplayValue($customField, $translation[$name], $data, $context);
        if ($display === null && $this->needsDisplayValue($customField)) {
            return null;
        }

        $value = [
            'id' => $customField->getId(),
            'type' => $customField->getType(),
            'content' => $translation[$name],
        ];

        if ($display !== null) {
            $value['display'] = $display;
        }

        return [
            'label' => $this->getCustomFieldLabel($customField, $context),
            'value' => $value,
            'type' => BlogFeatureSetDefinition::TYPE_BLOG_CUSTOM_FIELD,
        ];
    }

    /**
     * @return array{label: string, value: array{price: float, purchaseUnit: float, referenceUnit: float, unitName: ?string}, type: string}|null
     */
    private function getReferencePrice(LineItem $lineItem, ChannelBlogEntity $blog): ?array
    {
        $referencePrice = $lineItem->getPrice()?->getReferencePrice();
        if ($referencePrice === null) {
            return null;
        }

        $unit = $blog->getUnit();
        if ($unit === null) {
            return null;
        }

        return [
            'label' => BlogFeatureSetDefinition::TYPE_BLOG_REFERENCE_PRICE,
            'value' => [
                'price' => $referencePrice->getPrice(),
                'purchaseUnit' => $referencePrice->getPurchaseUnit(),
                'referenceUnit' => $referencePrice->getReferenceUnit(),
                'unitName' => $unit->getTranslation('name'),
            ],
            'type' => BlogFeatureSetDefinition::TYPE_BLOG_REFERENCE_PRICE,
        ];
    }

    /**
     * Select, entity select and price custom fields store raw values that carry no meaning on their own.
     * They are resolved into a rendered representation next to the untouched content of the custom field.
     *
     * @return list<string>|float|null
     */
    private function getDisplayValue(CustomFieldEntity $customField, mixed $content, CartDataCollection $data, ChannelContext $context): array|float|null
    {
        if ($customField->getType() === CustomFieldTypes::PRICE) {
            return $this->getPriceValue($content, $context);
        }

        if ($this->getReferencedEntityName($customField) !== null) {
            $values = $this->getEntityValues($customField, $content, $data);

            return $values === [] ? null : $values;
        }

        if ($customField->getType() === CustomFieldTypes::SELECT) {
            $values = $this->getSelectValues($customField, $content, $context);

            return $values === [] ? null : $values;
        }

        return null;
    }

    private function needsDisplayValue(CustomFieldEntity $customField): bool
    {
        return \in_array(
            $customField->getType(),
            [CustomFieldTypes::SELECT, CustomFieldTypes::ENTITY, CustomFieldTypes::PRICE],
            true
        );
    }

    /**
     * @return list<string>
     */
    private function getSelectValues(CustomFieldEntity $customField, mixed $content, ChannelContext $context): array
    {
        $options = $customField->getConfig()['options'] ?? null;

        $labels = [];
        foreach (\is_array($options) ? $options : [] as $option) {
            if (!\is_array($option) || !isset($option['value']) || !\is_scalar($option['value'])) {
                continue;
            }

            $label = $option['label'] ?? null;
            $labels[(string) $option['value']] = \is_array($label) ? $label : [];
        }

        $values = [];
        foreach ($this->toStringList($content) as $value) {
            $values[] = $this->getTranslatedLabel($labels[$value] ?? [], $context) ?? $value;
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private function getEntityValues(CustomFieldEntity $customField, mixed $content, CartDataCollection $data): array
    {
        $labels = $data->get(self::ENTITY_LABEL_KEY_PREFIX . $customField->getName());
        if (!\is_array($labels)) {
            return [];
        }

        $values = [];
        foreach ($this->toStringList($content) as $id) {
            $label = $labels[$id] ?? null;

            if (\is_string($label) && $label !== '') {
                $values[] = $label;
            }
        }

        return $values;
    }

    private function getPriceValue(mixed $content, ChannelContext $context): ?float
    {
        if (!\is_array($content)) {
            return null;
        }

        $prices = new PriceCollection();
        foreach ($content as $price) {
            if (!\is_array($price) || !isset($price['currencyId'], $price['net'], $price['gross'])) {
                continue;
            }

            $prices->add(new Price(
                (string) $price['currencyId'],
                (float) $price['net'],
                (float) $price['gross'],
                (bool) ($price['linked'] ?? false)
            ));
        }

        $price = $prices->getCurrencyPrice($context->getCurrencyId());
        if ($price === null) {
            return null;
        }

        $value = $context->getTaxState() === CartPrice::TAX_STATE_GROSS ? $price->getGross() : $price->getNet();

        if ($price->getCurrencyId() !== $context->getCurrencyId()) {
            $value *= $context->getContext()->getCurrencyFactor();
        }

        return $value;
    }

    private function getReferencedEntityName(CustomFieldEntity $customField): ?string
    {
        if (!\in_array($customField->getType(), [CustomFieldTypes::SELECT, CustomFieldTypes::ENTITY], true)) {
            return null;
        }

        $entityName = $customField->getConfig()['entity'] ?? null;

        return \is_string($entityName) && $entityName !== '' ? $entityName : null;
    }

    /**
     * @return list<string>
     */
    private function getLabelProperties(CustomFieldEntity $customField): array
    {
        $labelProperty = $customField->getConfig()['labelProperty'] ?? null;

        if (\is_string($labelProperty) && $labelProperty !== '') {
            return [$labelProperty];
        }

        if (!\is_array($labelProperty)) {
            return ['name'];
        }

        $properties = array_values(array_filter($labelProperty, \is_string(...)));

        return $properties === [] ? ['name'] : $properties;
    }

    /**
     * @param list<string> $properties
     */
    private function getEntityLabel(Entity $entity, array $properties): ?string
    {
        $parts = [];

        foreach ($properties as $property) {
            $value = $entity->getTranslation($property);

            if (!\is_string($value) && $entity->has($property)) {
                $value = $entity->get($property);
            }

            if (\is_string($value) && $value !== '') {
                $parts[] = $value;
            }
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * @return list<string>
     */
    private function toStringList(mixed $content): array
    {
        if (\is_scalar($content)) {
            return [(string) $content];
        }

        if (!\is_array($content)) {
            return [];
        }

        return array_values(array_map(strval(...), array_filter($content, \is_scalar(...))));
    }

    /**
     * The context locale is passed explicitly, as only Frontend requests set the translator locale.
     * A snippet equal to its key or to the field name is unset: the translator echoes the key, and the
     * snippet is seeded with the field name when the field was created without a label for that language.
     * Config labels are walked along the language chain, as the DAL resolves translated fields.
     */
    private function getCustomFieldLabel(CustomFieldEntity $customField, ChannelContext $context): string
    {
        $snippetKey = self::CUSTOM_FIELD_SNIPPET_PREFIX . $customField->getName();
        $snippet = $this->translator->trans($snippetKey, [], null, $context->getLanguageInfo()->localeCode);

        if ($snippet !== '' && $snippet !== $snippetKey && $snippet !== $customField->getName()) {
            return $snippet;
        }

        $labels = $customField->getConfig()['label'] ?? null;

        return $this->getTranslatedLabel(\is_array($labels) ? $labels : [], $context) ?? $customField->getName();
    }

    /**
     * @param array<string|int, mixed> $labels
     */
    private function getTranslatedLabel(array $labels, ChannelContext $context): ?string
    {
        foreach ($context->getLanguageIdChain() as $languageId) {
            $localeCode = $this->languageLocaleProvider->getLocaleForLanguageId($languageId);
            $label = $labels[$localeCode] ?? null;

            if (\is_string($label) && $label !== '') {
                return $label;
            }
        }

        return null;
    }

    private function getDataKey(string $id): string
    {
        return 'blog-' . $id;
    }
}
