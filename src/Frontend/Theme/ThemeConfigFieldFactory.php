<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

use Contena\Frontend\Theme\Exception\ThemeException;

class ThemeConfigFieldFactory
{
    /**
     * @param array<string, mixed> $configFieldArray
     */
    public function create(string $name, array $configFieldArray): ThemeConfigField
    {
        $configField = new ThemeConfigField();
        $configField->setName($name);

        unset($configFieldArray['label'], $configFieldArray['helpText']);

        foreach ($configFieldArray as $key => $value) {
            $setter = 'set' . $key;
            if (!method_exists($configField, $setter)) {
                throw ThemeException::invalidThemeConfig($key);
            }
            $configField->$setter($value); /* @phpstan-ignore-line */
        }

        return $configField;
    }
}
