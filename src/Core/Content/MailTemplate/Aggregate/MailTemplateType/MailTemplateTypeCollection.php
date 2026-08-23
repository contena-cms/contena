<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MailTemplateTypeEntity>
 */
class MailTemplateTypeCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'mail_template_type_collection';
    }

    protected function getExpectedClass(): string
    {
        return MailTemplateTypeEntity::class;
    }
}
