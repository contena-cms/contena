/**
 * @public
 * @status ready
 * @description The <u>ct-media-replace</u> component extends the <u>ct-media-upload</u> component. It is
 * used in cases of replacing items rather than uploading them.
 * @example-type code-only
 * @component-example
 * <ct-media-replace
 *      :item-to-replace="mediaItem"
 *      variant="regular"
 * ></ct-media-replace>
 */
const { fileReader } = Contena.Utils;

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default {
    props: {
        itemToReplace: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            multiSelect: false,
        };
    },

    methods: {
        getMediaEntityForUpload() {
            return this.itemToReplace;
        },

        cleanUpFailure(mediaEntity, message) {
            this.createNotificationError({ message });
        },

        handlePresignedUpload(files) {
            const { extension } = fileReader.getNameAndExtensionFromFile(files[0]);

            this.mediaService.getListenerForTag(this.uploadTag).forEach((listener) => {
                listener(
                    this.mediaService._createUploadEvent('media-upload-add', this.uploadTag, {
                        data: [{ targetId: this.itemToReplace.id, extension, src: files[0] }],
                    }),
                );
            });
        },
    },
};
