<?php declare(strict_types=1);

namespace Contena\Core\Framework\JWT\Struct;

use Contena\Core\Framework\JWT\JWTException;
use Contena\Core\Framework\Struct\Collection;
use Contena\Core\Framework\Validation\ValidatorFactory;

/**
 * @phpstan-import-type JSONWebKey from JWKStruct
 *
 * @extends Collection<JWKStruct>
 */
class JWKCollection extends Collection
{
    /**
     * @param array{keys: array<int, JSONWebKey>} $data
     */
    public static function fromArray(array $data): self
    {
        $elements = ['elements' => \array_map(static function (array $element): JWKStruct {
            $dto = ValidatorFactory::create($element, JWKStruct::class);
            if (!$dto instanceof JWKStruct) {
                throw JWTException::invalidType(JWKStruct::class, $dto::class);
            }

            return $dto;
        }, $data['keys'])];

        return new self()->assign($elements);
    }
}
