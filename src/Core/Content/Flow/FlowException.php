<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow;

use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class FlowException extends HttpException
{
    final public const string MISSING_REQUIRED_SEQUENCE_FIELD = 'CONTENT__FLOW_MISSING_REQUIRED_SEQUENCE_FIELD';
    final public const string METHOD_NOT_COMPATIBLE = 'CONTENT__FLOW_METHOD_NOT_COMPATIBLE';
    final public const string INVALID_SERIALIZER_FIELD = 'CONTENT__FLOW_INVALID_SERIALIZER_FIELD';

    public static function missingRequiredSequenceField(string $field): self
    {
        return new self(Response::HTTP_BAD_REQUEST, self::MISSING_REQUIRED_SEQUENCE_FIELD, 'Required sequence field "{{ name }}" is missing.', ['name' => $field]);
    }

    public static function methodNotCompatible(string $method, string $class): self
    {
        return new self(Response::HTTP_BAD_REQUEST, self::METHOD_NOT_COMPATIBLE, 'Method {{ method }} is not compatible for {{ class }} class', ['method' => $method, 'class' => $class]);
    }

    public static function invalidSerializerField(string $serializer, string $field): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_SERIALIZER_FIELD,
            'Serializer {{ serializer }} does not support field {{ field }}.',
            ['serializer' => $serializer, 'field' => $field]
        );
    }
}
