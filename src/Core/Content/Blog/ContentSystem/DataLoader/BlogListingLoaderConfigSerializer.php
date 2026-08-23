<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\ContentSystem\DataLoader;

use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;

/**
 * @phpstan-import-type BlogListingLoaderConfigData from BlogListingLoaderConfig
 *
 * @internal
 *
 * @final
 */
class BlogListingLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return 'blog_listing';
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        $property = null;
        if (\array_key_exists('property', $data)) {
            if (!\is_string($data['property']) || $data['property'] === '') {
                throw BlogException::invalidFieldValueType('property', 'non-empty string', \gettype($data['property']));
            }
            $property = $data['property'];
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

        return new BlogListingLoaderConfig($property, $associations);
    }

    /**
     * @return BlogListingLoaderConfigData
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof BlogListingLoaderConfig) {
            throw BlogException::invalidFieldValueType('config', BlogListingLoaderConfig::class, $config::class);
        }

        return $config->jsonSerialize();
    }
}
