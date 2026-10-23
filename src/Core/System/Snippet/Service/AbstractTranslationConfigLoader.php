<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Service;

use Contena\Core\System\Snippet\Struct\TranslationConfig;

abstract class AbstractTranslationConfigLoader
{
    abstract public function getDecorated(): AbstractTranslationConfigLoader;

    abstract public function load(): TranslationConfig;
}
