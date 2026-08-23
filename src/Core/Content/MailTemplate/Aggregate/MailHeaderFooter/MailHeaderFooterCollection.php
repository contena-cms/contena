<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooter;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MailHeaderFooterEntity>
 */
class MailHeaderFooterCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'mail_template_header_footer_collection';
    }

    protected function getExpectedClass(): string
    {
        return MailHeaderFooterEntity::class;
    }
}
