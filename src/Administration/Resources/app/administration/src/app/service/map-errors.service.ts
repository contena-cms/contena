// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export type CamelCasePath<T extends string> = T extends `${infer A}.${infer B}`
    ? `${Capitalize<Lowercase<A>>}${CamelCasePath<Capitalize<B>>}`
    : Capitalize<T>;

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export function mapPropertyErrors<T extends string, K extends string>(
    entityName: T,
    properties: K[] = [],
): Record<`${T}${CamelCasePath<K>}Error`, () => unknown> {
    const computedValues: Record<string, () => unknown> = {};

    properties.forEach((property) => {
        const computedValueName = Contena.Utils.string.camelCase(`${entityName}.${property}.error`);

        computedValues[computedValueName] = function getterPropertyError() {
            const entity = (this as VueComponent)[entityName];

            const isEntity = entity && typeof entity.getEntityName === 'function';
            if (!isEntity) {
                return null;
            }

            return Contena.Store.get('error').getApiError(entity, property);
        };
    });

    return computedValues;
}

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export function mapSystemConfigErrors(entityName: string, saleChannelId: string | null, key: string = ''): $TSFixMe {
    return Contena.Store.get('error').getSystemConfigApiError(entityName, saleChannelId!, key);
}

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export function mapCollectionPropertyErrors<T extends string, K extends string>(
    entityCollectionName: T,
    properties: K[] = [],
): Record<`${T}${CamelCasePath<K>}Error`, () => unknown> {
    const computedValues: Record<string, () => unknown> = {};

    properties.forEach((property) => {
        const computedValueName = Contena.Utils.string.camelCase(`${entityCollectionName}.${property}.error`);

        computedValues[computedValueName] = function getterCollectionError() {
            const entityCollection = this[entityCollectionName];

            if (!Array.isArray(entityCollection)) {
                return null;
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
            return entityCollection.map((entity) => Contena.Store.get('error').getApiError(entity, property));
        };
    });

    return computedValues;
}

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export function mapPageErrors<T extends string>(
    errorConfig: Record<T, Record<string, string[]>>,
): Record<`${Uncapitalize<CamelCasePath<T>>}Error`, () => boolean> {
    const map: Record<string, () => boolean> = {};
    Object.keys(errorConfig).forEach((routeName) => {
        const subjects = errorConfig[routeName as T];
        map[`${Contena.Utils.string.camelCase(routeName)}Error`] = function getterPropertyError() {
            return Object.keys(subjects).some((entityName) => {
                return Contena.Store.get('error').existsErrorInProperty(entityName, subjects[entityName]);
            });
        };
    });
    return map;
}
