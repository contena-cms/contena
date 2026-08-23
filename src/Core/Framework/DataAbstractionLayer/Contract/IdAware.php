<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Contract;

interface IdAware
{
    public function getId(): string;
}
