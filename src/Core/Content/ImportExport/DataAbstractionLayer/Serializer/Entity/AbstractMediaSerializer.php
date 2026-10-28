<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
abstract class AbstractMediaSerializer extends EntitySerializer
{
    abstract public function persistMedia(EntityWrittenEvent $event): void;
}
