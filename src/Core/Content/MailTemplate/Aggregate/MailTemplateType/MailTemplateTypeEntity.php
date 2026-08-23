<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType;

use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation\MailTemplateTypeTranslationCollection;
use Contena\Core\Content\MailTemplate\MailTemplateCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class MailTemplateTypeEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $name;

    protected string $technicalName;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $availableEntities = null;

    protected ?MailTemplateTypeTranslationCollection $translations = null;

    protected ?MailTemplateCollection $mailTemplates = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getTranslations(): ?MailTemplateTypeTranslationCollection
    {
        return $this->translations;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAvailableEntities(): ?array
    {
        return $this->availableEntities;
    }

    /**
     * @param array<string, mixed>|null $availableEntities
     */
    public function setAvailableEntities(?array $availableEntities): void
    {
        $this->availableEntities = $availableEntities;
    }

    public function setTranslations(MailTemplateTypeTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getMailTemplates(): ?MailTemplateCollection
    {
        return $this->mailTemplates;
    }

    public function setMailTemplates(MailTemplateCollection $mailTemplates): void
    {
        $this->mailTemplates = $mailTemplates;
    }
}
