const ENTITY_MAPPING = {
    media: 'ct-media-media-item',
    media_folder: 'ct-media-folder-item',
};
/**
 * @private
 */
export default {
    template: `<component :is="mapEntity" v-bind="$props"><slot/></component>`,

    props: {
        item: {
            type: Object,
            required: true,
            validator(value) {
                return !!value.getEntityName();
            },
        },
    },

    computed: {
        mapEntity() {
            return ENTITY_MAPPING[this.item.getEntityName()];
        },
    },
};
