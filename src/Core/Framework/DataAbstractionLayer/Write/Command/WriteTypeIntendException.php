<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Write\Command;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Symfony\Component\HttpFoundation\Response;

class WriteTypeIntendException extends DataAbstractionLayerException
{
    public const string WRITE_TYPE_INTEND_ERROR = 'FRAMEWORK__WRITE_TYPE_INTEND_ERROR';

    public function __construct(
        EntityDefinition $definition,
        string $expectedClass,
        string $actualClass
    ) {
        $hint = match ([$expectedClass, $actualClass]) {
            [UpdateCommand::class, InsertCommand::class] => 'Hint: Use POST method to create new entities.',
            default => '',
        };

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::WRITE_TYPE_INTEND_ERROR,
            'Expected command for "{{ definition }}" to be "{{ expectedClass }}". (Got: {{ actualClass }})' . $hint,
            ['definition' => $definition->getEntityName(), 'expectedClass' => $expectedClass, 'actualClass' => $actualClass]
        );
    }
}
