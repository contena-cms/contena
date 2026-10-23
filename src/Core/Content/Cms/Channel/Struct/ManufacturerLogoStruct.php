<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Channel\Struct;

use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerEntity;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class ManufacturerLogoStruct extends ImageStruct
{
    protected ?BlogManufacturerEntity $manufacturer = null;

    public function getManufacturer(): ?BlogManufacturerEntity
    {
        return $this->manufacturer;
    }

    public function setManufacturer(?BlogManufacturerEntity $manufacturer): void
    {
        $this->manufacturer = $manufacturer;
    }

    public function getApiAlias(): string
    {
        return 'cms_manufacturer_logo';
    }
}
