<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MailTemplateEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
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
