<?php declare(strict_types=1);

namespace Contena\Core\Migration\Traits;

class Translations
{
    /**
     * @param array<string, string|null> $chinese
     * @param array<string, string|null> $english
     */
    public function __construct(
        protected array $chinese,
        protected array $english
    ) {
    }

    /**
     * @return array<string, string|null>
     */
    public function getChinese(): array
    {
        return $this->chinese;
    }

    /**
     * @return array<string, string|null>
     */
    public function getEnglish(): array
    {
        return $this->english;
    }

    /**
     * @return list<string>
     */
    public function getColumns(): array
    {
        return array_keys($this->english);
    }
}
