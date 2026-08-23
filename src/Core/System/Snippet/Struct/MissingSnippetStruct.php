<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Struct;

use Contena\Core\Framework\Struct\Struct;

class MissingSnippetStruct extends Struct
{
    public function __construct(
        protected string $keyPath,
        protected string $filePath,
        protected string $availableISO,
        protected string $availableTranslation,
        protected string $missingForISO,
        protected ?string $translation = null
    ) {
    }

    public function getKeyPath(): string
    {
        return $this->keyPath;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getAvailableISO(): string
    {
        return $this->availableISO;
    }

    public function getAvailableTranslation(): string
    {
        return $this->availableTranslation;
    }

    public function getMissingForISO(): string
    {
        return $this->missingForISO;
    }

    public function getTranslation(): ?string
    {
        return $this->translation;
    }

    public function setTranslation(string $translation): void
    {
        $this->translation = $translation;
    }
}
