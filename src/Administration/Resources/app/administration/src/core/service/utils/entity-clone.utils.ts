import cloneDeepWith from 'lodash-es/cloneDeepWith';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import Entity from 'src/core/data/entity.data';

/* eslint-disable @typescript-eslint/no-explicit-any */
/**
 * Deep clone with custom handling for entities and entity collections
 */
// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default function deepCloneWithEntity(data: any): any {
    return cloneDeepWith(
        data,
        (value: {
            __identifier__?: () => string;
            source?: string;
            entity?: keyof EntitySchema.Entities;
            criteria?: Criteria;
            total?: number;
            aggregations?: unknown;
            id?: string;
            _entityName?: keyof EntitySchema.Entities;
            _draft?: unknown;
            _origin?: unknown;
            _isDirty?: boolean;
            _isNew?: boolean;
        }) => {
            // If value is a entity collection, we need to clone it custom
            if (
                value?.__identifier__ &&
                typeof value.__identifier__ === 'function' &&
                value.__identifier__() === 'EntityCollection'
            ) {
                return new EntityCollection(
                    value.source!,
                    value.entity!,
                    // @ts-expect-error - we don't want to provide a context
                    {},
                    value.criteria === null ? value.criteria : Criteria.fromCriteria(value.criteria!),
                    // @ts-expect-error - value is an array inside a entity collection
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                    deepCloneWithEntity(Array.from(value)),
                    value.total,
                    value.aggregations,
                );
            }

            // If value is a entity, we need to clone it custom
            if (value?.__identifier__ && typeof value.__identifier__ === 'function' && value.__identifier__() === 'Entity') {
                return new Entity(
                    value.id!,
                    value._entityName!,
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                    deepCloneWithEntity(value._draft),
                    {
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                        originData: deepCloneWithEntity(value._origin),
                        isDirty: value._isDirty,
                        isNew: value._isNew,
                    },
                );
            }

            return undefined;
        },
    );
}

/* eslint-enable @typescript-eslint/no-explicit-any */
