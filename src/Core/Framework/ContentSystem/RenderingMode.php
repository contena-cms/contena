<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem;

/**
 * Controls content rendering pipeline behavior.
 *
 * FULL: Complete pipeline - pre-hydration, hydration, post-hydration.
 * SKELETON: Skip hydration - returns layout structure without loaded data.
 */
enum RenderingMode: string
{
    case FULL = 'full';
    case SKELETON = 'skeleton';
}
