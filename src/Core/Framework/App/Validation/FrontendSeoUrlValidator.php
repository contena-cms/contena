<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation;

use Contena\Core\Content\Seo\Validation\Constraint\ValidSeoPathInfo;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Manifest\Xml\Frontend\SeoUrl;
use Contena\Core\Framework\App\Validation\Error\ErrorCollection;
use Contena\Core\Framework\App\Validation\Error\FrontendSeoUrlError;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\Validation\RouteBlocklistService;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class FrontendSeoUrlValidator extends AbstractManifestValidator
{
    public function __construct(private readonly RouteBlocklistService $routeBlocklistService)
    {
    }

    public function validate(Manifest $manifest, Context $context): array
    {
        $seoUrls = $manifest->getFrontend()?->getSeoUrls() ?? [];
        if ($seoUrls === []) {
            return [];
        }

        $violations = [];
        $seenNames = [];

        foreach ($seoUrls as $seoUrl) {
            $name = $seoUrl->getName();

            if (isset($seenNames[$name])) {
                $violations[] = \sprintf('%s: the name is used by more than one seo-url', $name);

                continue;
            }
            $seenNames[$name] = true;

            foreach ($this->validateSeoUrl($seoUrl) as $violation) {
                $violations[] = \sprintf('%s: %s', $name, $violation);
            }
        }

        if ($violations !== []) {
            return [new FrontendSeoUrlError($violations)];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function validateSeoUrl(SeoUrl $seoUrl): array
    {
        $violations = [];

        $paths = $seoUrl->getPath();
        $isEntityBound = $seoUrl->getEntity() !== null;

        if ($isEntityBound && $paths !== []) {
            $violations[] = 'an entity bound seo-url must not define a path';
        }

        if (!$isEntityBound && $paths === []) {
            $violations[] = 'a seo-url must define either an entity or at least one path';
        }

        $defaultTemplate = $seoUrl->getDefaultTemplate();

        if ($isEntityBound && ($defaultTemplate === null || trim($defaultTemplate) === '')) {
            $violations[] = 'an entity bound seo-url requires a non-empty default-template';
        }

        if (!$isEntityBound && $defaultTemplate !== null) {
            $violations[] = 'a static seo-url must not define a default-template';
        }

        foreach ($paths as $locale => $path) {
            if (trim($path) === '') {
                $violations[] = \sprintf('the path for "%s" must not be empty', $locale);

                continue;
            }

            if (ValidSeoPathInfo::sanitize($path) !== $path) {
                $violations[] = \sprintf('the path "%s" contains characters that are not allowed in URLs', $path);

                continue;
            }

            if ($this->routeBlocklistService->isPathBlocked($path)) {
                $violations[] = \sprintf('the path "%s" is already used by another route', $path);
            }
        }

        return $violations;
    }
}
