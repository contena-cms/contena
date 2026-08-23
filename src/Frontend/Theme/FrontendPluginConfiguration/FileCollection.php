<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\FrontendPluginConfiguration;

use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<File>
 */
class FileCollection extends Collection
{
    /**
     * @param list<string> $files
     *
     * @return self
     */
    public static function createFromArray(array $files)
    {
        $collection = new self();
        foreach ($files as $file) {
            $collection->add(new File($file));
        }

        return $collection;
    }

    /**
     * @return list<string>
     */
    public function getFilepaths(): array
    {
        return array_values($this->map(static fn (File $element) => $element->getFilepath()));
    }

    /**
     * @return list<string>
     */
    public function getPublicPaths(string $prefix): array
    {
        return array_values(array_filter($this->map(static function (File $element) use ($prefix) {
            if ($element->assetName === null) {
                return null;
            }

            if (!str_ends_with($element->getFilepath(), $element->assetName . '/' . basename($element->getFilepath()))) {
                return null;
            }

            return $prefix . '/' . $element->assetName . '/' . basename($element->getFilepath());
        })));
    }

    /**
     * @return array<string, string>
     */
    public function getResolveMappings(): array
    {
        $resolveMappings = [];

        foreach ($this->elements as $file) {
            if (\count($file->getResolveMapping()) > 0) {
                $resolveMappings[] = $file->getResolveMapping();
            }
        }

        return array_merge(...$resolveMappings);
    }

    protected function getExpectedClass(): ?string
    {
        return File::class;
    }
}
