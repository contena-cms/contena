<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\ContentSystem\DataLoader;

use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;

/**
 * @phpstan-import-type BlogSearchLoaderConfigData from BlogSearchLoaderConfig
 *
 * @internal
 *
 * @final
 */
class BlogSearchLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return 'blog_search';
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        $searchTermProperty = null;
        if (\array_key_exists('searchTermProperty', $data)) {
            if (!\is_string($data['searchTermProperty']) || $data['searchTermProperty'] === '') {
                throw BlogException::invalidFieldValueType('searchTermProperty', 'non-empty string', \gettype($data['searchTermProperty']));
            }
            $searchTermProperty = $data['searchTermProperty'];
        }

        $associations = [];
        if (\array_key_exists('associations', $data) && $data['associations'] !== null) {
            if (!\is_array($data['associations'])) {
                throw BlogException::invalidFieldValueType('associations', 'array', \gettype($data['associations']));
            }
            foreach ($data['associations'] as $i => $association) {
                if (!\is_string($association) || $association === '') {
                    throw BlogException::invalidFieldValueType('associations.' . $i, 'non-empty string', \gettype($association));
                }

                $associations[] = $association;
            }
        }

        return new BlogSearchLoaderConfig($searchTermProperty, $associations);
    }

    /**
     * @return BlogSearchLoaderConfigData
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof BlogSearchLoaderConfig) {
            throw BlogException::invalidFieldValueType('config', BlogSearchLoaderConfig::class, $config::class);
        }

        return $config->jsonSerialize();
    }
}
