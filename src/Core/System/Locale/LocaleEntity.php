<?php declare(strict_types=1);

namespace Contena\Core\System\Locale;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationCollection;
use Contena\Core\System\User\UserCollection;

class LocaleEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $code;

    protected ?string $name = null;

    protected ?string $territory = null;

    protected ?LocaleTranslationCollection $translations = null;

    protected ?UserCollection $users = null;

    protected ?LanguageCollection $languages = null;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getTerritory(): ?string
    {
        return $this->territory;
    }

    public function setTerritory(?string $territory): void
    {
        $this->territory = $territory;
    }

    public function getTranslations(): ?LocaleTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(LocaleTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getUsers(): ?UserCollection
    {
        return $this->users;
    }

    public function setUsers(UserCollection $users): void
    {
        $this->users = $users;
    }

    public function getLanguages(): ?LanguageCollection
    {
        return $this->languages;
    }

    public function setLanguages(LanguageCollection $languages): void
    {
        $this->languages = $languages;
    }
}
