<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class UnsupportedCommandTypeException extends HttpException
{
    public function __construct(WriteCommand $command)
    {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'FRAMEWORK__UNSUPPORTED_COMMAND_TYPE_EXCEPTION',
            'Command of class {{ command }} is not supported by {{ definition }}',
            ['command' => $command::class, 'definition' => $command->getEntityName()]
        );
    }
}
