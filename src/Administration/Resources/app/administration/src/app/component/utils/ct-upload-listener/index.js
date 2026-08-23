import { UploadEvents } from 'src/core/service/api/media.api.service';

const { Context } = Contena;
const utils = Contena.Utils;

/**
 * @private
 */

function isDuplicationException(error) {
    return error.response?.data?.errors?.some((err) => {
        return err.code === 'CONTENT__MEDIA_DUPLICATED_FILE_NAME';
    });
}

/**
 * @public
 * @description
 * component that listens to mutations of the upload store and transforms them back into the vue.js event system.
 * @status ready
 * @event media-upload-add { UploadTask[]: data }
 * @event media-upload-finish { string: targetId }
 * @event media-upload-fail UploadTask UploadTask
 * @event media-upload-cancel UploadTask UploadTask
 * @example code-only
 * @component-example
 * <ct-upload-listener @ct-uploads-added="..."></ct-upload-listener>
 */
// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default {
    template: '<div style="display: none"></div>',

    inject: [
        'repositoryFactory',
        'mediaService',
    ],

    props: {
        uploadTag: {
            type: String,
            required: true,
        },

        autoUpload: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            id: utils.createId(),
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },
    },

    watch: {
        uploadTag(newVal, oldVal) {
            this.mediaService.removeListener(oldVal, this.convertStoreEventToVueEvent);
            this.mediaService.addListener(newVal, this.convertStoreEventToVueEvent);
        },
    },

    created() {
        this.createdComponent();
    },

    unmounted() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.mediaService.addListener(this.uploadTag, this.convertStoreEventToVueEvent);
        },

        destroyedComponent() {
            this.mediaService.removeListener(this.uploadTag, this.convertStoreEventToVueEvent);
        },

        convertStoreEventToVueEvent({ action, uploadTag, payload }) {
            if (this.uploadTag !== uploadTag) {
                return;
            }

            if (action === UploadEvents.UPLOAD_ADDED) {
                if (this.autoUpload === true) {
                    this.syncEntitiesAndRunUploads();
                    return;
                }

                this.$emit(UploadEvents.UPLOAD_ADDED, payload);
                return;
            }

            if (action === UploadEvents.UPLOAD_FINISHED) {
                this.$emit(UploadEvents.UPLOAD_FINISHED, payload);
                return;
            }

            if (action === UploadEvents.UPLOAD_FAILED) {
                if (isDuplicationException(payload?.error)) {
                    this.$emit(UploadEvents.UPLOAD_FAILED, payload);
                    return;
                }

                this.handleError(payload).then(() => {
                    this.$emit(UploadEvents.UPLOAD_FAILED, payload);
                });
            }

            if (action === UploadEvents.UPLOAD_CANCELED) {
                this.$emit(UploadEvents.UPLOAD_CANCELED, payload);
            }
        },

        async handleError(payload) {
            const updatedMedia = await this.mediaRepository.get(payload.targetId, Context.api);

            if (!updatedMedia.hasFile) {
                await this.mediaRepository.delete(updatedMedia.id, Context.api);
            }
        },

        syncEntitiesAndRunUploads() {
            this.mediaService.runUploads(this.uploadTag);
        },
    },
};
