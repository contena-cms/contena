<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field\Flag;

/**
 * @internal
 *
 * Flag to ignore a field via the OpenApiDefinitionSchemaBuilder
 * If this flag is set, make sure you have a custom OpenApiSchema json for that field/entity
 *
 * @codeCoverageIgnore
 */
class IgnoreInOpenapiSchema extends Flag
{
    public function parse(): \Generator
    {
        yield 'ignore_in_openapi_schema' => true;
    }
}
