<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\MediaType;

class ImageType extends MediaType
{
    final public const string ANIMATED = 'animated';
    final public const string TRANSPARENT = 'transparent';
    final public const string VECTOR_GRAPHIC = 'vectorGraphic';
    final public const string ICON = 'image/x-icon';

    protected string $name = 'IMAGE';
}
