<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

interface StorageAware
{
    public function getStorageName(): string;
}
