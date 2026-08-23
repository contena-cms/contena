import type FilterFactoryData from 'src/core/data/filter-factory.data';

const FilterFactory = Contena.Classes._private.FilterFactory;

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default function initializeFilterFactory() {
    const filterFactory = new FilterFactory();

    Contena.Application.addServiceProvider('filterFactory', () => {
        return filterFactory as unknown as FilterFactoryData;
    });
}
