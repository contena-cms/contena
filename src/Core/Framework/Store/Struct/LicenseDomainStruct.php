<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LicenseDomainStruct extends Struct
{
    protected string $domain;

    protected bool $verified = false;

    protected string $edition = 'Community Edition';

    protected bool $active = false;

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function getEdition(): string
    {
        return $this->edition;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getApiAlias(): string
    {
        return 'store_license_domain';
    }
}
