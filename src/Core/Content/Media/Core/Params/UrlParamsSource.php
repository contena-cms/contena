<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Core\Params;

use Contena\Core\Framework\Log\Package;

#[Package('discovery')]
enum UrlParamsSource
{
    case MEDIA;
    case THUMBNAIL;
}
