<?php declare(strict_types=1);

namespace Contena\Core\System\Language\ContentSystem\DataLoader;

use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Language\Channel\AbstractLanguageRoute;
use Contena\Core\System\Language\LanguageCollection;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads available languages via AbstractLanguageRoute.
 *
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<LanguageCollection>
 */
class LanguageDataLoader extends AbstractContentDataLoader
{
    public const string SOURCE = 'language';

    public function __construct(
        private readonly AbstractLanguageRoute $languageRoute,
    ) {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: []),
        ]);
    }

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        ChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $config = $requirement->config;

        $criteria = new Criteria();

        if ($config instanceof LanguageLoaderConfig) {
            foreach ($config->associations as $association) {
                $criteria->addAssociation($association);
            }
        }

        $response = $this->languageRoute->load($request, $context, $criteria);

        // LanguageRoute handles its own caching internally
        return ContentDataLoaderResult::cachedExternally($response->getLanguages());
    }
}
