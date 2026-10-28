<?php

declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Event;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AssetUploadEvent
{
    /**
     * @internal
     *
     * @param list<string> $filesToUpload
     * @param list<string> $filesToDelete
     */
    public function __construct(
        public array $filesToUpload,
        public array $filesToDelete,
    ) {
    }
}
