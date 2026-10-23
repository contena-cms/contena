/*
 * @sw-package inventory
 */

import template from './sw-blog-variants-configurator-restrictions.html.twig';
import './sw-blog-variants-configurator-restrictions.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

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
            term: '',
            restrictionModalIsOpen: false,
            isLoading: true,
            groupsWithOptions: [],
            actualRestriction: {},
        };
    },

    computed: {
        restrictionColumns() {
            return [
                {
                    property: 'id',
                    label: 'sw-blog.variations.configuratorModal.restrictedCombinations',
                    rawData: true,
                },
            ];
        },

        actualRestrictionValueLength() {
            return this.actualRestriction.values.length;
        },

        filteredRestrictions() {
            if (!Array.isArray(this.blog.variantRestrictions)) {
                return [];
            }
            if (this.term.length <= 0) {
                return this.blog.variantRestrictions;
            }

            // get restriction names for ids
            const namedRestriction = this.blog.variantRestrictions.map((restriction) => {
                return this.getRestrictionsWithNaming(restriction.id);
            });

            // search for matching content
            const matchingIds = namedRestriction.reduce((acc, restriction) => {
                let termMatched = false;

                restriction.values.forEach((value) => {
                    if (value.group.toLowerCase().includes(this.term.toLowerCase())) {
                        termMatched = true;
                    }

                    value.options.find((option) => {
                        if (option.toLowerCase().includes(this.term.toLowerCase())) {
                            termMatched = true;
                            return true;
                        }
                        return false;
                    });
                });

                if (termMatched) {
                    acc.push(restriction.id);
                }
                return acc;
            }, []);

            // return only the restrictions with matching id
            return this.blog.variantRestrictions.filter((restriction) => matchingIds.indexOf(restriction.id) >= 0);
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.filterEmptyValues();

            // Load the groups with the related options
            this.groupsWithOptions = this.selectedGroups.map((group) => {
                return {
                    group: group,
                    options: this.getOptionsForGroupId(group.id),
                };
            });

            this.isLoading = false;
        },

        getOptionsForGroupId(groupId) {
            return this.blog.configuratorSettings.filter((element) => {
                return !element.isDeleted && element.option.groupId === groupId;
            });
        },

        getRestrictionsWithNaming(restrictionId) {
            // get the restriction from the param
            const cRestriction = this.blog.variantRestrictions.find((actRestriction) => {
                return actRestriction.id === restrictionId;
            });

            // return the restriction with names
            return {
                id: cRestriction.id,
                values: cRestriction.values.map((value) => {
                    const actualGroup = this.selectedGroups.find((group) => {
                        return group.id === value.group;
                    });

                    // When no group was found
                    if (!actualGroup) {
                        return {
                            group: '',
                            options: [],
                        };
                    }

                    const optionNames = value.options.reduce((acc, optionId) => {
                        const idOfOption = optionId.optionId ? optionId.optionId : optionId;

                        const actualOption = this.blog.configuratorSettings.find((sOption) => {
                            return idOfOption === sOption.optionId;
                        });

                        if (actualOption?.option) {
                            acc.push(actualOption.option.translated.name);
                        }
                        return acc;
                    }, []);

                    return {
                        group: actualGroup.translated.name,
                        options: optionNames,
                    };
                }),
            };
        },

        filterEmptyValues() {
            if (!Array.isArray(this.blog.variantRestrictions)) {
                return false;
            }
            this.blog.variantRestrictions = this.blog.variantRestrictions.filter((restriction) => {
                restriction.values = restriction.values.filter((value) => {
                    value.options = value.options.filter((option) => {
                        return this.blog.configuratorSettings.find((sOption) => {
                            return option === sOption.optionId;
                        });
                    });
                    return value.options.length > 0;
                });
                return restriction.values.length > 0;
            });
            return true;
        },

        addEmptyRestrictionCombination() {
            const uniqueId = String(new Date().valueOf()).split('').reverse().join('');
            this.actualRestriction = {
                id: uniqueId,
                values: [],
            };
            this.addEmptyRestriction();
            this.restrictionModalIsOpen = true;
        },

        addEmptyRestriction() {
            const uniqueId = String(new Date().valueOf()).split('').reverse().join('');
            const firstGroup = this.groupsWithOptions[0].group;
            this.actualRestriction.values.push({
                id: uniqueId,
                group: firstGroup.id,
                options: [],
            });
        },

        cancelAddRestriction() {
            this.actualRestriction = {};
            this.restrictionModalIsOpen = false;
        },

        saveAddRestriction() {
            this.actualRestriction.values = this.actualRestriction.values.filter((value) => {
                return Array.isArray(value.options) && value.options.length > 0;
            });

            if (this.actualRestriction.values.length === 0) {
                this.actualRestriction = {};
                this.restrictionModalIsOpen = false;
                return;
            }

            if (!Array.isArray(this.blog.variantRestrictions)) {
                this.blog.variantRestrictions = [];
            }

            const exists = this.blog.variantRestrictions.some((restriction) => {
                return restriction.id === this.actualRestriction.id;
            });

            if (!exists) {
                this.blog.variantRestrictions.push(this.actualRestriction);
            }

            this.actualRestriction = {};
            this.restrictionModalIsOpen = false;
        },

        editRestrictionCombination(restriction) {
            this.actualRestriction = restriction;
            this.restrictionModalIsOpen = true;
        },

        deleteRestrictionCombination(deleteRestriction) {
            this.blog.variantRestrictions = this.blog.variantRestrictions.filter((restriction) => {
                return restriction.id !== deleteRestriction.id;
            });
        },

        deleteRestriction(deleteRestriction) {
            this.actualRestriction.values = this.actualRestriction.values.filter((restriction) => {
                return restriction !== deleteRestriction;
            });
        },
    },
};
