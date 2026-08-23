import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';
import { nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { Criteria } = Contena.Data;
const { debounce } = Contena.Utils;

/**
 * @private
 */
export default {
    emits: ['search-term-change'],

    setup(props, { emit, expose }) {
        const { t } = useI18n();
        const {
            repository,
            searchTerm,
            isLoading,
            resultCollection,
            search: parentSearch,
            addItem: parentAddItem,
            loadData,
            resetActiveItem: parentResetActiveItem,
        } = Contena.Component.getExtensionParentSetup();

        const publicApi = createExtendableSetup(
            {
                name: 'ct-entity-tag-select',
                props,
            },
            () => {
                const tagExists = ref(true);

                const resetActiveItem = (position = 0) => {
                    parentResetActiveItem.value(position);
                };

                const filterSearchGeneratedTags = () => {
                    resultCollection.value = resultCollection.value.filter((entity) => {
                        return entity.id !== -1;
                    });
                };

                const checkTagExists = (term) => {
                    if (term.trim().length === 0) {
                        tagExists.value = true;
                        return Promise.resolve();
                    }

                    const criteria = new Criteria(1, 25);
                    criteria.addFilter(Criteria.equals('name', term));

                    return repository.value.search(criteria, props.context).then((response) => {
                        tagExists.value = response.total > 0;
                    });
                };

                const search = (term) => {
                    filterSearchGeneratedTags();

                    return Promise.all([
                        checkTagExists(searchTerm.value),
                        parentSearch.value(term),
                    ]).then(() => {
                        if (tagExists.value) {
                            return;
                        }

                        const newTag = repository.value.create(props.entityCollection.context, -1);
                        newTag.name = t('global.ct-tag-field.listItemAdd', { term: searchTerm.value }, 0);

                        resultCollection.value.unshift(newTag);
                        void nextTick(() => resetActiveItem());
                    });
                };

                const debouncedSearch = debounce(() => {
                    void search(searchTerm.value);
                }, 400);

                const onSearchTermChange = (term) => {
                    searchTerm.value = term;
                    emit('search-term-change', term);
                    debouncedSearch();
                };

                const createNewTag = () => {
                    const item = repository.value.create(props.entityCollection.context);
                    item.name = searchTerm.value;
                    isLoading.value = true;

                    return repository.value
                        .save(item, props.entityCollection.context)
                        .then(() => {
                            parentAddItem.value(item);

                            props.criteria.setPage(1);
                            props.criteria.setLimit(props.resultLimit);
                            props.criteria.setTerm('');
                            searchTerm.value = '';
                            resultCollection.value = null;

                            return loadData.value();
                        })
                        .then(() => {
                            resetActiveItem();
                        })
                        .finally(() => {
                            isLoading.value = false;
                        });
                };

                const addItem = (item) => {
                    if (item.id !== -1) {
                        parentAddItem.value(item);
                        return;
                    }

                    if (!isLoading.value) {
                        void createNewTag();
                    }
                };

                return {
                    public: {
                        tagExists,
                        resetActiveItem,
                        search,
                        addItem,
                        createNewTag,
                        checkTagExists,
                        filterSearchGeneratedTags,
                        debouncedSearch,
                        onSearchTermChange,
                    },
                };
            },
        );

        expose(publicApi);

        return publicApi;
    },
};
