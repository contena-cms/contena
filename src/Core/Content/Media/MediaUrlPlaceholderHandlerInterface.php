<?php declare(strict_types=1);

namespace Contena\Core\Content\Media;

interface MediaUrlPlaceholderHandlerInterface
{
    public function replace(string $content): string;
}
