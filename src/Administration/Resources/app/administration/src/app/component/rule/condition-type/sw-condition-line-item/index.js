import template from './sw-condition-line-item.html.twig';
import './sw-condition-line-item.scss';

const { EntityCollection, Criteria } = Contena.Data;

/**
 * @public
 * @sw-package fundamentals@after-sales
 * @description Condition for the LineItemRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item :condition="condition" :level="0"></sw-condition-line-item>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            blogs: null,
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('multiStore');
        },

        blogRepository() {
            return this.repositoryFactory.create('blog');
        },

        blogIds: {
            get() {
                this.ensureValueExist();
                return this.condition.value.identifiers || [];
            },
            set(identifiers) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, identifiers };
            },
        },

        blogCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('options.group');

            return criteria;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed
         */
        resultCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('options.group');

            return criteria;
        },

        blogContext() {
            return { ...Contena.Context.api, inheritance: true };
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.blogs = new EntityCollection(
                this.blogRepository.route,
                this.blogRepository.entityName,
                this.blogContext,
            );

            if (this.blogIds.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria(1, 25);
            criteria.addAssociation('options.group');
            criteria.setIds(this.blogIds);

            return this.blogRepository.search(criteria, this.blogContext).then((blogs) => {
                this.blogs = blogs;
            });
        },

        setIds(blogCollection) {
            this.blogIds = blogCollection.getIds();
            this.blogs = blogCollection;
        },
    },
};
