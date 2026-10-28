<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Struct;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<TranslationFile>
 */
#[Package('discovery')]
class TranslationFileCollection extends Collection
{
    public function add($element): void
    {
        $this->validateType($element);

        $this->set($element->getFullPath(), $element);
    }

    protected function getExpectedClass(): string
    {
        return TranslationFile::class;
    }
}
