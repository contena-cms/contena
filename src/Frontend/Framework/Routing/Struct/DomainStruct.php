<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing\Struct;

readonly class DomainStruct
{
    public function __construct(
        public string $url,
        public string $id,
        public string $channelId,
        public string $typeId,
        public string $snippetSetId,
        public string $languageId,
        public ?string $themeId,
        public string $maintenance,
        public ?string $maintenanceIpAllowlist,
        public string $locale,
        public ?string $themeName,
        public ?string $parentThemeName,
    ) {
    }

    /**
     * @param array<string, string|null> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            url: rtrim((string) $row['url'], '/'),
            id: (string) $row['id'],
            channelId: (string) $row['channelId'],
            typeId: (string) $row['typeId'],
            snippetSetId: (string) $row['snippetSetId'],
            languageId: (string) $row['languageId'],
            themeId: isset($row['themeId']) ? (string) $row['themeId'] : null,
            maintenance: (string) $row['maintenance'],
            maintenanceIpAllowlist: isset($row['maintenanceIpAllowlist']) ? (string) $row['maintenanceIpAllowlist'] : null,
            locale: (string) $row['locale'],
            themeName: isset($row['themeName']) ? (string) $row['themeName'] : null,
            parentThemeName: isset($row['parentThemeName']) ? (string) $row['parentThemeName'] : null,
        );
    }
}
