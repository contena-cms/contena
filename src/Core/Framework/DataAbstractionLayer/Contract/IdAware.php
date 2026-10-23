<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Contract;

use Contena\Core\Framework\Log\Package;

interface IdAware
{
    public function getId(): string;
}
