<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Header;

use Contena\Core\Content\Category\Tree\Tree;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Currency\CurrencyCollection;
use Contena\Core\System\Currency\CurrencyEntity;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Frontend\Pagelet\NavigationPagelet;

class HeaderPagelet extends NavigationPagelet
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed, access the active language through the context
     */
    protected LanguageEntity $activeLanguage;

    /**
     * @deprecated tag:v6.8.0 - Will be removed, access the active currency through the context
     */
    protected CurrencyEntity $activeCurrency;

    /**
     * @internal
     */
    public function __construct(
        Tree $navigation,
        protected LanguageCollection $languages,
        protected CurrencyCollection $currencies,
    ) {
        parent::__construct($navigation);
    }

    public function getLanguages(): LanguageCollection
    {
        return $this->languages;
    }

    public function getCurrencies(): CurrencyCollection
    {
        return $this->currencies;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, access the active language through the context
     */
    public function setActiveLanguage(LanguageEntity $activeLanguage): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        $this->activeLanguage = $activeLanguage;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, access the active language through the context
     */
    public function getActiveLanguage(): LanguageEntity
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->activeLanguage;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, access the active language through the context
     */
    public function setActiveCurrency(CurrencyEntity $activeCurrency): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        $this->activeCurrency = $activeCurrency;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, access the active language through the context
     */
    public function getActiveCurrency(): CurrencyEntity
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->activeCurrency;
    }
}
