<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Filesystem\Adapter;

use League\Flysystem\FilesystemAdapter;
use Contena\Core\Framework\Log\Package;

interface AdapterFactoryInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function create(array $config): FilesystemAdapter;

    public function getType(): string;
}
