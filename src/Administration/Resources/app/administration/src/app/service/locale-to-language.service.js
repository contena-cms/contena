const { Criteria } = Contena.Data;

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default function createLocaleToLanguageService() {
    return {
        localeToLanguage,
    };

    /**
     * @param {string} locale
     * @return {Promise} languageIdPromise
     */
    function localeToLanguage(locale) {
        const apiContext = Contena.Context.api;
        const repoFactory = Contena.Service('repositoryFactory');
        const localeRepo = repoFactory.create('locale', '/locale');
        const localeCriteria = new Criteria(1, 25);

        localeCriteria.addFilter(Criteria.equals('code', locale)).addAssociation('languages');

        return localeRepo
            .search(localeCriteria, apiContext)
            .then((data) => {
                return data.first().languages.first().id;
            })
            .catch(() => {
                // Fallback: System default language
                return Contena.Context.api.systemLanguageId;
            });
    }
}
