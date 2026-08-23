<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooter;

use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation\MailHeaderFooterTranslationCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class MailHeaderFooterEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected ?string $name = null;

    protected bool $systemDefault;

    protected ?string $description = null;

    protected ?string $headerHtml = null;

    protected ?string $headerPlain = null;

    protected ?string $footerHtml = null;

    protected ?string $footerPlain = null;

    protected ?MailHeaderFooterTranslationCollection $translations = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getHeaderHtml(): ?string
    {
        return $this->headerHtml;
    }

    public function setHeaderHtml(?string $headerHtml): void
    {
        $this->headerHtml = $headerHtml;
    }

    public function getHeaderPlain(): ?string
    {
        return $this->headerPlain;
    }

    public function setHeaderPlain(?string $headerPlain): void
    {
        $this->headerPlain = $headerPlain;
    }

    public function getFooterHtml(): ?string
    {
        return $this->footerHtml;
    }

    public function setFooterHtml(?string $footerHtml): void
    {
        $this->footerHtml = $footerHtml;
    }

    public function getFooterPlain(): ?string
    {
        return $this->footerPlain;
    }

    public function setFooterPlain(?string $footerPlain): void
    {
        $this->footerPlain = $footerPlain;
    }

    public function getTranslations(): ?MailHeaderFooterTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(MailHeaderFooterTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getSystemDefault(): bool
    {
        return $this->systemDefault;
    }

    public function setSystemDefault(bool $systemDefault): void
    {
        $this->systemDefault = $systemDefault;
    }
}
