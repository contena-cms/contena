import { watch } from 'vue';

let isInitialized = false;

/**
 * @private
 */
export default function LanguageAutoFetchingService() {
    if (isInitialized) return;
    isInitialized = true;

    // initial loading of the language
    loadLanguage(Contena.Context.api.languageId);

    // load the language Entity
    async function loadLanguage(newLanguageId) {
        const languageRepository = Contena.Service('repositoryFactory').create('language');
        const newLanguage = await languageRepository.get(newLanguageId, {
            ...Contena.Context.api,
            inheritance: true,
        });

        Contena.Store.get('context').api.language = newLanguage;
    }

    watch(() => Contena.Store.get('context').api.languageId, loadLanguage);
}
