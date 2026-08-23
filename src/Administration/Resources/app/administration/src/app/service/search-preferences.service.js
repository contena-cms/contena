import { KEY_USER_SEARCH_PREFERENCE } from 'src/app/service/search-ranking.service';

/**
 * @description Exposes an user search preferences
 * @constructor
 * @param {Object} Object.userConfigRepository
 */
// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default function SearchPreferencesService({ userConfigRepository: _userConfigRepository }) {
    return {
        getDefaultSearchPreferences,
        getUserSearchPreferences,
        createUserSearchPreferences,
        processSearchPreferences,
        processSearchPreferencesFields,
    };

    /**
     * @description Get default search preferences
     * @returns {Array}
     */
    function getDefaultSearchPreferences() {
        const defaultSearchPreferences = [];

        Contena.Module.getModuleRegistry().forEach(({ manifest }) => {
            if (
                manifest.entity &&
                Contena.Service('acl').can(`${manifest.entity}.editor`) &&
                manifest.defaultSearchConfiguration
            ) {
                defaultSearchPreferences.push({
                    [manifest.entity]: manifest.defaultSearchConfiguration,
                });
            }
        });

        return defaultSearchPreferences;
    }

    /**
     * @description Get user search preferences
     * @returns {Promise}
     */
    function getUserSearchPreferences() {
        return new Promise((resolve) => {
            Contena.Service('userConfigService')
                .search([KEY_USER_SEARCH_PREFERENCE])
                .then((response) => {
                    resolve(response.data[KEY_USER_SEARCH_PREFERENCE] || null);
                });
        });
    }

    /**
     * @description Define user search preferences
     * @returns {Object}
     */
    function createUserSearchPreferences() {
        const userSearchPreferences = _userConfigRepository.create();

        _getUserConfigCriteria().filters.forEach(({ field, value }) => {
            userSearchPreferences[field] = value;
        });

        return userSearchPreferences;
    }

    /**
     * @description Process search preferences
     * @param {Array} tempSearchPreferences
     * [{
     *     media: {
     *         _searchable: false,
     *         fileName: {
     *             _searchable: true,
     *             _score: 80,
     *         },
     *     }
     * }]
     * @returns {Array}
     * [{
     *     entityName: 'media'
     *     _searchable: false,
     *     fields: [{
     *         fieldName: 'fileName',
     *         _score: 80,
     *         _searchable: true
     *     }]
     * }]
     */
    function processSearchPreferences(tempSearchPreferences) {
        const searchPreferences = [];

        tempSearchPreferences = Object.assign({}, ...tempSearchPreferences);
        Object.entries(tempSearchPreferences).forEach(
            ([
                entityName,
                { _searchable, ...rest },
            ]) => {
                const fields = _getFields(rest);
                searchPreferences.push({ entityName, _searchable, fields });
            },
        );

        searchPreferences.sort((a, b) => {
            const lengthDiff = b.fields.length - a.fields.length;

            if (lengthDiff !== 0) {
                return lengthDiff;
            }

            return a.entityName.localeCompare(b.entityName);
        });

        return searchPreferences;
    }

    /**
     * @description Process search preferences fields
     * @param {Array} tempSearchPreferencesFields
     * [{
     *     fieldName: 'company',
     *     _searchable: true,
     *     _score: 500,
     *     group: [{
     *             fieldName: 'company',
     *             _score: 500,
     *             _searchable: true
     *         },
     *         {
     *             fieldName: 'primaryProfile.company',
     *             _score: 500,
     *             _searchable: true
     *         },
     *         {
     *             fieldName: 'secondaryProfile.company',
     *             _score: 500,
     *             _searchable: true
     *         }
     *     ]
     * }]
     * @returns {Object}
     * {
     *     company: {
     *         _score: 500,
     *         _searchable: true
     *     }
     *     primaryProfile: {
     *         company: {
     *             _score: 500,
     *             _searchable: true
     *         }
     *     }
     *     secondaryProfile: {
     *         company: {
     *             _score: 500,
     *             _searchable: true
     *         }
     *     }
     * }
     */
    function processSearchPreferencesFields(tempSearchPreferencesFields) {
        let searchPreferencesFields = {};

        tempSearchPreferencesFields.forEach((field) => {
            field.group.forEach((group) => {
                const searchPreferencesField = Contena.Utils.object.set({}, group.fieldName, {
                    _searchable: field._searchable,
                    _score: field._score,
                });
                searchPreferencesFields = Contena.Utils.object.deepMergeObject(
                    searchPreferencesFields,
                    searchPreferencesField,
                );
            });
        });

        return searchPreferencesFields;
    }

    /**
     * @private
     */
    function _getUserConfigCriteria() {
        const criteria = new Contena.Data.Criteria();

        criteria.addFilter(Contena.Data.Criteria.equals('key', KEY_USER_SEARCH_PREFERENCE));
        criteria.addFilter(Contena.Data.Criteria.equals('userId', _getCurrentUser()?.id));

        return criteria;
    }

    /**
     * @private
     */
    function _getCurrentUser() {
        return Contena.Store.get('session').currentUser;
    }

    /**
     * @private
     */
    function _getFields(data) {
        const fieldsGroup = {};

        Object.entries(data).forEach(
            ([
                key,
                value,
            ]) => {
                const fields = _flattenFields(value, `${key}.`);
                _groupFields(fields, fieldsGroup);
            },
        );

        return Object.values(fieldsGroup);
    }

    /**
     * @private
     */
    function _flattenFields(fields, prefix = '') {
        return Object.keys(fields).reduce((accumulator, currentValue) => {
            if (typeof fields[currentValue] === 'object') {
                return [
                    ...accumulator,
                    ..._flattenFields(fields[currentValue], `${prefix + currentValue}.`),
                ];
            }

            if (typeof fields[currentValue] === 'number') {
                return accumulator;
            }

            const fieldName = prefix.substring(0, prefix.length - 1);
            return [
                ...accumulator,
                { fieldName, ...fields },
            ];
        }, []);
    }

    /**
     * @private
     */
    function _groupFields(fields, fieldsGroup) {
        [...fields].forEach((item) => {
            let lastFieldName = item.fieldName.slice(item.fieldName.lastIndexOf('.') + 1);
            if (item.fieldName.includes('tags.name')) {
                lastFieldName = 'tagsName';
            }
            if (item.fieldName.includes('country.name')) {
                lastFieldName = 'countryName';
            }
            if (item.fieldName.includes('mediaFolder.name')) {
                lastFieldName = 'mediaFolderName';
            }
            fieldsGroup[lastFieldName] ??= {
                group: [],
                fieldName: lastFieldName,
                _searchable: item._searchable,
                _score: item._score,
            };

            fieldsGroup[lastFieldName].group.push(item);
        });
    }
}
