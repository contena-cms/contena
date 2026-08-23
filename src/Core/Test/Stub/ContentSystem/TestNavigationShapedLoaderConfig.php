<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\ContentSystem;

use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;

/**
 * The config object of {@see TestNavigationShapedLoader}: an entity name and one optional defaulted property
 * reference. It carries no required property reference, which is what makes the loader never gate.
 *
 * @internal
 */
final readonly class TestNavigationShapedLoaderConfig extends AbstractContentDataLoaderConfig
{
    public function __construct(
        public string $entity,
        public ?string $activeProperty = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = ['entity' => $this->entity];

        if ($this->activeProperty !== null) {
            $data['activeProperty'] = $this->activeProperty;
        }

        return $data;
    }
}
