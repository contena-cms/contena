/*
 * @sw-package inventory
 */

import template from './sw-blog-variant-modal.html.twig';
import './sw-blog-variant-modal.scss';

const { Mixin, Context } = Contena;
const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    emits: ['modal-close'],

    mixins: [Mixin.getByName('notification')],

    props: {
        // this is the parent blog entity from wich we will get all the variants
        blogEntity: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            blogVariants: [],
            currency: null,
            paginationLimit: 25,
            paginationPage: 1,
            toBeDeletedVariants: [],
            showDeleteModal: false,
            searchTerm: '',
            isDeleteButtonLoading: false,
            isDeletionOver: false,
            sortDirection: 'ASC',
            sortBy: 'blogNumber',
            isLoading: false,
            groups: [],
            filterOptions: [],
            includeOptions: [],
            filterWindowOpen: false,
            showBulkEditModal: false,
        };
    },

    computed: {
        modalTitle() {
            return this.$t('sw-blog.list.variantModalTitle', {
                blogName: this.blogEntity.translated.name,
            });
        },

        openMainProductText() {
            return this.$t('sw-blog.list.openMainProduct', {
                blogName: this.blogEntity.translated.name,
            });
        },

        blogRepository() {
            return this.repositoryFactory.create('blog');
        },

        blogMediaRepository() {
            return this.repositoryFactory.create('blog_media');
        },

        blogConfigurationRepository() {
            return this.repositoryFactory.create('blog_configurator_setting');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        groupRepository() {
            return this.repositoryFactory.create('property_group');
        },

        contextMenuEditText() {
            return this.acl.can('blog.editor') ? this.$t('global.default.edit') : this.$t('global.default.view');
        },

        filterCriteria() {
            if (this.includeOptions.length <= 0) {
                return [];
            }

            // Collect each selected option in a group
            // [
            //   {id: 'abc123', options: [...optionIds]},
            //   {id: 'def456', options: [...optionIds]},
            // ]
            const optionInGroups = this.includeOptions.reduce((result, option) => {
                const parentGroup = result.find((group) => group.id === option.groupId);

                // Push to group when array exists
                if (parentGroup) {
                    parentGroup.options.push(option.id);
                } else {
                    // otherwise create new group with the option
                    result.push({
                        id: option.groupId,
                        options: [option.id],
                    });
                }

                return result;
            }, []);

            return optionInGroups.map((group) => {
                return Criteria.equalsAny('blog.optionIds', group.options);
            });
        },

        blogVariantCriteria() {
            const criteria = new Criteria(this.paginationPage, this.paginationLimit);

            // this is the id of the main blog.
            const blogEntityId = this.blogEntity.id;
            criteria.addFilter(Criteria.equals('parentId', blogEntityId));

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            criteria.getAssociation('options').addAssociation('group');
            criteria.addAssociation('cover.media');
            criteria.addAssociation('media.media');
            criteria.addAssociation('tax');

            if (this.searchTerm) {
                // Split each word for search; drop empty entries from leading,
                // trailing or repeated spaces, otherwise they build a `contains`
                // filter with an empty value and the API rejects the whole query
                // with FRAMEWORK__INVALID_FILTER_QUERY.
                const terms = this.searchTerm.split(' ').filter((term) => term !== '');

                // Create query for each single word
                terms.forEach((term) => {
                    criteria.addQuery(Criteria.equals('blog.options.name', term), 3500);
                    criteria.addQuery(Criteria.contains('blog.options.name', term), 500);
                });
            }

            // AdminUser selected filters
            if (this.filterCriteria) {
                this.filterCriteria.forEach((cri) => {
                    criteria.addFilter(cri);
                });
            }

            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, true));

            return criteria;
        },

        gridColumns() {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    label: this.$t('sw-blog.list.columnName'),
                    routerLink: 'sw.blog.detail',
                    inlineEdit: 'string',
                    allowResize: true,
                },
                {
                    property: 'sales',
                    dataIndex: 'sales',
                    label: this.$t('sw-blog.list.columnSales'),
                    allowResize: true,
                    align: 'right',
                },
                {
                    property: 'price',
                    dataIndex: `price.${this.currency?.id || ''}.net`,
                    label: 'sw-blog.list.columnPrice',
                    allowResize: true,
                    width: 'calc(var(--scale-size-224) + var(--scale-size-26))',
                    inlineEdit: 'number',
                    align: 'right',
                },
                {
                    property: 'stock',
                    dataIndex: 'stock',
                    label: 'sw-blog.list.columnInStock',
                    allowResize: true,
                    inlineEdit: 'number',
                    align: 'right',
                },
                {
                    property: 'active',
                    dataIndex: 'active',
                    label: 'sw-blog.list.columnActive',
                    allowResize: true,
                    inlineEdit: 'boolean',
                    align: 'center',
                },
                {
                    property: 'blogNumber',
                    dataIndex: 'blogNumber',
                    label: 'sw-blog.list.columnProductNumber',
                    allowResize: true,
                    align: 'right',
                },
                {
                    property: 'media',
                    dataIndex: 'media',
                    label: this.$t('sw-blog.list.columnMedia'),
                    allowResize: true,
                    inlineEdit: true,
                    sortable: false,
                },
            ];
        },

        canBeDeletedCriteria() {
            const criteria = new Criteria(1, 25);
            const variantIds = this.toBeDeletedVariants.map((variant) => variant.id);
            criteria.addFilter(Criteria.equalsAny('canonicalProductId', variantIds));

            return criteria;
        },

        groupCriteria() {
            return new Criteria(1, 100);
        },

        selectedGroups() {
            // get groups for selected options
            const groupIds = this.blogEntity?.configuratorSettings.reduce((result, element) => {
                if (result.indexOf(element.option.groupId) < 0) {
                    result.push(element.option.groupId);
                }

                return result;
            }, []);

            return this.groups?.filter((group) => {
                return groupIds.indexOf(group.id) >= 0;
            });
        },

        filterOptionsListing() {
            // Prepare groups
            const groups = [...this.selectedGroups]
                .sort((a, b) => a.position - b.position)
                .map((group, index) => {
                    const children = this.getOptionsForGroup(group.id);

                    return {
                        id: group.id,
                        name: group.name,
                        childCount: children.length,
                        parentId: null,
                        afterId: index > 0 ? this.selectedGroups[index - 1].id : null,
                        storeObject: group,
                    };
                });

            // Prepare options
            const children = groups.reduce((result, group) => {
                const options = this.getOptionsForGroup(group.id);

                // Iterate for each group options
                const optionsForGroup = options
                    .sort((elementA, elementB) => {
                        return elementA.position - elementB.position;
                    })
                    .map((element, index) => {
                        const option = element.option;

                        // Get previous element
                        let afterId = null;
                        if (index > 0) {
                            afterId = options[index - 1].option.id;
                        }

                        return {
                            id: option.id,
                            name: option.name,
                            childCount: 0,
                            parentId: option.groupId,
                            afterId,
                            storeObject: element,
                        };
                    });

                return [...result, ...optionsForGroup];
            }, []);

            // Assign groups and children to order objects
            return [...groups, ...children];
        },

        stockColorVariantFilter() {
            return Contena.Filter.getByName('stockColorVariant');
        },
    },

    watch: {
        selectedGroups() {
            this.filterOptions = this.filterOptionsListing;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            return Promise.all([
                this.fetchProductMedias(),
                this.fetchProductConfiguration(),
                this.fetchProductVariants(),
                this.fetchSystemCurrency(),
                this.loadGroups(),
            ]).finally(() => {
                this.isLoading = false;
            });
        },

        fetchProductMedias() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('blogId', this.blogEntity.id));

            return this.blogMediaRepository.search(criteria).then((response) => {
                this.blogEntity.media = response;
            });
        },

        fetchProductConfiguration() {
            const criteria = new Criteria();
            criteria.addAssociation('option');
            criteria.addFilter(Criteria.equals('blogId', this.blogEntity.id));

            return this.blogConfigurationRepository.search(criteria).then((response) => {
                this.blogEntity.configuratorSettings = response;
            });
        },

        fetchSystemCurrency() {
            const systemCurrencyId = Contena.Context.app.systemCurrencyId;

            return this.currencyRepository.get(systemCurrencyId).then((response) => {
                this.currency = response;
            });
        },

        fetchProductVariants() {
            this.isLoading = true;

            return this.blogRepository
                .search(this.blogVariantCriteria)
                .then((response) => {
                    this.blogVariants = response;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        getDefaultPriceForVariant(variant) {
            if (!variant.price) {
                return this.defaultPrice;
            }

            const foundDefaultPrice = variant.price.find((price) => {
                return price.currencyId === this.defaultCurrency.id;
            });

            return foundDefaultPrice || this.defaultPrice;
        },

        onInheritanceRestore(variant, currency) {
            if (!variant.price) {
                return;
            }

            const foundVariantIndex = variant.price.findIndex((price) => {
                return price.currencyId === currency.id;
            });

            if (foundVariantIndex >= 0) {
                variant.price.splice(foundVariantIndex, 1);
            }

            if (variant.price.length <= 0) {
                variant.price = null;
            }
        },

        onInheritanceRemove(variant, currency) {
            if (!variant.price) {
                variant.price = [];
            }

            // create new price for selected currency
            const defaultPrice = this.blogEntity.price[0];
            const newPrice = {
                currencyId: currency.id,
                gross: defaultPrice.gross * currency.factor,
                linked: defaultPrice.linked,
                net: defaultPrice.net * currency.factor,
            };

            // add new price currency to variant
            variant.price.push(newPrice);
        },

        /**
         * Sorts variant options by their position. If the position is the same it sorts them by their name.
         * @param {Array} options
         * @return {Array}
         */
        sortOptions(options) {
            // making a local copy because using .sort directly on `option` causes an infinite loop
            const localOptions = [...options];

            return localOptions.sort((a, b) => {
                if (a.position === b.position) {
                    return a.name > b.name ? 1 : -1;
                }

                return a.position > b.position ? 1 : -1;
            });
        },

        /**
         * Returns a string with all options of an variant: "(color: black, size: xl)"
         * @param {object} variant
         * @returns {string}
         */
        buildVariantOptions(variant, seperator = ', ', ommitParenthesis = false, ommitOptionGroupName = false) {
            const options = variant.options;

            const sortedOptions = this.sortOptions(options);

            /* Creates following string: "color: black, size: xl".
             * The slice method removes the last two chars from the string wich are: ", ".
             */
            const formattedOptions = sortedOptions
                .reduce((accumulator, currentOption) => {
                    const optionValue = currentOption.translated.name;
                    const optionGroupName = currentOption.group.translated.name;

                    return accumulator.concat(
                        !ommitOptionGroupName ? optionGroupName : '',
                        !ommitOptionGroupName ? ': ' : '',
                        optionValue,
                        seperator,
                    );
                }, '')
                .slice(0, -seperator.length);

            return ommitParenthesis ? formattedOptions : `(${formattedOptions})`;
        },

        /**
         * Returns a string with the name of the variant and the options: T-Shirt (black, xl)
         * @param {object} variant
         * @returns {string}
         */
        buildVariantName(variant) {
            const options = this.buildVariantOptions(variant);
            const variantName = variant.translated.name || this.blogEntity.translated.name;

            return `${variantName} ${options}`;
        },

        /**
         * Returns the price of a variant. If the variant has no price it gets the price of the parent blog.
         * @param {object} variant
         * @returns {number}
         */
        getVariantPrice(variant) {
            const variantPrice = variant.price;

            return variantPrice ? variantPrice[0] : this.blogEntity.price[0];
        },

        onPageChange({ limit = 25, page = 1 }) {
            this.paginationLimit = limit;
            this.paginationPage = page;

            this.fetchProductVariants();
        },

        visitProduct(blogId) {
            this.$emit('modal-close');

            // using $nextTick to wait unit the dom has updated and the modal is closed
            this.$nextTick().then(() => {
                this.$router.push({
                    name: 'sw.blog.detail',
                    params: {
                        id: blogId,
                    },
                });
            });
        },

        getItemMedia(item) {
            if (item.cover) {
                return item.cover.media;
            }

            if (this.blogEntity.cover) {
                return this.blogEntity.cover.media;
            }

            return null;
        },

        deleteVariants() {
            this.isDeleteButtonLoading = true;

            const variantIds = this.toBeDeletedVariants.map((variant) => variant.id);
            const variantName = this.toBeDeletedVariants[0].translated.name || this.blogEntity.translated.name;
            const amount = variantIds.length;

            this.canVariantsBeDeleted().then((canBeDeleted) => {
                if (!canBeDeleted) {
                    this.isDeleteButtonLoading = false;
                    this.isDeletionOver = true;

                    this.createNotificationError({
                        message: this.$t(
                            'sw-blog.list.notificationVariantDeleteErrorCanonicalUrl',
                            {
                                variantName,
                            },
                            amount,
                        ),
                    });

                    return;
                }

                this.blogRepository
                    .syncDeleted(variantIds)
                    .then(() => {
                        this.createNotificationSuccess({
                            message: this.$t(
                                'sw-blog.list.notificationVariantDeleteSuccess',
                                {
                                    variantName,
                                    amount,
                                },
                                amount,
                            ),
                        });

                        this.$refs.variantGrid.resetSelection();

                        this.fetchProductVariants();
                    })
                    .catch(() => {
                        this.createNotificationError({
                            message: this.$t(
                                'sw-blog.list.notificationVariantDeleteError',
                                {
                                    variantName,
                                    amount,
                                },
                                amount,
                            ),
                        });
                    })
                    .finally(() => {
                        this.isDeleteButtonLoading = false;
                        this.isDeletionOver = true;
                    });
            });
        },

        async canVariantsBeDeleted() {
            const blogs = await this.blogRepository.search(this.canBeDeletedCriteria);

            return blogs.length === 0;
        },

        onInlineEditSave(editedVariant) {
            const variantName = this.buildVariantName(editedVariant);

            this.blogRepository.save(editedVariant).then(() => {
                this.createNotificationSuccess({
                    message: this.$t('sw-blog.list.notificationVariantSaveSuccess', { variantName }),
                });

                this.fetchProductVariants();
            });
        },

        onInlineEditCancel() {
            this.fetchProductVariants();
        },

        onClickBulkDelete() {
            const gridSelection = this.$refs.variantGrid.selection;
            this.toBeDeletedVariants = Object.values(gridSelection);

            this.showDeleteModal = true;
        },

        closeDeleteModal() {
            this.showDeleteModal = false;
            this.toBeDeletedVariants = [];
            this.isDeletionOver = false;
        },

        onDeleteVariant(variant) {
            this.toBeDeletedVariants.push(variant);

            this.showDeleteModal = true;
        },

        onSearchTermChange() {
            this.fetchProductVariants();
        },

        onSortColumn(column) {
            if (this.sortBy === column.dataIndex) {
                this.sortDirection = this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.sortBy = column.dataIndex;
            }

            this.fetchProductVariants();
        },

        getNoPermissionsTooltip(role, showOnDisabledElements = true) {
            return {
                showDelay: 300,
                message: this.$t('sw-privileges.tooltip.warning'),
                appearance: 'dark',
                showOnDisabledElements,
                disabled: this.acl.can(role),
            };
        },

        isMediaFieldInherited(variant) {
            if (variant.forceMediaInheritanceRemove) {
                return false;
            }

            if (variant.media) {
                return variant.media.length <= 0;
            }

            return !!variant.media;
        },

        onMediaInheritanceRestore(variant, isInlineEdit) {
            if (!isInlineEdit) {
                return;
            }

            variant.forceMediaInheritanceRemove = false;
            variant.coverId = null;

            variant.media.getIds().forEach((mediaId) => {
                variant.media.remove(mediaId);
            });
        },

        onMediaInheritanceRemove(variant, isInlineEdit) {
            if (!isInlineEdit) {
                return;
            }

            variant.forceMediaInheritanceRemove = true;
            this.blogEntity.media.forEach(({ id, mediaId, position, media }) => {
                const mediaItem = this.blogMediaRepository.create(Context.api);
                Object.assign(mediaItem, {
                    mediaId,
                    position,
                    blogId: this.blogEntity.id,
                    media,
                });

                if (this.blogEntity.coverId === id) {
                    variant.coverId = mediaItem.id;
                }

                variant.media.push(mediaItem);
            });
        },

        loadGroups() {
            return this.groupRepository.search(this.groupCriteria).then((searchResult) => {
                this.groups = searchResult;
            });
        },

        resetFilterOptions() {
            this.filterOptions = [];
            this.includeOptions = [];

            this.$nextTick(() => {
                this.filterOptions = this.filterOptionsListing;
                this.fetchProductVariants();
            });
        },

        filterOptionChecked(option) {
            if (option.checked) {
                // Remove from include list
                this.includeOptions.push({
                    id: option.id,
                    groupId: option.parentId,
                });
                return;
            }
            // Remove option from option list which is unchecked
            this.includeOptions = this.includeOptions.filter((includeOption) => includeOption.id !== option.id);
        },

        getOptionsForGroup(groupId) {
            return this.blogEntity?.configuratorSettings.filter((element) => {
                return !element.isDeleted && element.option.groupId === groupId;
            });
        },

        toggleFilterMenu() {
            this.filterWindowOpen = !this.filterWindowOpen;
        },

        toggleBulkEditModal() {
            this.showBulkEditModal = !this.showBulkEditModal;
        },

        async onEditItems() {
            await this.$nextTick();

            let includesDigital = '0';
            const digital = Object.values(this.$refs.variantGrid.selection).filter((blog) => blog.type === 'digital');
            if (digital.length > 0) {
                includesDigital = digital.filter((blog) => blog.isCloseout).length !== digital.length ? '1' : '2';
            }

            this.$router.push({
                name: 'sw.bulk.edit.blog',
                params: {
                    parentId: this.blogEntity.id,
                    includesDigital,
                },
            });
        },

        variantIsDigital(variant) {
            return variant.type && variant.type === 'digital';
        },
    },
};
