<?php declare(strict_types=1);

namespace Contena\Core\Framework\Update\Event;

use Contena\Core\Framework\Context;

class UpdatePreFinishEvent extends UpdateEvent
{
    public function __construct(
        Context $context,
        private readonly string $oldVersion,
        private readonly string $newVersion
    ) {
        parent::__construct($context);
    }

    public function getOldVersion(): string
    {
        return $this->oldVersion;
    }

    public function getNewVersion(): string
    {
        return $this->newVersion;
    }
}
