<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Garan;

use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
class GaranLabelResolver
{
    public const LABEL_TYPE_FULL = 'full';
    public const LABEL_TYPE_NESTED = 'nested';

    public function __construct(
        private readonly GaranLabelDurationFormatter $durationFormatter,
        private readonly GaranLabelRenderer $renderer,
    ) {
    }

    public function resolve(BlogEntity $blog, string $type = self::LABEL_TYPE_FULL): ?string
    {
        $duration = $this->resolveDuration($blog);

        if ($duration === null) {
            return null;
        }

        if ($type === self::LABEL_TYPE_NESTED) {
            return $this->renderer->renderNestedLabel($duration);
        }

        return $this->renderer->render(
            $duration,
            trim((string) $blog->getManufacturer()?->getTranslation('name')),
            trim((string) $blog->getManufacturerNumber()),
        );
    }

    /**
     * @return array{guaranteeMonths: int, brand: string, modelIdentifier: string}|null
     */
    public function resolveSnapshot(BlogEntity $blog): ?array
    {
        if ($this->resolveDuration($blog) === null) {
            return null;
        }

        return [
            'guaranteeMonths' => (int) $blog->getGuaranteeMonths(),
            'brand' => trim((string) $blog->getManufacturer()?->getTranslation('name')),
            'modelIdentifier' => trim((string) $blog->getManufacturerNumber()),
        ];
    }

    public function resolveDuration(BlogEntity $blog): ?string
    {
        if (!$blog->isGuaranteeConfirmed()) {
            return null;
        }

        $brand = trim((string) $blog->getManufacturer()?->getTranslation('name'));
        $modelIdentifier = trim((string) $blog->getManufacturerNumber());

        if ($brand === '' || $modelIdentifier === '') {
            return null;
        }

        return $this->durationFormatter->formatMonths($blog->getGuaranteeMonths());
    }
}
