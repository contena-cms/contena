<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Filesystem\Adapter;

use League\Flysystem\FilesystemAdapter;

interface AdapterFactoryInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function create(array $config): FilesystemAdapter;

    public function getType(): string;
}
