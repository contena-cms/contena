<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Content\Seo\Hreflang\HreflangCollection;
use Contena\Core\Content\Seo\Hreflang\HreflangStruct;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\Routing\RouterInterface;

class HreflangLoader implements HreflangLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RouterInterface $router,
        private readonly Connection $connection
    ) {
    }

    public function load(HreflangLoaderParameter $parameter): HreflangCollection
    {
        $channelContext = $parameter->getChannelContext();

        if (!$channelContext->getChannel()->isHreflangActive()) {
            return new HreflangCollection();
        }

        $domains = $this->fetchChannelDomains($channelContext->getChannelId());

        if ($parameter->isHomepage()) {
            return $this->getHreflangForHomepage($domains, $channelContext->getChannel()->getHreflangDefaultDomainId());
        }

        $pathInfo = $this->router->generate($parameter->getRoute(), $parameter->getRouteParameters(), RouterInterface::ABSOLUTE_PATH);

        $basePath = $parameter->getBasePath();
        if ($basePath !== '' && str_starts_with($pathInfo, $basePath)) {
            $pathInfo = mb_substr($pathInfo, mb_strlen($basePath));
        }

        $languageToDomainMapping = $this->getLanguageToDomainMapping($domains);
        $seoUrls = $this->fetchSeoUrls($pathInfo, $channelContext->getChannelId(), array_keys($languageToDomainMapping));

        // We need at least two links
        if (\count($seoUrls) <= 1) {
            return new HreflangCollection();
        }

        $hreflangCollection = new HreflangCollection();

        /** @var array{seoPathInfo: string, languageId: string} $seoUrl */
        foreach ($seoUrls as $seoUrl) {
            /** @var array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool} $domain */
            foreach ($languageToDomainMapping[$seoUrl['languageId']] as $domain) {
                $this->addHreflangForDomain(
                    $domain,
                    $seoUrl,
                    $channelContext->getChannel()->getHreflangDefaultDomainId(),
                    $hreflangCollection
                );
            }
        }

        return $hreflangCollection;
    }

    /**
     * @param list<array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool}> $domains
     */
    private function getHreflangForHomepage(array $domains, ?string $defaultDomainId): HreflangCollection
    {
        $collection = new HreflangCollection();

        if (\count($domains) <= 1) {
            return new HreflangCollection();
        }

        /** @var array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool} $domain */
        foreach ($domains as $domain) {
            $this->addHreflangForDomain(
                $domain,
                null,
                $defaultDomainId,
                $collection
            );
        }

        return $collection;
    }

    /**
     * @return list<array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool}>
     */
    private function fetchChannelDomains(string $channelId): array
    {
        /** @var list<array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool}> $result */
        $result = $this->connection->fetchAllAssociative(
            'SELECT `domain`.`language_id` AS languageId,
                          `domain`.`id` AS id,
                          `domain`.`url` AS url,
                          `domain`.`hreflang_use_only_locale` AS onlyLocale,
                          `locale`.`code` AS locale
            FROM `channel_domain` AS `domain`
            INNER JOIN `language` ON `language`.`id` = `domain`.`language_id`
            INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
            WHERE `domain`.`channel_id` = :channelId',
            ['channelId' => Uuid::fromHexToBytes($channelId)]
        );

        return $result;
    }

    /**
     * @param list<array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool}> $domains
     *
     * @return array<string, list<array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool}>>
     */
    private function getLanguageToDomainMapping(array $domains): array
    {
        $mapping = [];

        foreach ($domains as $domain) {
            $mapping[$domain['languageId']][] = $domain;
        }

        return $mapping;
    }

    /**
     * @param array{languageId: string, id: string, url: string, locale: string, onlyLocale: bool} $domain
     * @param array{seoPathInfo: string, languageId: string}|null $seoUrl
     */
    private function addHreflangForDomain(
        array $domain,
        ?array $seoUrl,
        ?string $defaultDomainId,
        HreflangCollection $collection
    ): void {
        $hrefLang = new HreflangStruct();

        $hrefLang->setUrl($domain['url']);
        if ($seoUrl) {
            $hrefLang->setUrl($domain['url'] . '/' . $seoUrl['seoPathInfo']);
        }
        $locale = $domain['locale'];

        if ($domain['onlyLocale']) {
            $locale = mb_substr($locale, 0, 2);
        }

        if (Uuid::fromBytesToHex($domain['id']) === $defaultDomainId) {
            $mainLang = clone $hrefLang;
            $mainLang->setLocale('x-default');
            $collection->add($mainLang);
        }

        $hrefLang->setLocale($locale);
        $collection->add($hrefLang);
    }

    /**
     * @param array<string> $languageIds
     *
     * @return list<array{seoPathInfo: string, languageId: string}>
     */
    private function fetchSeoUrls(string $pathInfo, string $channelId, array $languageIds): array
    {
        /** @var list<array{seoPathInfo: string, languageId: string}> $result */
        $result = $this->connection->fetchAllAssociative(
            'SELECT `seo_path_info` AS seoPathInfo, `language_id` AS languageId
            FROM `seo_url`
            WHERE `path_info` = :pathInfo AND `is_canonical` = 1 AND
                  `channel_id` = :channelId AND `language_id` IN (:languageIds)',
            ['pathInfo' => $pathInfo, 'channelId' => Uuid::fromHexToBytes($channelId), 'languageIds' => $languageIds],
            ['languageIds' => ArrayParameterType::BINARY]
        );

        return $result;
    }
}
