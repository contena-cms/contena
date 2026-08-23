<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Aggregate\MailTemplateMedia;

use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class MailTemplateMediaEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $mailTemplateId;

    protected ?string $languageId = null;

    protected string $mediaId;

    protected ?MediaEntity $media = null;

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

    public function getLanguageId(): ?string
    {
        return $this->languageId;
    }

    public function setLanguageId(?string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getMediaId(): string
    {
        return $this->mediaId;
    }

    public function setMediaId(string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(MediaEntity $media): void
    {
        $this->media = $media;
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
