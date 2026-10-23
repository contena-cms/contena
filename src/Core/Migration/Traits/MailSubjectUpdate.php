<?php declare(strict_types=1);

namespace Contena\Core\Migration\Traits;

use Contena\Core\Framework\Log\Package;

class MailSubjectUpdate
{
    public function __construct(
        protected string $type,
        protected ?string $enSubject = null,
        protected ?string $deSubject = null
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDeSubject(): ?string
    {
        return $this->deSubject;
    }

    public function getEnSubject(): ?string
    {
        return $this->enSubject;
    }
}
