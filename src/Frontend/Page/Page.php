<?php declare(strict_types=1);

namespace Contena\Frontend\Page;

use Contena\Core\Framework\Struct\Struct;

class Page extends Struct
{
    protected ?MetaInformation $metaInformation = null;

    public function getMetaInformation(): ?MetaInformation
    {
        return $this->metaInformation;
    }

    public function setMetaInformation(MetaInformation $metaInformation): void
    {
        $this->metaInformation = $metaInformation;
    }
}
