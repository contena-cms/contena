<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MailTemplateEntity>
 */
class MailTemplateCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'mail_template_collection';
    }

    protected function getExpectedClass(): string
    {
        return MailTemplateEntity::class;
    }
}
