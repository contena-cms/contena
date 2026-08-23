<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Profiling\Profiler;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

class SeoUrlPlaceholderHandler implements SeoUrlPlaceholderHandlerInterface
{
    final public const DOMAIN_PLACEHOLDER = '124c71d524604ccbad6042edce3ac799';

    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly Connection $connection
    ) {
    }

    /**
     * @param string $name
     * @param array<string, string> $parameters
     */
    public function generate($name, array $parameters = []): string
    {
        $path = $this->router->generate($name, $parameters);

        $request = $this->requestStack->getMainRequest();
        $basePath = $request ? $request->getBasePath() : '';
        $path = $this->removePrefix($path, $basePath);

        return self::DOMAIN_PLACEHOLDER . $path . '#';
    }

    public function replace(string $content, string $host, ChannelContext $context): string
    {
        return Profiler::trace('seo-url-replacer', function () use ($content, $host, $context) {
            $matches = [];

            if (preg_match_all('/' . self::DOMAIN_PLACEHOLDER . '[^#]*#/', $content, $matches)) {
                $mapping = $this->createDefaultMapping($matches[0]);
                $seoMapping = $this->createSeoMapping($context, $mapping);
                foreach ($seoMapping as $key => $value) {
                    if ($context->getChannel()->getTypeId() !== Defaults::CHANNEL_TYPE_API) {
                        $seoMapping[$key] = $host . '/' . ltrim($value, '/');

                        continue;
                    }

                    $externalFrontendDomain = $this->getExternalFrontendDomain($context);
                    if ($externalFrontendDomain === null) {
                        continue;
                    }

                    $seoMapping[$key] = rtrim($externalFrontendDomain, '/') . '/' . ltrim($value, '/');
                }

                return (string) \preg_replace_callback('/' . self::DOMAIN_PLACEHOLDER . '[^#]*#/', static fn (array $match) => $seoMapping[$match[0]], $content);
            }

            return $content;
        });
    }

    private function getExternalFrontendDomain(ChannelContext $context): ?string
    {
        foreach ($context->getChannel()->getDomains() ?? [] as $domain) {
            if (!$domain->getIsExternalFrontend() || $domain->getLanguageId() !== $context->getLanguageId()) {
                continue;
            }

            return $domain->getUrl();
        }

        return null;
    }

    /**
     * @param list<string> $matches
     *
     * @return array<string, string>
     */
    private function createDefaultMapping(array $matches): array
    {
        $mapping = [];
        $placeholder = \strlen(self::DOMAIN_PLACEHOLDER);
        foreach ($matches as $match) {
            // remove self::DOMAIN_PLACEHOLDER from start
            // remove # from end
            $mapping[$match] = str_replace('\/', '/', substr((string) $match, $placeholder, -1));
        }

        return $mapping;
    }

    /**
     * @param array<string, string> $mapping
     *
     * @return array<string, string>
     */
    private function createSeoMapping(ChannelContext $context, array $mapping): array
    {
        if ($mapping === []) {
            return [];
        }

        $query = new QueryBuilder($this->connection);
        $query->setTitle('seo_url::replacement');
        $query->addSelect('seo_path_info', 'path_info', 'channel_id');

        $query->from('seo_url');
        $query->andWhere('seo_url.is_canonical = 1');
        $query->andWhere('seo_url.path_info IN (:pathInfo)');
        $query->andWhere('seo_url.language_id = :languageId');
        $query->andWhere('seo_url.channel_id = :channelId OR seo_url.channel_id IS NULL');
        $query->andWhere('seo_url.is_deleted = 0');
        $query->setParameter('pathInfo', $mapping, ArrayParameterType::STRING);
        $query->setParameter('languageId', Uuid::fromHexToBytes($context->getLanguageId()));
        $query->setParameter('channelId', Uuid::fromHexToBytes($context->getChannelId()));

        $seoUrls = $query->executeQuery()->fetchAllAssociative();

        $mapped = [];

        foreach ($seoUrls as $seoUrl) {
            $key = self::DOMAIN_PLACEHOLDER . $seoUrl['path_info'] . '#';

            // prefer channel-specific SEO URLs
            if ($seoUrl['channel_id'] === null && isset($mapped[$key])) {
                continue;
            }

            $seoPathInfo = trim((string) $seoUrl['seo_path_info']);
            if ($seoPathInfo === '') {
                continue;
            }
            $mapping[$key] = $seoPathInfo;
            $mapped[$key] = true;
        }

        return $mapping;
    }

    private function removePrefix(string $subject, string $prefix): string
    {
        if (!$prefix || mb_strpos($subject, $prefix) !== 0) {
            return $subject;
        }

        return mb_substr($subject, mb_strlen($prefix));
    }
}
