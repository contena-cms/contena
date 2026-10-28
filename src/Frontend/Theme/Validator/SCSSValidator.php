<?php declare(strict_types=1);

namespace Contena\Storefront\Theme\Validator;

use ScssPhp\ScssPhp\Colors;
use ScssPhp\ScssPhp\OutputStyle;
use Contena\Core\Framework\Log\Package;

#[Package('discovery')]
class SCSSValidator
{

    /**
     * @param array<int, string> $customAllowedRegex
     */
    private static function validateCustom(string $value, array $customAllowedRegex): bool
    {
        foreach ($customAllowedRegex as $regex) {
            preg_match('/' . $regex . '/i', $value, $parsed);
            if (isset($parsed[0]) && $parsed[0] !== '') {
                return true;
            }
        }

        return false;
    }

    private static function validateTypeCheckbox(mixed $value): mixed
    {
        if (!\is_bool($value) && $value !== 1 && $value !== 0) {
            return 'true';
        }

        return $value;
    }




    private static function isValidColorName(mixed $value): bool
    {
        return (str_starts_with($value, '#') && self::isHex(substr($value, 1)))
            || (str_starts_with($value, 'hsl') && self::isHSL($value))
            || (str_starts_with($value, 'rgb') && self::isRGB($value))
            || (!str_starts_with($value, '#') && Colors::colorNameToRGBa($value) !== null);
    }

    private static function initVariables(string $value, string $varVal): string
    {
        $initString = '';
        $vars = self::extractSCSSvars($value);
        foreach ($vars as $var) {
            $initString .= \PHP_EOL . $var . ': ' . $varVal . ';';
        }

        return $initString;
    }

    /**
     * @return list<string>
     */
    private static function extractSCSSvars(string $value): array
    {
        $matches = [];
        $vars = [];
        preg_match_all('/.*?(\$(?![0-9])(?:[a-zA-Z0-9-_]|(?:\\[!"#$%&\'\(\)*+,.\/:;<=>?@\[\]^{|}~]))+)/D', $value, $matches);
        foreach ($matches[1] as $match) {
            $vars[] = $match;
        }

        return $vars;
    }

    private static function isHex(string $hexCode): bool
    {
        preg_match('/^[a-f0-9]*$/Di', $hexCode, $parsed);

        return isset($parsed[0]) && $parsed[0] === $hexCode;
    }

    /**
     * Validates if the given HSL code is valid.
     *
     * It supports both classic and modern syntax.
     * Will forward to isHSLA for classic syntax with 'hsla'.
     * Supports SCSS functions like hue(), saturation(), lightness().
     *
     * The relative color syntax is not supported.
     * It will fail in combination with SCSS color functions.
     * Patterns like `hsl(from <color> H S L[ / A])` are not valid.
     */
    private static function isHSL(string $hslCode): bool
    {
        if (!str_starts_with($hslCode, 'hsl')) {
            return false;
        }

        if (str_starts_with($hslCode, 'hsla')) {
            return self::isHSLA($hslCode);
        }

        $hue = '(?:360(?:\.0+)?|3[0-5]\d(?:\.\d+)?|[12]\d{2}(?:\.\d+)?|[1-9]?\d(?:\.\d+)?)';
        $percent = '(?:100|[1-9]?\d)%';
        $alpha = '(?:0?\.\d+|1(?:\.0+)?|0|(?:100|[1-9]?\d)%)';

        // SCSS function call pattern (e.g., hue($var), saturation($var))
        $scssFunction = '[a-z-]+\([^)]+\)';

        // Allow either numeric values or SCSS functions
        $hueOrFunction = '(?:' . $hue . '(?:deg)?|' . $scssFunction . ')';
        $percentOrFunction = '(?:' . $percent . '|' . $scssFunction . ')';
        $alphaOrFunction = '(?:' . $alpha . '|' . $scssFunction . ')';

        // Modern: hsl(h s% l% / a?)  (spaces; optional / alpha)
        $patternModern = '/^hsl\(\s*' . $hueOrFunction . '\s+' . $percentOrFunction . '\s+' . $percentOrFunction . '(?:\s*\/\s*' . $alphaOrFunction . ')?\s*\)$/Di';

        // Legacy: hsl(h, s%, l%)  (commas; no alpha)
        $patternLegacy = '/^hsl\(\s*' . $hueOrFunction . '\s*,\s*' . $percentOrFunction . '\s*,\s*' . $percentOrFunction . '\s*\)$/Di';

        return preg_match($patternModern, $hslCode) === 1 || preg_match($patternLegacy, $hslCode) === 1;
    }

