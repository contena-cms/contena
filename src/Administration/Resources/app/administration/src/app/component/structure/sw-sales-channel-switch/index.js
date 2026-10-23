/**
 * @sw-package discovery
 */
import template from './sw-sales-channel-switch.html.twig';

const { Criteria } = Contena.Data;
const { debug } = Contena.Utils;

/**
 * @private
 * @description
 * Renders a sales channel switcher.
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-sales-channel-switch></sw-sales-channel-switch>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['change-sales-channel-id'],

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
        abortChangeFunction: {
            type: Function,
            required: false,
        },
        saveChangesFunction: {
            type: Function,
            required: false,
        },
        label: {
            type: String,
            required: false,
            default: '',
        },
        channelCriteria: {
            type: Object,
            required: false,
            default: () => {
                const criteria = new Criteria(1, 25);
                criteria.addSorting(Criteria.sort('name'));

                return criteria;
            },
        },
    },

    data() {
        return {
            channelId: '',
            lastSalesChannelId: '',
            newSalesChannelId: '',
            showUnsavedChangesModal: false,
        };
    },

    methods: {
        onChange(id) {
            this.channelId = id;
            this.newSalesChannelId = id;

            this.checkAbort();
        },
        checkAbort() {
            // Check if abort function exists und reset the select field if the change should be aborted
            if (typeof this.abortChangeFunction === 'function') {
                if (
                    this.abortChangeFunction({
                        oldSalesChannelId: this.lastSalesChannelId,
                        newSalesChannelId: this.channelId,
                    })
                ) {
                    this.showUnsavedChangesModal = true;
                    this.channelId = this.lastSalesChannelId;
                    this.$refs.channelSelect.loadSelected();
                    return;
                }
            }

            this.emitChange();
        },
        emitChange() {
            this.lastSalesChannelId = this.channelId;

            this.$emit('change-sales-channel-id', this.channelId);
        },
        onCloseChangesModal() {
            this.showUnsavedChangesModal = false;
            this.newSalesChannelId = '';
        },
        onClickSaveChanges() {
            let save = {};
            // Check if save function exists and wait for it before changing the channel
            if (typeof this.saveChangesFunction === 'function') {
                save = this.saveChangesFunction();
            } else {
                debug.warn('sw-sales-channel-switch', 'You need to implement an own save function to save the changes!');
            }
            return Promise.resolve(save).then(() => {
                this.changeToNewSalesChannel();
                this.onCloseChangesModal();
            });
        },
        onClickRevertUnsavedChanges() {
            this.changeToNewSalesChannel();
            this.onCloseChangesModal();
        },
        changeToNewSalesChannel(channelId) {
            if (channelId) {
                this.newSalesChannelId = channelId;
            }
            this.channelId = this.newSalesChannelId;
            this.newSalesChannelId = '';
            this.$refs.channelSelect.loadSelected();
            this.emitChange();
        },
    },
};
