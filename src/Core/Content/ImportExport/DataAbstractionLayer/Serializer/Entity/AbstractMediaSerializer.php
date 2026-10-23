<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;

abstract class AbstractMediaSerializer extends EntitySerializer
{
    abstract public function persistMedia(EntityWrittenEvent $event): void;
}
