<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\ContentSystem\DataLoader;

use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;

/**
 * @internal
 */
final class BlogCommentLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return BlogCommentDataLoader::SOURCE;
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        if (!\array_key_exists('property', $data)) {
            return new BlogCommentLoaderConfig();
        }

        if (!\is_string($data['property']) || $data['property'] === '') {
            throw BlogException::invalidFieldValueType('property', 'non-empty string', get_debug_type($data['property']));
        }

        return new BlogCommentLoaderConfig($data['property']);
    }

    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof BlogCommentLoaderConfig) {
            throw BlogException::invalidFieldValueType('config', BlogCommentLoaderConfig::class, $config::class);
        }

        return $config->jsonSerialize();
    }
}
