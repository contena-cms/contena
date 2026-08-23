import './ct-media-quickinfo-metadata-item.scss';

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default {
    template: `
        <dt :class="$attrs.class" class="ct-media-quickinfo-metadata-item__term">
            {{ this.labelName }}:
        </dt>
        <dd :class="$attrs.class"  class="ct-media-quickinfo-metadata-item__description">
            <slot/>
        </dd>
    `,

    props: {
        labelName: {
            required: true,
            type: String,
        },
    },
};
