<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
final class ConfigConstructionClass
{
    /**
     * @return array{enabled: int, payload: string}
     */
    public static function getConfig(): array
    {
        $defaultPayload = [
            'a' => 1,
            'b' => 2,
        ];

        return [
            'enabled' => 1,
            'payload' => (string) json_encode($defaultPayload),
        ];
    }

    /**
     * @return array<string, array<string, int>>
     */
    public static function getFieldsFor(string $entityName): array
    {
        $config = [
            'blog' => self::blogFields(),
            'category' => self::categoryFields(),
        ];

        return $config[$entityName] ?? [];
    }

    /**
     * @return array<string, int>
     */
    private static function blogFields(): array
    {
        return ['name' => 1];
    }

    /**
     * @return array<string, int>
     */
    private static function categoryFields(): array
    {
        return ['name' => 1];
    }
}
