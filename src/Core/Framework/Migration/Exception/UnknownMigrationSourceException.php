<?php declare(strict_types=1);

namespace Contena\Core\Framework\Migration\Exception;

use Contena\Core\Framework\Migration\MigrationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class UnknownMigrationSourceException extends MigrationException
{
    public function __construct(string $name)
    {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FRAMEWORK_MIGRATION_INVALID_MIGRATION_SOURCE,
            'No source registered for "{{ name }}"',
            ['name' => $name]
        );
    }
}
