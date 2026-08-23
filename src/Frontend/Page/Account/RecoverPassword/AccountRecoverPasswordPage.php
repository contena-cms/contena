<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\RecoverPassword;

use Contena\Frontend\Page\Page;

class AccountRecoverPasswordPage extends Page
{
    protected ?string $hash = null;

    protected bool $hashExpired;

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(?string $hash): void
    {
        $this->hash = $hash;
    }

    public function isHashExpired(): bool
    {
        return $this->hashExpired;
    }

    public function setHashExpired(bool $hashExpired): void
    {
        $this->hashExpired = $hashExpired;
    }
}
