<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Channel\Configuration;

use Contena\Core\System\SystemConfig\Util\ConfigReader;

/**
 * @internal
 */
final readonly class ChannelConfigReader
{
    public function __construct(private ConfigReader $configReader)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function read(string $file): array
    {
        $schema = $this->configReader->read($file);

        foreach ($schema as &$card) {
            if (!\is_array($card['elements'] ?? null)) {
                continue;
            }

            foreach ($card['elements'] as &$element) {
                if (\array_key_exists('secret', $element)) {
                    $element['secret'] = filter_var($element['secret'], \FILTER_VALIDATE_BOOLEAN);
                }
            }
            unset($element);
        }
        unset($card);

        return array_values($schema);
    }
}
