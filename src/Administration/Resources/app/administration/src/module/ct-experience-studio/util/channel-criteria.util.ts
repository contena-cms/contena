import type CriteriaType from 'src/core/data/criteria.data';

const { Criteria } = Contena.Data;

const WEB_CHANNEL_TYPE_ID = '8a243080f92e4c719546314b577cf82b';

/**
 * @private
 */
export function getFrontendChannelCriteria(limit = 25): CriteriaType {
    return new Criteria(1, limit)
        .addFilter(Criteria.equals('typeId', WEB_CHANNEL_TYPE_ID))
        .addSorting(Criteria.sort('name', 'ASC'));
}
