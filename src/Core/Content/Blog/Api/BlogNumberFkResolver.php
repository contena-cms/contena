<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Api;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Sync\AbstractFkResolver;
use Contena\Core\Framework\Api\Sync\FkReference;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class BlogNumberFkResolver extends AbstractFkResolver
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getName(): string
    {
        return 'blog.number';
    }

    /**
     * @param array<FkReference> $map
     *
     * @return array<FkReference>
     */
    public function resolve(array $map): array
    {
        $numbers = \array_map(static fn ($id) => $id->value, $map);

        $numbers = \array_filter(\array_unique($numbers));

        if ($numbers === []) {
            return $map;
        }

        $hash = $this->connection->fetchAllKeyValue(
            'SELECT blog_number, LOWER(HEX(id)) FROM blog WHERE blog_number IN (:numbers) AND version_id = :version',
            ['numbers' => $numbers, 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
            ['numbers' => ArrayParameterType::STRING]
        );

        foreach ($map as $reference) {
            if (isset($hash[$reference->value])) {
                $reference->resolved = $hash[$reference->value];
            }
        }

        return $map;
    }
}
