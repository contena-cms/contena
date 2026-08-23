const { remove } = Contena.Utils.array;
const { Service } = Contena;
const { Criteria } = Contena.Data;

/**
 *
 * @private
 * @module app/service/custom-field
 * @memberOf module:core/service/custom-field
 * @constructor
 * @method createCustomFieldTypeService
 * @returns {Object}
 */
// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default function createCustomFieldService() {
    const $typeStore = {
        select: {
            configRenderComponent: 'ct-custom-field-type-select',
            config: {},
        },
        entity: {
            configRenderComponent: 'ct-custom-field-type-entity',
            type: 'select',
            config: {},
        },
        text: {
            configRenderComponent: 'ct-custom-field-type-text',
            type: 'text',
            config: {
                componentName: 'ct-field',
                type: 'text',
            },
        },
        media: {
            configRenderComponent: 'ct-custom-field-type-base',
            type: 'text',
            config: {
                componentName: 'ct-media-field',
            },
        },
        number: {
            configRenderComponent: 'ct-custom-field-type-number',
            type: 'int',
            config: {
                componentName: 'ct-field',
                type: 'number',
                numberType: 'float',
            },
        },
        date: {
            configRenderComponent: 'ct-custom-field-type-date',
            type: 'datetime',
            config: {
                componentName: 'ct-field',
                type: 'date',
                dateType: 'datetime',
            },
        },
        checkbox: {
            configRenderComponent: 'ct-custom-field-type-checkbox',
            type: 'bool',
            config: {
                componentName: 'ct-field',
                type: 'checkbox',
            },
        },
        switch: {
            configRenderComponent: 'ct-custom-field-type-checkbox',
            type: 'bool',
            config: {
                componentName: 'ct-field',
                type: 'switch',
            },
        },
        textEditor: {
            configRenderComponent: 'ct-custom-field-type-text-editor',
            type: 'html',
            config: {
                componentName: 'mt-text-editor',
            },
        },
        colorpicker: {
            configRenderComponent: 'ct-custom-field-type-colorpicker',
            type: 'text',
            config: {
                componentName: 'ct-field',
                type: 'colorpicker',
            },
        },
    };

    const $entityNameStore = [
        'country',
        'region',
        'integration',
        'language',
        'locale',
        'media',
        'media_folder',
        'number_range',
        'plugin',
        'state_machine',
        'state_machine_state',
        'user',
    ];

    return {
        getTypeByName,
        upsertType,
        getTypes,
        getEntityNames,
        addEntityName,
        removeEntityName,
        getCustomFieldSets,
    };

    function getTypeByName(type) {
        return $typeStore[type];
    }

    function upsertType(name, configuration) {
        $typeStore[name] = { ...$typeStore[name], ...configuration };
    }

    function getTypes() {
        return $typeStore;
    }

    function getEntityNames() {
        return $entityNameStore;
    }

    function addEntityName(entityName) {
        $entityNameStore.push(entityName);
    }

    function removeEntityName(entityName) {
        remove($entityNameStore, (storeItem) => {
            return storeItem === entityName;
        });
    }

    function getCustomFieldSets(entityName) {
        const customFieldSetRepository = Service('repositoryFactory').create('custom_field_set');

        return customFieldSetRepository.search(customFieldSetCriteria(entityName), Contena.Context.api).then((sets) => {
            return sets.filter((set) => set.customFields.length > 0);
        });
    }

    function customFieldSetCriteria(entityName) {
        const criteria = new Criteria(1, 25);

        criteria.addFilter(Criteria.equals('relations.entityName', entityName));
        criteria.getAssociation('customFields').addSorting(Criteria.sort('config.customFieldPosition'));

        return criteria;
    }
}
