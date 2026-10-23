/*
 * @sw-package inventory
 */

import template from './sw-blog-visibility-select.html.twig';

const { EntityCollection, Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['item-add'],

    props: {
        criteria: {
            type: Object,
            required: false,
            default(props) {
                const criteria = new Criteria(1, props.resultLimit);
                criteria.addSorting(Criteria.sort('name', 'ASC'));
                return criteria;
            },
        },
    },

    data() {
        return {
            defaultVisibility: 30,
        };
    },

    computed: {
        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        repository() {
            return this.repositoryFactory.create('channel');
        },

        associationRepository() {
            return this.repositoryFactory.create('blog_visibility');
        },
    },

    methods: {
        isSelected(item) {
            return this.currentCollection.some((entity) => {
                return entity.channelId === item.id;
            });
        },

        addItem(item) {
            // Remove when already selected
            if (this.isSelected(item)) {
                const associationEntity = this.currentCollection.find((entity) => {
                    return entity.channelId === item.id;
                });
                this.remove(associationEntity);

                return;
            }

            // Create new entity
            const newSalesChannelAssociation = this.associationRepository.create(this.entityCollection.context);
            newSalesChannelAssociation.blogId = this.blog.id;
            newSalesChannelAssociation.blogVersionId = this.blog.versionId;
            newSalesChannelAssociation.channelId = item.id;
            newSalesChannelAssociation.visibility = this.defaultVisibility;
            newSalesChannelAssociation.channel = item;

            this.$emit('item-add', item);

            const changedCollection = EntityCollection.fromCollection(this.currentCollection);
            changedCollection.add(newSalesChannelAssociation);

            this.emitChanges(changedCollection);
            this.onSelectExpanded();
        },
    },
};
