<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\Log\Package;

#[Package('framework')]
interface StorageAware
{
    public function getStorageName(): string;
}
