<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Http;

use Contena\Core\Framework\Adapter\Cache\Http\Extension\ResolveCacheRelevantRuleIdsExtension;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @final
 */
readonly class CacheRelevantRulesResolver
{
    /**
     * @internal
     */
    public function __construct(
        private ExtensionDispatcher $extensions,
    ) {
    }

    /**
     * @return list<string> List of rule IDs which should be considered for the HTTP Cache in the context cookie / header
     */
    public function resolveRuleAreas(Request $request, ChannelContext $context): array
    {
        $ruleIdsExtension = new ResolveCacheRelevantRuleIdsExtension($request, [RuleAreas::BLOG_AREA], $context);

        /** @var list<string> $ruleAreas */
        $ruleAreas = $this->extensions->publish(
            name: ResolveCacheRelevantRuleIdsExtension::NAME,
            extension: $ruleIdsExtension,
            function: static function (Request $request, array $ruleAreas, ChannelContext $channelContext): array {
                return $ruleAreas;
            },
        );

        return $ruleAreas;
    }
}
