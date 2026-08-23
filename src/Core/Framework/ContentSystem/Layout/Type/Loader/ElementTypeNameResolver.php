<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Type\Loader;

use Contena\Core\Framework\ContentSystem\ContentSystemException;

use function Symfony\Component\String\u;

/**
 * @internal
 */
class ElementTypeNameResolver
{
    private const SEGMENT_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    public function resolve(string $relativePath, string $prefix): string
    {
        $path = preg_replace('/\.(yaml|yml)$/', '', $relativePath);
        if (!\is_string($path)) {
            throw ContentSystemException::elementTypeInvalidFilename($relativePath, $relativePath);
        }

        $segments = explode('/', $path);
        $resolved = [];

        foreach ($segments as $segment) {
            if (preg_match(self::SEGMENT_PATTERN, $segment) !== 1) {
                throw ContentSystemException::elementTypeInvalidFilename($segment, $relativePath);
            }

            // kebab-case to PascalCase: "product-card" -> camel "productCard" -> title "ProductCard"
            $resolved[] = u($segment)->camel()->title()->toString();
        }

        return $prefix . ':' . implode(':', $resolved);
    }
}
