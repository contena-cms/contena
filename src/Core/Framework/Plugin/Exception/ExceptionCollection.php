<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<ContenaHttpException>
 *
 * @codeCoverageIgnore
 */
class ExceptionCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'plugin_exception_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return ContenaHttpException::class;
    }
}
