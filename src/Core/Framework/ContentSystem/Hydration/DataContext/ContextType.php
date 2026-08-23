<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Hydration\DataContext;

enum ContextType: string
{
    case Single = 'single';
    case Collection = 'collection';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
