<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;

/**
 * The Agentic Commerce sales channel feature is mirrored by the SwagAgenticCommerce
 * compatibility plugin for older Contena versions; changes here should be considered there.
 *
 * @internal
 */
class AgenticCommercePluginHint
{
    private const AGENTIC_COMMERCE_PATTERNS = [
        // PHP — blog export provider, validators and tracking that back the agentic-commerce sales channel type
        'src/Core/Content/BlogExport/Provider/*AgenticCommerce*',
        'src/Core/Content/BlogExport/Provider/OpenAi*',
        'src/Core/Content/BlogExport/Subscriber/*AgenticCommerce*',
        'src/Core/Content/BlogExport/Validator/OpenAi*',
        'src/Core/Content/BlogExport/Validator/AbstractProviderValidator.php',
        'src/Core/Content/BlogExport/Error/JsonlValidationError.php',
        'src/Core/Content/BlogExport/Error/ProviderValidationError.php',
        'src/Core/Content/BlogExport/Tracking/**',
        'src/Core/Migration/**/*AgenticCommerce*',
        'src/Core/Migration/**/*AgenticAi*',
        // Administration — agentic-commerce sales channel UI
        'src/Administration/Resources/app/administration/src/module/sw-sales-channel/agentic-blog-export-templates/**',
        'src/Administration/Resources/app/administration/src/module/sw-sales-channel/component/sw-agentic-commerce-tracking-config/**',
        'src/Administration/Resources/app/administration/src/module/sw-sales-channel/view/sw-sales-channel-detail-agentic-commerce-integration/**',
    ];

    public function __invoke(Context $context): void
    {
        $files = $context->platform->pullRequest->getFiles();

        $matched = [];
        foreach (self::AGENTIC_COMMERCE_PATTERNS as $pattern) {
            foreach ($files->matches($pattern) as $file) {
                $matched[$file->name] = true;
            }
        }

        if ($matched === []) {
            return;
        }

        ksort($matched);

        $context->warning(
            'This Pull Request touches the <strong>Agentic Commerce Sales Channel</strong> feature.<br/>'
            . 'If you added or changed functionality here, please consider porting the change to the compatibility plugin'
            . ' SwagAgenticCommerce (https://github.com/contena-cms/SwagAgenticCommerce) so it is also available to merchants on older Contena versions.<br/><br/>'
            . 'Affected files:<br/>'
            . implode('<br/>', array_keys($matched))
        );
    }
}
