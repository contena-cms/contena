const utils = Contena.Utils;

/**
 * @module app/service/blog-stream-condition
 */

/**
 * @private
 * @sw-package inventory
 * @memberOf module:app/service/blog-stream-condition
 * @constructor
 * @method conditionService
 * @returns {Object}
 */
export default function conditionService() {
    const allowedProperties = ['id'];

    const entityAllowedProperties = {
        tag: ['id'],
        category: ['id'],
        blog_manufacturer: ['id'],
        property_group_option: ['id', 'group'],
        property_group: ['id'],
        blog_visibility: ['id', 'channel'],
        channel: ['id'],
        blog: [
            'id',
            'active',
            'name',
            'description',
            'ratingAverage',
            'cheapestPrice',
            'blogNumber',
            'stock',
            'availableStock',
            'releaseDate',
            'tags',
            'weight',
            'height',
            'width',
            'length',
            'ean',
            'sales',
            'manufacturer',
            'manufacturerNumber',
            'categoriesRo',
            'shippingFree',
            'visibilities',
            'properties',
            'options',
            'isCloseout',
            'deliveryTime',
            'purchasePrices',
            'createdAt',
            'coverId',
            'markAsTopseller',
            'type',
        ],
    };

    if (!Contena.Feature.isActive('v6.8.0.0')) {
        entityAllowedProperties.blog.push('states');
    }

    /**
     * @deprecated tag:v6.8.0 - remove from stream & deprecation list
     */
    const blogStatesDeprecation = {
        version: 'v6.8.0',
        label: 'sw-blog-stream.filter.values.states',
        replacement: {
            field: 'type',
            label: 'sw-blog-stream.filter.values.type',
        },
    };

    /**
     * Registry of deprecated filter fields. `getDeprecationsInTree` reads this to
     * render the deprecation notice on the detail page.
     *
     * To deprecate a field, add an entry:
     *
     *   <fieldName>: {
     *       version: 'v6.8.0',                                   // version it is removed in
     *       label: 'sw-blog-stream.filter.values.<field>',    // snippet key, shown as the field name
     *       replacement: {                                       // optional; omit if there is none
     *           field: '<replacementField>',
     *           label: 'sw-blog-stream.filter.values.<replacementField>',
     *       },
     *   }
     *
     * @type {Object<string, { version: string, label: string, replacement?: { field: string, label: string } }>}
     */
    const deprecatedFields = {
        states: blogStatesDeprecation,
        'blog.states': blogStatesDeprecation,
    };

    const allowedJsonAccessors = {
        cheapestPrice: {
            value: 'cheapestPrice',
            type: 'float',
            trans: 'cheapestPrice',
        },
        'cheapestPrice.percentage': {
            value: 'cheapestPrice.percentage',
            type: 'float',
            trans: 'percentage',
        },
    };

    const blogFilterTypes = {
        equals: {
            identifier: 'equals',
            label: 'sw-blog-stream.filter.type.equals',
        },

        equalsAny: {
            identifier: 'equalsAny',
            label: 'sw-blog-stream.filter.type.equalsAny',
        },

        contains: {
            identifier: 'contains',
            label: 'sw-blog-stream.filter.type.contains',
        },

        lessThan: {
            identifier: 'lessThan',
            label: 'sw-blog-stream.filter.type.lessThan',
        },

        greaterThan: {
            identifier: 'greaterThan',
            label: 'sw-blog-stream.filter.type.greaterThan',
        },

        lessThanEquals: {
            identifier: 'lessThanEquals',
            label: 'sw-blog-stream.filter.type.lessThanEquals',
        },

        greaterThanEquals: {
            identifier: 'greaterThanEquals',
            label: 'sw-blog-stream.filter.type.greaterThanEquals',
        },

        notEquals: {
            identifier: 'notEquals',
            label: 'sw-blog-stream.filter.type.notEquals',
        },

        notEqualsAny: {
            identifier: 'notEqualsAny',
            label: 'sw-blog-stream.filter.type.notEqualsAny',
        },

        notContains: {
            identifier: 'notContains',
            label: 'sw-blog-stream.filter.type.notContains',
        },

        range: {
            identifier: 'range',
            label: 'sw-blog-stream.filter.type.range',
        },

        until: {
            identifier: 'until',
            label: 'sw-blog-stream.filter.type.until',
            operators: [
                'equals',
                'notEquals',
                'lessThan',
                'greaterThan',
                'lessThanEquals',
                'greaterThanEquals',
            ],
        },

        since: {
            identifier: 'since',
            label: 'sw-blog-stream.filter.type.since',
            operators: [
                'equals',
                'notEquals',
                'lessThan',
                'greaterThan',
                'lessThanEquals',
                'greaterThanEquals',
            ],
        },

        not: {
            identifier: 'not',
            label: 'sw-blog-stream.filter.type.not',
        },

        equalsAll: {
            identifier: 'equalsAll',
            label: 'sw-blog-stream.filter.type.equalsAll',
        },
        notEqualsAll: {
            identifier: 'notEqualsAll',
            label: 'sw-blog-stream.filter.type.notEqualsAll',
        },
    };

    const operatorSets = {
        boolean: [blogFilterTypes.equals],

        empty: [blogFilterTypes.equals],

        string: [
            blogFilterTypes.equals,
            blogFilterTypes.notEquals,
            blogFilterTypes.equalsAny,
            blogFilterTypes.notEqualsAny,
            blogFilterTypes.contains,
            blogFilterTypes.notContains,
        ],

        date: [
            blogFilterTypes.equals,
            blogFilterTypes.greaterThan,
            blogFilterTypes.greaterThanEquals,
            blogFilterTypes.lessThan,
            blogFilterTypes.lessThanEquals,
            blogFilterTypes.notEquals,
            blogFilterTypes.range,
            blogFilterTypes.since,
            blogFilterTypes.until,
        ],

        uuid: [
            blogFilterTypes.equals,
            blogFilterTypes.notEquals,
            blogFilterTypes.equalsAny,
            blogFilterTypes.notEqualsAny,
            blogFilterTypes.equalsAll,
            blogFilterTypes.notEqualsAll,
        ],

        int: [
            blogFilterTypes.equals,
            blogFilterTypes.greaterThan,
            blogFilterTypes.greaterThanEquals,
            blogFilterTypes.lessThan,
            blogFilterTypes.lessThanEquals,
            blogFilterTypes.notEquals,
            blogFilterTypes.range,
        ],

        float: [
            blogFilterTypes.equals,
            blogFilterTypes.greaterThan,
            blogFilterTypes.greaterThanEquals,
            blogFilterTypes.lessThan,
            blogFilterTypes.lessThanEquals,
            blogFilterTypes.notEquals,
            blogFilterTypes.range,
        ],

        object: [
            blogFilterTypes.equals,
            blogFilterTypes.greaterThan,
            blogFilterTypes.greaterThanEquals,
            blogFilterTypes.lessThan,
            blogFilterTypes.lessThanEquals,
            blogFilterTypes.notEquals,
            blogFilterTypes.range,
        ],

        text: [
            blogFilterTypes.equals,
            blogFilterTypes.notEquals,
            blogFilterTypes.equalsAny,
            blogFilterTypes.notEqualsAny,
            blogFilterTypes.contains,
            blogFilterTypes.notContains,
        ],

        default: [
            blogFilterTypes.equals,
            blogFilterTypes.notEquals,
            blogFilterTypes.equalsAny,
            blogFilterTypes.notEqualsAny,
        ],
    };

    return {
        isPropertyInAllowList,
        addToGeneralAllowList,
        addToEntityAllowList,
        removeFromGeneralAllowList,
        removeFromEntityAllowList,
        getConditions,
        getAndContainerData,
        isAndContainer,
        getOrContainerData,
        isOrContainer,
        getPlaceholderData,
        getComponentByCondition,
        getOperatorSet,
        negateOperator,
        getOperator,
        isNegatedType,
        isRangeType,
        isRelativeTimeType,
        getDeprecationsInTree,
        allowedJsonAccessors,
    };

    /**
     * @param {?string} definition
     * @param {string} property
     * @returns {boolean}
     */
    function isPropertyInAllowList(definition, property) {
        return (
            allowedProperties.includes(property) ||
            (entityAllowedProperties.hasOwnProperty(definition) && entityAllowedProperties[definition].includes(property))
        );
    }

    /**
     * @param {string|string[]} properties
     */
    function addToGeneralAllowList(properties) {
        properties = Array.isArray(properties) ? properties : [properties];
        allowedProperties.push(...properties);
    }

    /**
     * @param {string} entity
     * @param {string|string[]} properties
     */
    function addToEntityAllowList(entity, properties) {
        if (entityAllowedProperties[entity]) {
            properties = Array.isArray(properties) ? properties : [properties];
            entityAllowedProperties[entity].push(...properties);

            return;
        }

        entityAllowedProperties[entity] = properties;
    }

    /**
     * @param {string|string[]} properties
     */
    function removeFromGeneralAllowList(properties) {
        properties = Array.isArray(properties) ? properties : [properties];
        properties.forEach((entry) => {
            allowedProperties.splice(allowedProperties.indexOf(entry), 1);
        });
    }

    /**
     * @param {string} entity
     * @param {string|string[]} properties
     */
    function removeFromEntityAllowList(entity, properties) {
        if (!entityAllowedProperties[entity]) {
            return;
        }

        properties = Array.isArray(properties) ? properties : [properties];
        properties.forEach((entry) => {
            entityAllowedProperties[entity].splice(entityAllowedProperties[entity].indexOf(entry), 1);
        });
    }

    function getConditions() {
        return [
            {
                type: 'contentStreamFilter',
                component: 'sw-blog-stream-filter',
                label: 'blog',
                scopes: ['blog'],
            },
        ];
    }

    function getAndContainerData() {
        return {
            type: 'multi',
            field: null,
            parameters: null,
            operator: 'AND',
        };
    }

    function isAndContainer(condition) {
        return condition.type === 'multi' && condition.operator === 'AND';
    }

    function getOrContainerData() {
        return { type: 'multi', field: null, parameters: null, operator: 'OR' };
    }

    function isOrContainer(condition) {
        return condition.type === 'multi' && condition.operator === 'OR';
    }

    function getPlaceholderData() {
        return {
            type: 'equals',
            field: 'id',
            parameters: null,
            operator: null,
        };
    }

    function getComponentByCondition(condition) {
        if (isAndContainer(condition)) {
            return 'sw-condition-and-container';
        }

        if (isOrContainer(condition)) {
            return 'sw-condition-or-container';
        }

        return 'sw-blog-stream-filter';
    }

    function getOperatorSet(type) {
        if (!utils.types.isString(type) || type === '') {
            return operatorSets.default;
        }

        return operatorSets[type] || operatorSets.default;
    }

    function getOperator(type) {
        return blogFilterTypes[type];
    }

    function negateOperator(type) {
        switch (type) {
            case 'equals':
                return blogFilterTypes.notEquals;
            case 'notEquals':
                return blogFilterTypes.equals;
            case 'equalsAny':
                return blogFilterTypes.notEqualsAny;
            case 'notEqualsAny':
                return blogFilterTypes.equalsAny;
            case 'contains':
                return blogFilterTypes.notContains;
            case 'notContains':
                return blogFilterTypes.contains;
            case 'notEqualsAll':
                return blogFilterTypes.equalsAll;
            case 'equalsAll':
                return blogFilterTypes.notEqualsAll;
            default:
                return blogFilterTypes[type] || null;
        }
    }

    function isNegatedType(type) {
        return [
            blogFilterTypes.notContains.identifier,
            blogFilterTypes.notEqualsAny.identifier,
            blogFilterTypes.notEquals.identifier,
            blogFilterTypes.notEqualsAll.identifier,
        ].includes(type);
    }

    function isRangeType(type) {
        return [
            blogFilterTypes.lessThan.identifier,
            blogFilterTypes.lessThanEquals.identifier,
            blogFilterTypes.greaterThan.identifier,
            blogFilterTypes.greaterThanEquals.identifier,
            blogFilterTypes.range.identifier,
        ].includes(type);
    }

    function isRelativeTimeType(type) {
        return [blogFilterTypes.since.identifier, blogFilterTypes.until.identifier].includes(type);
    }

    function getDeprecationsInTree(filters) {
        const uniqueFields = [...new Set(collectFilterFields(filters))];

        return uniqueFields.flatMap((field) => {
            const deprecation = deprecatedFields[field];

            if (!deprecation) {
                return [];
            }

            return [
                {
                    field,
                    label: deprecation.label,
                    version: deprecation.version,
                    replacement: deprecation.replacement ?? null,
                },
            ];
        });
    }

    function collectFilterFields(filters) {
        return normalizeFilterCollection(filters).flatMap((condition) => {
            if (!condition) {
                return [];
            }

            return [
                ...(condition.field ? [condition.field] : []),
                ...collectFilterFields(condition.queries),
                ...collectFilterFields(condition.children),
            ];
        });
    }

    function normalizeFilterCollection(filters) {
        if (!filters) {
            return [];
        }

        if (Array.isArray(filters)) {
            return filters.filter(Boolean);
        }

        if (typeof filters.toArray === 'function') {
            return filters.toArray();
        }

        if (typeof filters[Symbol.iterator] === 'function') {
            return [...filters];
        }

        return [];
    }
}
