import { computed } from 'vue';
import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';

const { EntityCollection } = Contena.Data;

/**
 * @private
 */
export default {
    emits: ['item-add'],

    setup(props, { emit, expose }) {
        const { repositoryFactory, currentCollection, remove, emitChanges, onSelectExpanded } =
            Contena.Component.getExtensionParentSetup();

        const publicApi = createExtendableSetup(
            {
                name: 'ct-category-channel-multi-select',
                props,
            },
            () => {
                const channelRepository = computed(() => repositoryFactory.value.create('channel'));

                const isSelected = (item) => {
                    return currentCollection.value.some((entity) => entity.id === item.id);
                };

                const addItem = (item) => {
                    if (isSelected(item)) {
                        const associationEntity = currentCollection.value.find((entity) => entity.id === item.id);

                        remove.value(associationEntity);
                        return;
                    }

                    const changedCollection = EntityCollection.fromCollection(currentCollection.value);
                    changedCollection.add(item);

                    emit('item-add', item);
                    emitChanges.value(changedCollection);
                    onSelectExpanded.value();
                };

                return {
                    public: {
                        channelRepository,
                        isSelected,
                        addItem,
                    },
                };
            },
        );

        expose(publicApi);

        return publicApi;
    },
};
