<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\AppTranslation;

use Contena\Core\Framework\App\AppDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Since;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
class AppTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'app_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AppTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AppTranslationCollection::class;
    }

    public function since(): ?string
    {
        return '6.3.1.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return AppDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('label', 'label')->addFlags(new Required()),
            new LongTextField('description', 'description'),
            new LongTextField('privacy_policy_extensions', 'privacyPolicyExtensions'),
            new CustomFields()->addFlags(new Since('6.4.1.0')),
        ]);
    }
}
