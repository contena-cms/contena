<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\Term;

class Tokenizer implements TokenizerInterface
{
    private const int DEFAULT_MIN_SEARCH_TERM_LENGTH = 2;

    /**
     * @param string[] $preservedChars
     *
     * @internal
     */
    public function __construct(
        private readonly array $preservedChars = ['-', '_', '+', '.', '@']
    ) {
    }

    public function tokenize(string $string, ?int $tokenMinimumLength = null): array
    {
        $tokenMinimumLength ??= self::DEFAULT_MIN_SEARCH_TERM_LENGTH;

        $string = mb_strtolower(html_entity_decode($string), 'UTF-8');
        $string = str_replace('<', ' <', $string);
        $string = strip_tags($string);

        $allowChars = '';

        foreach ($this->preservedChars as $char) {
            $allowChars .= '\\' . $char;
        }

        $string = trim((string) preg_replace(\sprintf("/[^\pL%s0-9]/u", $allowChars), ' ', $string));

        /** @var list<non-falsy-string> $tags */
        $tags = array_filter(explode(' ', $string));

        $filtered = [];
        foreach ($tags as $tag) {
            $tag = trim($tag);

            if ($tag === '' || mb_strlen($tag) < $tokenMinimumLength) {
                continue;
            }

            $filtered[] = $tag;
        }

        if ($filtered === []) {
            return $tags;
        }

        return array_values(array_unique($filtered));
    }
}
