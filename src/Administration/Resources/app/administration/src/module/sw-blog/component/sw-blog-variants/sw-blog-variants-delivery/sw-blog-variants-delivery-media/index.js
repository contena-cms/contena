/*
 * @sw-package inventory
 */

import template from './sw-blog-variants-delivery-media.html.twig';
import './sw-blog-variants-delivery-media.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'mediaService'],

    props: {
        blog: {
            type: Object,
            required: true,
        },

        selectedGroups: {
            type: Array,
            required: true,
        },
    },

    data() {
        return {
            activeGroup: {},
            isLoading: false,
        };
    },

    computed: {
        selectedGroupsSorted() {
            // prepare group sorting
            let sortedGroups = [];
            const selectedGroupsCopy = [...this.selectedGroups];

            // check if sorting exists on server
            if (
                this.blog.variantListingConfig.configuratorGroupConfig &&
                this.blog.variantListingConfig.configuratorGroupConfig.length > 0
            ) {
                // add server sorting to the sortedGroups
                sortedGroups = this.blog.variantListingConfig.configuratorGroupConfig.reduce((acc, configGroup) => {
                    const relatedGroup = selectedGroupsCopy.find((group) => group.id === configGroup.id);

                    if (relatedGroup) {
                        acc.push(relatedGroup);

                        // remove from original array
                        selectedGroupsCopy.splice(selectedGroupsCopy.indexOf(relatedGroup), 1);
                    }

                    return acc;
                }, []);
            }

            // add non sorted groups at the end of the sorted array
            sortedGroups = [...sortedGroups, ...selectedGroupsCopy];

            return sortedGroups;
        },

        optionColumns() {
            return [
                {
                    property: 'name',
                    label: 'sw-blog.variations.deliveryModal.mediaOptions',
                    rawData: true,
                },
                {
                    property: 'option.media',
                    label: 'sw-blog.variations.deliveryModal.media',
                    rawData: true,
                },
            ];
        },

        activeOptions() {
            return this.blog.configuratorSettings.filter((element) => {
                return !element.isDeleted && element.option.groupId === this.activeGroup.id;
            });
        },
    },

    watch: {
        activeGroup: {
            handler() {
                this.blog.variantListingConfig.configuratorGroupConfig.find((group) => {
                    return group.id === this.activeGroup.id;
                });
            },
        },
    },

    methods: {
        async onUploadsAdded({ data }) {
            if (data.length === 0) {
                return;
            }

            const uploadData = data[0];
            const relatedOption = this.activeOptions.find((option) => option.id === uploadData.uploadTag);

            this.isLoading = true;

            data.forEach((upload) => {
                relatedOption.mediaId = upload.targetId;
            });

            await this.mediaService.runUploads(uploadData.uploadTag);
        },

        async successfulUpload() {
            this.isLoading = false;

            this.$forceUpdate();
        },

        removeMedia(option) {
            option.mediaId = null;
        },

        setMedia(selection, optionId) {
            const relatedOption = this.activeOptions.find((option) => option.id === optionId);
            relatedOption.mediaId = selection[0].id;
        },

        onChangeGroupListing(value) {
            const existingIndex = this.blog.variantListingConfig.configuratorGroupConfig.findIndex(
                (group) => group.id === this.activeGroup.id,
            );

            if (existingIndex >= 0) {
                const existingConfig = this.blog.variantListingConfig.configuratorGroupConfig[existingIndex];

                this.blog.variantListingConfig.configuratorGroupConfig[existingIndex] = {
                    id: existingConfig.id,
                    expressionForListings: value,
                    representation: existingConfig.representation,
                };
            } else {
                this.blog.variantListingConfig.configuratorGroupConfig = [
                    ...this.blog.variantListingConfig.configuratorGroupConfig,
                    {
                        id: this.activeGroup.id,
                        expressionForListings: value,
                        representation: 'box',
                    },
                ];
            }
        },
    },
};
