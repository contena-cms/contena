/**
 *
 * @module core/factory/locale
 */
import { warn } from 'src/core/service/utils/debug.utils';
import { object } from 'src/core/service/util.service';

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default {
    getLocaleByName,
    getLocaleRegistry,
    register,
    extend,
    getBrowserLanguage,
    getBrowserLanguages,
    setSystemFallbackLocale,
    getLastKnownLocale,
    storeCurrentLocale,
};

/**
 * @private
 */
export type Snippets = {
    [key: string]: string | Snippets;
};

/**
 * @private
 */
export type SnippetRegistry = {
    [locale: string]: Snippets;
};

/**
 * Registry which holds all locales including the interface translations
 */
const localeRegistry = new Map<string, Snippets>();

/**
 * Defines the default locale
 *
 * @type {String}
 */
const defaultLocale = 'zh-CN';

/**
 * Defines the key of the localStorage item
 *
 * @type {String}
 */
const localStorageKey = 'contena-admin-locale';

let systemFallbackLocale: string | null = null;

/**
 * Get the complete locale registry
 * @returns {Map}
 */
function getLocaleRegistry() {
    return localeRegistry;
}

/**
 * Registers a new locale
 */
function register(localeName: string, localeMessages: Snippets = {}): boolean | string {
    if (!localeName || !localeName.length) {
        warn('LocaleFactory', 'A locale always needs a name');
        return false;
    }

    if (localeName.split('-').length < 2) {
        warn(
            'LocaleFactory',
            'The locale name should follow the RFC-4647 standard e.g. [languageCode-countryCode] for example "en-US"',
        );
        return false;
    }

    if (localeRegistry.has(localeName)) {
        warn(
            'LocaleFactory',
            `The locale "${localeName}" is registered already.`,
            'Please use the extend method to extend and override certain keys',
        );

        return false;
    }

    localeRegistry.set(localeName, localeMessages);

    return localeName;
}

/**
 * Extends a given locale with the provided translations
 */
function extend(localeName: string, localeMessages: Snippets = {}): boolean | string {
    if (localeName.split('-').length < 2) {
        warn(
            'LocaleFactory',
            'The locale name should follow the RFC-4647 standard e.g. [languageCode-countryCode]] for example "en-US"',
        );
        return false;
    }

    if (!localeRegistry.has(localeName)) {
        warn(
            'LocaleFactory',
            `The locale "${localeName}" doesn't exist. Please use the register method to register a new locale`,
        );
        return false;
    }

    const originalMessages = localeRegistry.get(localeName);
    localeRegistry.set(localeName, object.merge(originalMessages, localeMessages));

    // Adding snippets to current i18n instance
    // when already instantiated
    if (Contena.Snippet?.setLocaleMessage) {
        // Get the merged new messages from the locale registry
        const mergedMessages = localeRegistry.get(localeName);

        // Set empty messages first to trigger reactivity update
        Contena.Snippet.setLocaleMessage?.(localeName, {});
        Contena.Snippet.setLocaleMessage?.(localeName, mergedMessages!);
    }

    return localeName;
}

/**
 * Get translations for a specific locale
 */
function getLocaleByName(localeName: string): Snippets | boolean {
    return localeRegistry.get(localeName) || false;
}

function setSystemFallbackLocale(localeName: string | null): string | null {
    systemFallbackLocale = localeName;

    return systemFallbackLocale;
}

/**
 * Resolves the locale for the administration.
 * Prefers a supported stored locale, then a supported browser locale, and finally Chinese.
 */
function getLastKnownLocale(): string {
    const storedLocale = window.localStorage.getItem(localStorageKey);

    if (storedLocale && localeRegistry.has(storedLocale)) {
        setDocumentLocale(storedLocale);

        return storedLocale;
    }

    const locale = getBrowserLanguage();
    setDocumentLocale(locale);

    return locale;
}

/**
 * Terminates the browser language and checks if the language is in the registry.
 * If this is not the case the {@link defaultLocale} will be returned.
 */
function getBrowserLanguage(): string {
    const shortLanguageCodes = new Map<string, string>();
    localeRegistry.forEach((messages, locale) => {
        const lang = locale.split('-')[0];
        shortLanguageCodes.set(lang.toLowerCase(), locale);
    });

    let matchedLanguage: string | null = null;

    getBrowserLanguages().forEach((language) => {
        if (!matchedLanguage && localeRegistry.has(language)) {
            matchedLanguage = language;
        }

        if (!matchedLanguage && shortLanguageCodes.has(language)) {
            matchedLanguage = shortLanguageCodes.get(language) || null;
        }
    });

    if (matchedLanguage) {
        return matchedLanguage;
    }

    if (localeRegistry.has(defaultLocale)) {
        return defaultLocale;
    }

    return systemFallbackLocale || defaultLocale;
}

/**
 * Looks up all available browser languages.
 */
function getBrowserLanguages(): string[] {
    const languages = [];

    if (navigator.language) {
        languages.push(navigator.language);
    }

    // Chrome only
    if (navigator.languages?.length) {
        navigator.languages.forEach((lang) => {
            languages.push(lang);
        });
    }

    // @ts-expect-error
    if (navigator.userLanguage) {
        // @ts-expect-error
        languages.push(navigator.userLanguage);
    }

    // @ts-expect-error
    if (navigator.systemLanguage) {
        // @ts-expect-error
        languages.push(navigator.systemLanguage);
    }

    return languages as string[];
}

/**
 * Sets up the DOM and http client to use the provided locale
 */
function storeCurrentLocale(localeName: string): string {
    setDocumentLocale(localeName);

    window.localStorage.setItem(localStorageKey, localeName);

    return localeName;
}

/**
 * Keeps the document language in sync before the application renders. Browsers
 * use this region when selecting CJK glyphs, so leaving it unset can render
 * Simplified Chinese snippets with Traditional Chinese glyphs.
 */
function setDocumentLocale(localeName: string): void {
    if (typeof document === 'object') {
        document.documentElement.setAttribute('lang', localeName);
    }
}
