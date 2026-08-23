<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MailHeaderFooterTranslationEntity>
 */
class MailHeaderFooterTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (MailHeaderFooterTranslationEntity $mailTemplateTranslation) => $mailTemplateTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (MailHeaderFooterTranslationEntity $mailTemplateTranslation) => $mailTemplateTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'mail_template_header_footer_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return MailHeaderFooterTranslationEntity::class;
    }
}
