/**
 *
 * @private
 * @memberOf module:core/service/locale
 * @constructor
 * @method createLocaleHelperService
 * @returns {Object}
 */
export default class LocaleHelperService {
    _localeRepository;

    _localeFactory;

    _snippetService;

    _Contena;

    constructor({ Contena, localeRepository, snippetService, localeFactory }) {
        this._Contena = Contena;
        this._snippetService = snippetService;
        this._localeFactory = localeFactory;
        this._localeRepository = localeRepository;
    }

    async setLocaleWithId(localeId) {
        const { code } = await this._localeRepository.get(localeId, this._Contena.Context.api);

        await this.setLocaleWithCode(code);
    }

    async setLocaleWithCode(localeCode) {
        await this._snippetService.getSnippets(this._localeFactory, localeCode);
        await this._Contena.Store.get('session').setAdminLocale(localeCode);
    }
}
