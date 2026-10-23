<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Garan;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
class GaranLabelDurationFormatter
{
    public function formatMonths(?int $guaranteeMonths): ?string
    {
        if ($guaranteeMonths === null || $guaranteeMonths <= 24 || $guaranteeMonths % 6 !== 0) {
            return null;
        }

        $fullYears = intdiv($guaranteeMonths, 12);

        if ($guaranteeMonths % 12 === 0) {
            return (string) $fullYears;
        }

        return $fullYears . ',5';
    }
}
