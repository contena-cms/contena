<?php declare(strict_types=1);

namespace Contena\Core\Content\Media;

use Contena\Core\Framework\Log\Package;

#[Package('discovery')]
interface MediaUrlPlaceholderHandlerInterface
{
    public function replace(string $content): string;
}
