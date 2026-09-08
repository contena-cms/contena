<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Consent;

use Contena\Core\Framework\App\Feature\AppFeatureConfig;
use Contena\Core\Framework\App\Feature\AppFeatureDefinition;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Manifest\XmlParserUtils;
use Contena\Core\Framework\Util\Filesystem;

/**
 * @internal
 *
 * @extends AppFeatureDefinition<ConsentConfig>
 *
 * @phpstan-import-type ConsentPayload from ConsentConfig
 */
class ConsentFeatureDefinition extends AppFeatureDefinition
{
    public const TYPE = 'consent';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getConfigClass(): string
    {
        return ConsentConfig::class;
    }

    public function fromApp(Manifest $manifest, Filesystem $appFilesystem, string $defaultLocale): array
    {
        $configs = [];

        foreach ($manifest->getDocument()->getElementsByTagName('consent') as $consent) {
            $values = XmlParserUtils::parseChildren($consent);

            $configs[] = new ConsentConfig(
                (string) $values['name'],
                (string) $values['scope'],
                new \DateTimeImmutable((string) $values['since']),
                isset($values['revision']) ? (string) $values['revision'] : null,
            );
        }

        return $configs;
    }

    /**
     * @return ConsentPayload
     */
    public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array
    {
        return $declared->toArray();
    }

    /**
     * @param ConsentPayload $payload
     */
    public function fromPayload(array $payload): ConsentConfig
    {
        return new ConsentConfig(
            $payload['name'],
            $payload['scope'],
            new \DateTimeImmutable($payload['since']),
            $payload['revision'] ?? null,
        );
    }
}
