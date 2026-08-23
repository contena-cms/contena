<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

class SeoResolver extends AbstractSeoResolver
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getDecorated(): AbstractSeoResolver
    {
        throw new DecorationPatternException(self::class);
    }

    public function resolveUrl(SeoUrlRequestContext $context): ResolvedSeoUrl
    {
        $seoPathInfo = trim($context->pathInfo, '/');
        $normalizedQueryString = $this->normalizeQueryString($context->queryString);

        $query = new QueryBuilder($this->connection)
            ->select('id', 'path_info pathInfo', 'seo_path_info seoPathInfo', 'is_canonical isCanonical', 'channel_id channelId')
            ->from('seo_url')
            ->where('language_id = :language_id')
            ->andWhere('(channel_id = :channel_id OR channel_id IS NULL)')
            ->andWhere('seo_url.is_deleted = 0');

        $seoPathConditions = [
            'seo_path_info = :seoPath',
            'seo_path_info = :seoPathWithSlash',
        ];

        $query->setParameter('language_id', Uuid::fromHexToBytes($context->languageId))
            ->setParameter('channel_id', Uuid::fromHexToBytes($context->channelId))
            ->setParameter('seoPath', $seoPathInfo)
            ->setParameter('seoPathWithSlash', $seoPathInfo . '/');

        $queryCandidates = array_values(array_unique(array_filter(
            [$normalizedQueryString, $context->queryString],
            static fn (?string $query): bool => $query !== null && $query !== ''
        )));

        foreach ($queryCandidates as $index => $candidate) {
            $seoPathConditions[] = "seo_path_info = :seoPathWithQuery{$index}";
            $seoPathConditions[] = "seo_path_info = :seoPathWithSlashAndQuery{$index}";
            $query->setParameter("seoPathWithQuery{$index}", $seoPathInfo . '?' . $candidate)
                ->setParameter("seoPathWithSlashAndQuery{$index}", $seoPathInfo . '/?' . $candidate);
        }

        $query->andWhere('(' . implode(' OR ', $seoPathConditions) . ')');
        $query->setTitle('seo-url::resolve');

        /** @var list<array{id: string, pathInfo: string, seoPathInfo: string, isCanonical: string|null, channelId: string|null}> $seoPaths */
        $seoPaths = $query->executeQuery()->fetchAllAssociative();

        usort($seoPaths, function ($a, $b) use ($normalizedQueryString) {
            if ($a['isCanonical'] === null) {
                return 1;
            }

            if ($b['isCanonical'] === null) {
                return -1;
            }

            if ($a['channelId'] === null) {
                return 1;
            }

            if ($b['channelId'] === null) {
                return -1;
            }

            if ($normalizedQueryString !== null) {
                $aMatches = $this->storedQueryMatches($a['seoPathInfo'], $normalizedQueryString);
                $bMatches = $this->storedQueryMatches($b['seoPathInfo'], $normalizedQueryString);
                if ($aMatches !== $bMatches) {
                    return $aMatches ? -1 : 1;
                }
            }

            return 0;
        });

        $seoPath = ['pathInfo' => $seoPathInfo, 'isCanonical' => false];

        foreach ($seoPaths as $path) {
            $seoPath = $path;
            if ($path['isCanonical']) {
                break;
            }
        }

        if (!$seoPath['isCanonical']) {
            $query = new QueryBuilder($this->connection)
                ->select('path_info pathInfo', 'seo_path_info seoPathInfo')
                ->from('seo_url')
                ->where('language_id = :language_id')
                ->andWhere('channel_id = :channel_id')
                ->andWhere('path_info = :pathInfo')
                ->andWhere('is_canonical = 1')
                ->andWhere('is_deleted = 0')
                ->setMaxResults(1)
                ->setParameter('language_id', Uuid::fromHexToBytes($context->languageId))
                ->setParameter('channel_id', Uuid::fromHexToBytes($context->channelId))
                ->setParameter('pathInfo', '/' . ltrim((string) $seoPath['pathInfo'], '/'));

            $query->setTitle('seo-url::resolve-fallback');

            // we only have an id when the hit seo url was not a canonical url, save the one filter condition
            if (isset($seoPath['id'])) {
                $query->andWhere('id != :id')
                    ->setParameter('id', $seoPath['id']);
            }

            $canonicalQueryResult = $query->executeQuery()->fetchAssociative();
            if ($canonicalQueryResult) {
                $seoPath['canonicalPathInfo'] = '/' . ltrim((string) $canonicalQueryResult['seoPathInfo'], '/');
            }
        }

        $seoPath['pathInfo'] = '/' . ltrim((string) $seoPath['pathInfo'], '/');

        return new ResolvedSeoUrl(
            pathInfo: $seoPath['pathInfo'],
            isCanonical: (bool) $seoPath['isCanonical'],
            id: $seoPath['id'] ?? null,
            canonicalPathInfo: $seoPath['canonicalPathInfo'] ?? null,
            seoPathInfo: $seoPath['seoPathInfo'] ?? null,
        );
    }

    private function normalizeQueryString(?string $queryString): ?string
    {
        $normalizedQueryString = Request::normalizeQueryString($queryString);

        return $normalizedQueryString === '' ? null : $normalizedQueryString;
    }

    private function storedQueryMatches(mixed $storedSeoPathInfo, string $normalizedQueryString): bool
    {
        if (!\is_string($storedSeoPathInfo)) {
            return false;
        }

        $storedQuery = parse_url($storedSeoPathInfo, \PHP_URL_QUERY);
        if (!\is_string($storedQuery)) {
            return false;
        }

        return $this->normalizeQueryString($storedQuery) === $normalizedQueryString;
    }
}
