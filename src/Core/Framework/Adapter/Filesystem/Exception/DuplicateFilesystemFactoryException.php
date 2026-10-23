<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Filesystem\Exception;

use Contena\Core\Framework\Adapter\AdapterException;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class DuplicateFilesystemFactoryException extends AdapterException
{
    public function __construct(string $type)
    {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'FRAMEWORK__DUPLICATE_FILESYSTEM_FACTORY',
            'The type of factory "{{ type }}" must be unique.',
            ['type' => $type]
        );
    }
}
