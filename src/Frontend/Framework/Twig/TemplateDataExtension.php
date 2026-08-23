<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Twig;

use Doctrine\DBAL\Connection;
use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class TemplateDataExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly bool $showStagingBanner,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getGlobals(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return [];
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);
        if (!$context instanceof ChannelContext) {
            return [];
        }

        $themeId = $request->attributes->get(ChannelRequest::ATTRIBUTE_THEME_ID);

        // check attribute bag for path parameter first (category routes), fallback to other request parameters (blog routes)
        $activeNavigationId = (string) ($request->attributes->get('navigationId') ?? RequestParamHelper::get($request, 'navigationId', ''));

        // resolve category for landing pages so navigation active state is set correctly
        if ($activeNavigationId === '') {
            $landingPageId = $request->attributes->getString('landingPageId');
            if ($landingPageId !== '') {
                $activeNavigationId = $this->resolveNavigationIdForLandingPage($landingPageId, $context);
            }
        }

        // fallback to root category (Home) if no navigation context could be resolved
        if ($activeNavigationId === '') {
            $activeNavigationId = $context->getChannel()->getNavigationCategoryId();
        }
        $navigationPathIdList = $this->getNavigationPath($activeNavigationId, $context);
        $navigationInfo = new NavigationInfo(
            $activeNavigationId,
            $navigationPathIdList,
        );

        return [
            'contena' => [
                'dateFormat' => \DATE_ATOM,
                'navigation' => $navigationInfo,
                'minSearchLength' => $this->minSearchLength($context),
                'showStagingBanner' => $this->showStagingBanner,
            ],
            'themeId' => $themeId, /** Not used in Twig template directly, but in @see \Contena\Frontend\Framework\Twig\Extension\ConfigExtension::getThemeId */
            'context' => $context,
            'activeRoute' => $request->attributes->get('_route'),
            'formViolations' => $request->attributes->get('formViolations'),
            // JSON_HEX_TAG and JSON_HEX_AMP encode < > & so a value like "</script>" cannot close the script tag
            // and inject arbitrary HTML (XSS) when embedding JSON in an HTML <script> block.
            // |escape('js') is not suitable because it escapes double quotes, breaking the JSON structure.
            'jsonLdFlags' => \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_HEX_AMP,
        ];
    }

    private function minSearchLength(ChannelContext $context): int
    {
        [$tenantCondition, $tenantParameters] = $this->tenantCondition($context);
        $min = (int) $this->connection->fetchOne(
            'SELECT `min_search_length` FROM `blog_search_config` WHERE `language_id` = :id AND ' . $tenantCondition,
            array_merge(['id' => Uuid::fromHexToBytes($context->getLanguageId())], $tenantParameters),
        );

        return $min ?: AbstractTokenFilter::DEFAULT_MIN_SEARCH_TERM_LENGTH;
    }

    private function resolveNavigationIdForLandingPage(string $landingPageId, ChannelContext $context): string
    {
        [$tenantCondition, $tenantParameters] = $this->tenantCondition($context, 'ct.');
        $categoryId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(ct.category_id))
             FROM category_translation ct
             WHERE ct.link_type = :linkType
               AND ct.internal_link = :landingPageId
               AND ' . $tenantCondition . '
             LIMIT 1',
            array_merge([
                'linkType' => 'landing_page',
                'landingPageId' => Uuid::fromHexToBytes($landingPageId),
            ], $tenantParameters),
        );

        return $categoryId ?: '';
    }

    /**
     * @return list<string>
     */
    private function getNavigationPath(string $activeNavigationId, ChannelContext $context): array
    {
        [$tenantCondition, $tenantParameters] = $this->tenantCondition($context);
        $path = $this->connection->fetchOne(
            'SELECT path FROM category WHERE id = :id AND ' . $tenantCondition,
            array_merge(['id' => Uuid::fromHexToBytes($activeNavigationId)], $tenantParameters),
        ) ?: '';

        $navigationPathIdList = array_filter(explode('|', $path));
        $navigationPathIdList = array_diff($navigationPathIdList, [$context->getChannel()->getNavigationCategoryId()]);

        return array_values($navigationPathIdList);
    }

    /**
     * @return array{string, array<string, string>}
     */
    private function tenantCondition(ChannelContext $context, string $alias = ''): array
    {
        $tenantId = $context->getContext()->getTenantId();
        if ($tenantId === null) {
            return [$alias . '`tenant_id` IS NULL', []];
        }

        return [$alias . '`tenant_id` = :tenantId', ['tenantId' => Uuid::fromHexToBytes($tenantId)]];
    }
}
