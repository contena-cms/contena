/*
 * @sw-package inventory
 */

import template from './sw-blog-variants-overview.html.twig';
import './sw-blogs-variants-overview.scss';

const { Mixin, Context } = Contena;
const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'feature',
        'mediaService',
    ],

    emits: ['generator-open', 'delivery-open', 'variants-finish-update'],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('listing')],

    props: {
        blogEntity: {
            type: Object,
            required: true,
        },

        selectedGroups: {
            type: Array,
            required: true,
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, use `blogType` prop instead
         */
        blogStates: {
            type: Array,
            required: false,
            default: () => ['all'],
        },

        blogType: {
            type: String,
            required: false,
            default: 'all',
        },
    },

    data() {
        return {
            sortBy: 'name',
            sortDirection: 'ASC',
            showDeleteModal: false,
            modalLoading: false,
            priceEdit: false,
            filterOptions: [],
            activeFilter: [],
            includeOptions: [],
            filterWindowOpen: false,
            showBulkEditModal: false,
            toBeDeletedVariantIds: [],
            blogDownloadFolderId: null,
        };
    },

    computed: {
        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        currencies() {
            return Contena.Store.get('swProductDetail').currencies;
        },

        taxes() {
            return Contena.Store.get('swProductDetail').taxes;
        },

        variants() {
            return Contena.Store.get('swProductDetail').variants;
        },

        isLoading() {
            return Contena.Store.get('swProductDetail').isLoading;
        },

        defaultPrice() {
            return Contena.Store.get('swProductDetail').defaultPrice;
        },

        defaultCurrency() {
            return Contena.Store.get('swProductDetail').defaultCurrency;
        },

        blogTaxRate() {
            return Contena.Store.get('swProductDetail').blogTaxRate;
        },

        blogRepository() {
            return this.repositoryFactory.create('blog');
        },

        blogMediaRepository() {
            return this.repositoryFactory.create(this.blog.media.entity);
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        blogDownloadRepository() {
            return this.repositoryFactory.create('blog_download');
        },

        variantColumns() {
            const columns = [
                {
                    property: 'name',
                    label: this.$t('sw-blog.variations.generatedListColumnVariation'),
                    allowResize: true,
                },
                ...this.currencyColumns,
                {
                    property: 'sales',
                    dataIndex: 'sales',
                    label: this.$t('sw-blog.list.columnSales'),
                    allowResize: true,
                    align: 'right',
                },
                {
                    property: 'stock',
                    label: this.$t('sw-blog.variations.generatedListColumnStock'),
                    allowResize: true,
                    inlineEdit: 'number',
                    width: 'calc(var(--scale-size-80) + var(--scale-size-40) + var(--scale-size-6))',
                    align: 'right',
                },
                {
                    property: 'blogNumber',
                    label: this.$t('sw-blog.variations.generatedListColumnProductNumber'),
                    allowResize: true,
                    inlineEdit: 'string',
                    width: 'calc(var(--scale-size-128) + var(--scale-size-22))',
                },
                {
                    property: 'media',
                    label: this.$t('sw-blog.detailBase.cardTitleMedia'),
                    allowResize: true,
                    inlineEdit: true,
                    sortable: false,
                },
                {
                    property: 'active',
                    label: this.$t('sw-blog.variations.generatedListColumnActive'),
                    allowResize: true,
                    inlineEdit: 'boolean',
                    align: 'center',
                },
            ];

            // adding download files to second last index
            if (this.blogType === 'digital') {
                columns.splice(columns.length - 1, 0, {
                    property: 'downloads',
                    label: this.$t('sw-blog.variations.generatedListColumnDownload'),
                    allowResize: true,
                    inlineEdit: true,
                    sortable: false,
                });
            }
            return columns;
        },

        currencyColumns() {
            if (!this.currencies || !Array.isArray(this.currencies)) {
                return [];
            }

            return [...this.currencies]
                .sort((_a, b) => {
                    return b.isSystemDefault ? 1 : -1;
                })
                .map((currency) => {
                    return {
                        property: `price.${currency.id}.net`,
                        label: currency.translated.name || currency.name,
                        visible: currency.isSystemDefault,
                        allowResize: true,
                        primary: false,
                        rawData: false,
                        inlineEdit: 'number',
                        width: 'calc(var(--scale-size-224) + var(--scale-size-26))',
                    };
                });
        },

        canBeDeletedCriteria() {
            const criteria = new Criteria(1, 25);
            const variantIds = this.toBeDeletedVariantIds.map((variant) => variant.id);
            criteria.addFilter(Criteria.equalsAny('canonicalProductId', variantIds));

            return criteria;
        },
    },

    watch: {
        selectedGroups: {
            immediate: true,
            handler() {
                if (!this.selectedGroups || this.selectedGroups.length === 0) {
                    return;
                }

                this.getFilterOptions();
            },
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        blogStates() {
            this.getList();
        },

        blogType() {
            this.getList();
        },

        'blog.id': {
            handler() {
                this.getList();
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.blogDownloadFolderId = await this.mediaService.getDefaultFolderId('blog_download');
        },

        removeFile(fileName, item) {
            if (item.downloads.length === 1) {
                return;
            }

            item.downloads = item.downloads.filter(
                (download) => `${download.media.fileName}.${download.media.fileExtension}` !== fileName,
            );

            this.blogRepository.save(item);
        },

        mediaExists(files, targetId) {
            return files.some(({ id }) => {
                return id === targetId;
            });
        },

        successfulUpload(event, item) {
            this.mediaRepository.get(event.targetId, Context.api).then((media) => {
                if (this.mediaExists(this.getDownloadsSource(item), event.targetId)) {
                    return;
                }

                const newDownload = this.blogDownloadRepository.create(Context.api);
                newDownload.mediaId = event.targetId;
                newDownload.blogId = item.id;
                newDownload.media = media;

                Contena.Store.get('swProductDetail').variants = this.variants.map((variant) => {
                    if (variant.id === item.id) {
                        variant.downloads.push(newDownload);
                        this.blogRepository.save(variant);
                    }
                    return variant;
                });
            });
        },

        getDownloadsSource(item) {
            if (!item.downloads) {
                return [];
            }

            return item.downloads.map((download) => {
                return download.media;
            });
        },

        getList() {
            // Promise needed for inline edit error handling
            return new Promise((resolve) => {
                if (!this.blog?.id || this.blog.parentId) {
                    return;
                }

                Contena.Store.get('swProductDetail').setLoading(['variants', true]);

                // Get criteria for search and for option sorting
                const searchCriteria = new Criteria(1, 25);

                // Criteria for Search
                searchCriteria.setTotalCountMode(1);
                searchCriteria
                    .setPage(this.page)
                    .setLimit(this.limit)
                    .addFilter(Criteria.equals('blog.parentId', this.blog.id));

                if (this.blogType !== 'all') {
                    searchCriteria.addFilter(Criteria.equals('type', this.blogType));
                }

                searchCriteria.getAssociation('media').addSorting(Criteria.sort('position'));
                searchCriteria.addAssociation('media.media');

                searchCriteria
                    .getAssociation('options')
                    .addSorting(Criteria.sort('groupId'))
                    .addSorting(Criteria.sort('id'));

                if (this.blogType === 'digital') {
                    searchCriteria.addAssociation('downloads.media');
                }

                // Add search term
                this.buildSearchQuery(searchCriteria);

                // AdminUser selected filters
                if (this.getFilterCriteria()) {
                    this.getFilterCriteria().forEach((criteria) => {
                        searchCriteria.addFilter(criteria);
                    });
                }

                // check for other sort values
                if (this.sortBy === 'name') {
                    searchCriteria.addSorting(Criteria.sort('blog.options.name', this.sortDirection, true));
                } else {
                    searchCriteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
                }

                if (this.sortBy !== 'blogNumber') {
                    searchCriteria.addSorting(Criteria.sort('blog.blogNumber', this.sortDirection));
                }

                // Start search
                this.blogRepository.search(searchCriteria).then((res) => {
                    this.total = res.total;
                    Contena.Store.get('swProductDetail').variants = res;
                    Contena.Store.get('swProductDetail').setLoading(['variants', false]);
                    this.$emit('variants-finish-update', this.variants);
                    resolve();
                });
            });
        },

        buildSearchQuery(criteria) {
            // Normalize the term: leading/trailing whitespace must not leak into the
            // query. Otherwise the split below yields empty entries that build a
            // `contains` filter with an empty value and the API rejects the whole
            // query with FRAMEWORK__INVALID_FILTER_QUERY.
            const term = this.term ? this.term.trim() : '';
            if (!term) {
                return criteria;
            }

            // Split each word for search; empty entries from repeated spaces are dropped.
            const terms = term.split(' ').filter((word) => word !== '');

            // Create query for each single word
            terms.forEach((word) => {
                criteria.addQuery(Criteria.equals('blog.options.name', word), 3500);
                criteria.addQuery(Criteria.contains('blog.options.name', word), 500);
            });
            criteria.addQuery(Criteria.contains('blog.blogNumber', term), 5000);

            // return the input
            return criteria;
        },

        getFilterOptions() {
            // Prepare groups
            const groups = [...this.selectedGroups]
                .sort((a, b) => a.position - b.position)
                .map((group, index) => {
                    const children = this.getOptionsForGroup(group.id);

                    return {
                        id: group.id,
                        name: group.translated.name || group.name,
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
                            name: option.translated.name || option.name,
                            childCount: 0,
                            parentId: option.groupId,
                            afterId,
                            storeObject: element,
                        };
                    });

                return [...result, ...optionsForGroup];
            }, []);

            // Assign groups and children to order objects
            this.filterOptions = [...groups, ...children];
        },

        resetFilterOptions() {
            this.filterOptions = [];
            this.includeOptions = [];

            this.$nextTick(() => {
                this.getFilterOptions();
                this.getList();
            });
        },

        filterOptionChecked(option) {
            if (option.checked) {
                // Remove from include list
                this.includeOptions.push({
                    id: option.id,
                    groupId: option.parentId,
                });
            } else {
                // Add to include option list
                this.includeOptions = this.includeOptions.filter((includeOption) => includeOption.id !== option.id);
            }
        },

        getFilterCriteria() {
            if (this.includeOptions.length <= 0) {
                return false;
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

        getOptionsForGroup(groupId) {
            return this.blog.configuratorSettings.filter((element) => {
                return !element.isDeleted && element.option.groupId === groupId;
            });
        },

        isPriceFieldInherited(variant, currency) {
            if (!variant.price) {
                return true;
            }

            const foundVariant = variant.price.find((price) => {
                return price.currencyId === currency.id;
            });

            return !foundVariant;
        },

        isActiveFieldInherited(variant) {
            return variant.active === null;
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

        onInheritanceRestore(variant, currency) {
            if (!variant.price) {
                return;
            }

            const foundVariantIndex = variant.price.findIndex((price) => {
                return price.currencyId === currency.id;
            });

            if (foundVariantIndex >= 0) {
                delete variant.price[foundVariantIndex];
            }

            if (variant.price.length <= 0 || Object.keys(variant.price).length <= 0) {
                variant.price = null;
            }
        },

        onActiveInheritanceRestore(variant) {
            variant.active = null;
        },

        onActiveInheritanceRemove(variant) {
            variant.active = true;
        },

        onInheritanceRemove(variant, currency) {
            if (!variant.price) {
                variant.price = [];
            }

            // remove inheritance on default currency variant
            if (!currency.isSystemDefault) {
                this.onInheritanceRemove(variant, this.defaultCurrency);
            }

            // create new price for selected currency
            const defaultPrice = this.getDefaultPriceForVariant(variant);
            const newPrice = {
                currencyId: currency.id,
                gross: defaultPrice.gross * currency.factor,
                linked: defaultPrice.linked,
                net: defaultPrice.net * currency.factor,
            };

            // add new price currency to variant
            variant.price.push(newPrice);
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
            this.blog.media.forEach(({ id, mediaId, position, media }) => {
                const blogMedia = this.blogMediaRepository.create(Context.api);
                Object.assign(blogMedia, {
                    mediaId,
                    position,
                    blogId: this.blog.id,
                    media,
                });
                if (this.blog.coverId === id) {
                    variant.coverId = blogMedia.id;
                }

                variant.media.push(blogMedia);
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

        onVariationDelete(item) {
            this.showDeleteModal = true;

            this.toBeDeletedVariantIds.push(item);
        },

        onInlineEditSave(variation) {
            // check for changes
            if (!this.blogRepository.hasChanges(variation)) {
                return;
            }

            // get blog name
            const blogName = variation.options.reduce((acc, option, index) => {
                return `${acc}${index > 0 ? ' - ' : ''}${option.translated.name}`;
            }, '');

            this.blogRepository
                .save(variation)
                .then(() => {
                    // create success notification
                    const titleSaveSuccess = this.$t('global.default.success');
                    const messageSaveSuccess = this.$t(
                        'sw-blog.detail.messageSaveSuccess',
                        {
                            name: blogName,
                        },
                        0,
                    );

                    this.createNotificationSuccess({
                        title: titleSaveSuccess,
                        message: messageSaveSuccess,
                    });

                    // update items
                    this.getList();
                })
                .catch(() => {
                    // create error notification
                    const titleSaveError = this.$t('global.default.error');
                    const messageSaveError = this.$t(
                        'global.notification.notificationSaveErrorMessageRequiredFieldsInvalid',
                    );

                    this.createNotificationError({
                        title: titleSaveError,
                        message: messageSaveError,
                    });
                });
        },

        onInlineEditCancel() {
            this.getList();
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
            this.toBeDeletedVariantIds = [];
        },

        onConfirmDelete() {
            this.modalLoading = true;
            this.showDeleteModal = false;
            const variantIds = this.toBeDeletedVariantIds.map((variant) => variant.id);

            this.canVariantBeDeleted().then((canBeDeleted) => {
                if (!canBeDeleted) {
                    this.modalLoading = false;
                    this.toBeDeletedVariantIds = [];

                    this.createNotificationError({
                        message: this.$t('sw-blog.variations.generatedListMessageDeleteErrorCanonicalUrl'),
                    });

                    return;
                }

                this.updateVariantListingConfig(variantIds);

                this.blogRepository
                    .syncDeleted(variantIds)
                    .then(() => {
                        this.modalLoading = false;
                        this.toBeDeletedVariantIds = [];

                        this.createNotificationSuccess({
                            message: this.$t('sw-blog.variations.generatedListMessageDeleteSuccess'),
                        });

                        this.$refs.variantGrid.resetSelection();
                        this.getList();
                    })
                    .catch(() => {
                        this.modalLoading = false;
                        this.toBeDeletedVariantIds = [];

                        this.createNotificationError({
                            message: this.$t('sw-blog.variations.generatedListMessageDeleteError'),
                        });
                    });
            });
        },

        async canVariantBeDeleted() {
            const blogs = await this.blogRepository.search(this.canBeDeletedCriteria);

            return blogs.length === 0;
        },

        onOptionEdit(variant) {
            if (variant?.id) {
                this.$router.push({
                    name: 'sw.blog.detail',
                    params: {
                        id: variant.id,
                    },
                });
            }
        },

        isPriceEditing(value) {
            this.priceEdit = value;
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
                    parentId: this.blog.id,
                    includesDigital,
                },
            });
        },

        onClickBulkDelete() {
            const gridSelection = this.$refs.variantGrid.selection;
            this.toBeDeletedVariantIds = Object.values(gridSelection);

            this.showDeleteModal = true;
        },

        variantIsDigital(variant) {
            return this.blogType === 'all' && variant.type === 'digital';
        },

        updateVariantListingConfig(variantIds) {
            if (variantIds.length === this.variants.length) {
                this.blogEntity.variantListingConfig = null;
            }

            const mainVariantId = this.blogEntity.variantListingConfig?.mainVariantId;
            if (mainVariantId && variantIds.includes(mainVariantId)) {
                this.blogEntity.variantListingConfig.mainVariantId = null;
                this.blogEntity.variantListingConfig.displayParent =
                    this.blogEntity.variantListingConfig.displayParent === true;
            }

            this.blogRepository.save(this.blogEntity);
        },
    },
};