    /**
     * Validates if the given HSLA code is valid.
     * Supports SCSS functions like hue(), saturation(), lightness().
     *
     * The relative color syntax is not supported.
     * It will fail in combination with SCSS color functions.
     * Patterns like `hsla(from <color> H S L A)` are not valid.
     */
    private static function isHSLA(string $hslaCode): bool
    {
        $hue = '(?:360(?:\.0+)?|3[0-5]\d(?:\.\d+)?|[12]\d{2}(?:\.\d+)?|[1-9]?\d(?:\.\d+)?)';
        $percent = '(?:100|[1-9]?\d)%';
        $alpha = '(?:0?\.\d+|1(?:\.0+)?|0|(?:100|[1-9]?\d)%)';

        // SCSS function call pattern (e.g., hue($var), saturation($var))
        $scssFunction = '[a-z-]+\([^)]+\)';

        // Allow either numeric values or SCSS functions
        $hueOrFunction = '(?:' . $hue . '(?:deg)?|' . $scssFunction . ')';
        $percentOrFunction = '(?:' . $percent . '|' . $scssFunction . ')';
        $alphaOrFunction = '(?:' . $alpha . '|' . $scssFunction . ')';

        // Legacy HSLA: hsla(h, s%, l%, a)
        $patternHsla = '/^hsla\(\s*' . $hueOrFunction . '\s*,\s*' . $percentOrFunction . '\s*,\s*' . $percentOrFunction . '\s*,\s*' . $alphaOrFunction . '\s*\)$/Di';

        return preg_match($patternHsla, $hslaCode) === 1;
    }

    /**
     * Validates if the given RGB code is valid.
     *
     * It supports both classic and modern syntax.
     * Allows SCSS variables, hex colors, and SCSS functions like red(), green(), blue().
     * Will forward to isRGBA for classic syntax with 'rgba'.
     *
     * The relative color syntax is not supported.
     * It will fail in combination with SCSS color functions.
     * Patterns like `rgb(from <color> R G B[ / A])` are not valid.
     */
    private static function isRGB(string $rgbCode): bool
    {
        if (!str_starts_with($rgbCode, 'rgb')) {
            return false;
        }

        if (str_starts_with($rgbCode, 'rgba')) {
            return self::isRGBA($rgbCode);
        }

        $rgbValue = '(?:25[0-5]|2[0-4]\d|1?\d?\d|(?:100|[1-9]?\d)%)';
        $alpha = '(?:0?\.\d+|1(?:\.0+)?|0|(?:100|[1-9]?\d)%)';

        // SCSS function call pattern (e.g., red($var), green($var))
        $scssFunction = '[a-z-]+\([^)]+\)';

        // Allow either numeric values or SCSS functions
        $rgbOrFunction = '(?:' . $rgbValue . '|' . $scssFunction . ')';
        $alphaOrFunction = '(?:' . $alpha . '|' . $scssFunction . ')';

        // Modern: rgb(r g b / a?)  (spaces; optional / alpha)
        $patternModern = '/^rgb\(\s*' . $rgbOrFunction . '\s+' . $rgbOrFunction . '\s+' . $rgbOrFunction . '(?:\s*\/\s*' . $alphaOrFunction . ')?\s*\)$/Di';

        // Legacy: rgb(r, g, b)  (commas; no alpha)
        $patternLegacy = '/^rgb\(\s*' . $rgbOrFunction . '\s*,\s*' . $rgbOrFunction . '\s*,\s*' . $rgbOrFunction . '\s*\)$/Di';

        // SCSS variable or hex color format: rgb($variable / alpha?) or rgb(#fff / alpha?)
        $scssVariable = '\$(?![0-9])[a-zA-Z0-9-_]+';
        $hexColor = '#[a-f0-9]{3,8}';
        $patternRgbWithColorOrVariable = '/^rgb\(\s*(?:' . $scssVariable . '|' . $hexColor . ')(?:\s*\/\s*' . $alphaOrFunction . ')?\s*\)$/Di';

        return preg_match($patternModern, $rgbCode) === 1
            || preg_match($patternLegacy, $rgbCode) === 1
            || preg_match($patternRgbWithColorOrVariable, $rgbCode) === 1;
    }

    /**
     * Validates if the given RGBA code is valid.
     * Allows SCSS variables, hex colors, and SCSS functions like red(), green(), blue(), alpha().
     *
     * The relative color syntax is not supported.
     * It will fail in combination with SCSS color functions.
     * Patterns like `rgba(from <color> R G B A)` are not valid.
     */
    private static function isRGBA(string $rgbaCode): bool
    {
        $rgbValue = '(?:25[0-5]|2[0-4]\d|1?\d?\d|(?:100|[1-9]?\d)%)';
        $alpha = '(?:0?\.\d+|1(?:\.0+)?|0|(?:100|[1-9]?\d)%)';

        // SCSS function call pattern (e.g., red($var), green($var))
        $scssFunction = '[a-z-]+\([^)]+\)';

        // Allow either numeric values or SCSS functions
        $rgbOrFunction = '(?:' . $rgbValue . '|' . $scssFunction . ')';
        $alphaOrFunction = '(?:' . $alpha . '|' . $scssFunction . ')';

        // Classic 4-value format: rgba(r, g, b, a)
        $patternRgba = '/^rgba\(\s*' . $rgbOrFunction . '\s*,\s*' . $rgbOrFunction . '\s*,\s*' . $rgbOrFunction . '\s*,\s*' . $alphaOrFunction . '\s*\)$/Di';

        // SCSS variable or hex color format: rgba($variable, alpha) or rgba(#fff, alpha)
        $scssVariable = '\$(?![0-9])[a-zA-Z0-9-_]+';
        $hexColor = '#[a-f0-9]{3,8}';
        $patternRgbaWithColorOrVariable = '/^rgba\(\s*(?:' . $scssVariable . '|' . $hexColor . ')\s*,\s*' . $alphaOrFunction . '\s*\)$/Di';

        return preg_match($patternRgba, $rgbaCode) === 1 || preg_match($patternRgbaWithColorOrVariable, $rgbaCode) === 1;
    }
}
