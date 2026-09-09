<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation\Error;

use Contena\Core\Framework\Struct\Collection;

/**
 * @internal only for use by the app-system
 *
 * @extends Collection<Error>
 */
class ErrorCollection extends Collection
{
    /**
     * @param Error $error
     */
    public function add($error): void
    {
        $this->set($error->getErrorCode(), $error);
    }

    public function addErrors(ErrorCollection $errors): void
    {
        foreach ($errors as $error) {
            $this->set($error->getErrorCode(), $error);
        }
    }

    protected function getExpectedClass(): ?string
    {
        return Error::class;
    }
}
