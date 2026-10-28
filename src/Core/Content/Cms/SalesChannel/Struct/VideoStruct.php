<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\SalesChannel\Struct;

use Contena\Core\Framework\Log\Package;

#[Package('discovery')]
class VideoStruct extends ImageStruct
{
    public function getApiAlias(): string
    {
        return 'cms_video';
    }
}
