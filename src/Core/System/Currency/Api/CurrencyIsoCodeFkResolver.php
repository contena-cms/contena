<?php declare(strict_types=1);

namespace Contena\Core\System\Currency\Api;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Api\Sync\AbstractFkResolver;
use Contena\Core\Framework\Api\Sync\FkReference;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
class CurrencyIsoCodeFkResolver extends AbstractFkResolver
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getName(): string
    {
        return 'currency.iso_code';
    }

    /**
     * @param array<FkReference> $map
     *
     * @return array<FkReference>
     */
    public function resolve(array $map): array
    {
        $codes = \array_map(static fn ($id) => \is_string($id->value) ? \strtoupper($id->value) : $id->value, $map);

        $codes = \array_filter(\array_unique($codes));

        if ($codes === []) {
            return $map;
        }

        $hash = $this->connection->fetchAllKeyValue(
            'SELECT iso_code, LOWER(HEX(id)) FROM currency WHERE iso_code IN (:codes)',
            ['codes' => $codes],
            ['codes' => ArrayParameterType::STRING]
        );

        foreach ($map as $reference) {
            if (!\is_string($reference->value)) {
                continue;
            }

            $key = \strtoupper($reference->value);
            if (isset($hash[$key])) {
                $reference->resolved = $hash[$key];
            }
        }

        return $map;
    }
}
