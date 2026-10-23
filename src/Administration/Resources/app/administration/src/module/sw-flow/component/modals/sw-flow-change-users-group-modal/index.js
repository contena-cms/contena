import template from './sw-flow-change-user-group-modal.html.twig';

const { Component, Store } = Contena;
const { Criteria } = Contena.Data;
const { mapState } = Component.getComponentHelper();
const { ContenaError } = Contena.Classes;

/**
 * @private
 * @sw-package after-sales
 */
export default {
    template,

    inject: ['repositoryFactory'],

    emits: ['modal-close', 'process-finish'],

    props: {
        sequence: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            userGroupId: '',
            fieldError: null,
        };
    },

    computed: {
        userGroupRepository() {
            return this.repositoryFactory.create('user_group');
        },

        userGroupCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        ...mapState(() => Store.get('swFlow'), ['userGroups']),
    },

    watch: {
        userGroupId(value) {
            if (value && this.fieldError) {
                this.fieldError = null;
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.userGroupId = this.sequence?.config?.userGroupId || '';

            if (!this.userGroups.length) {
                this.userGroupRepository.search(this.userGroupCriteria).then((data) => {
                    Contena.Store.get('swFlow').userGroups = data;
                });
            }
        },

        onClose() {
            this.$emit('modal-close');
        },

        onAddAction() {
            if (!this.userGroupId) {
                this.fieldError = new ContenaError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });

                return;
            }

            const sequence = {
                ...this.sequence,
                config: {
                    userGroupId: this.userGroupId,
                },
            };

            this.$emit('process-finish', sequence);
        },
    },
};
