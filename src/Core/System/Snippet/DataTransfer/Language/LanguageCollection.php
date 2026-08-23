<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\DataTransfer\Language;

use Contena\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<Language>
 */
class LanguageCollection extends Collection
{
    /**
     * @param list<Language> $elements
     */
    public function __construct(iterable $elements = [])
    {
        parent::__construct();
        foreach ($elements as $element) {
            $this->set($element->locale, $element);
        }
    }

    /**
     * @param string $key
     * @param Language $element
     */
    public function set($key, $element): void
    {
        $this->validateType($element);

        $this->elements[$key] = $element;
    }

    public function add($element): void
    {
        $this->set($element->locale, $element);
    }

    protected function getExpectedClass(): string
    {
        return Language::class;
    }
}
