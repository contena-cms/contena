<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation;

use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;

class MailTemplateTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected ?string $tenantId = null;

    protected string $mailTemplateId;

    protected ?string $senderName = null;

    protected ?string $description = null;

    protected ?string $subject = null;

    protected ?string $contentHtml = null;

    protected ?string $contentPlain = null;

    protected ?MailTemplateEntity $mailTemplate = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getMailTemplateId(): string
    {
        return $this->mailTemplateId;
    }

    public function setMailTemplateId(string $mailTemplateId): void
    {
        $this->mailTemplateId = $mailTemplateId;
    }

    public function getSenderName(): ?string
    {
        return $this->senderName;
    }

    public function setSenderName(?string $senderName): void
    {
        $this->senderName = $senderName;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): void
    {
        $this->subject = $subject;
    }

    public function getContentHtml(): ?string
    {
        return $this->contentHtml;
    }

    public function setContentHtml(?string $contentHtml): void
    {
        $this->contentHtml = $contentHtml;
    }

    public function getContentPlain(): ?string
    {
        return $this->contentPlain;
    }

    public function setContentPlain(?string $contentPlain): void
    {
        $this->contentPlain = $contentPlain;
    }

    public function getMailTemplate(): ?MailTemplateEntity
    {
        return $this->mailTemplate;
    }

    public function setMailTemplate(MailTemplateEntity $mailTemplate): void
    {
        $this->mailTemplate = $mailTemplate;
    }
}
