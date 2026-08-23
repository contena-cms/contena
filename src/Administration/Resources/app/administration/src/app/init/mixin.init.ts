import mixin from 'src/app/mixin';

const createdAppMixin = mixin();

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default function createAppMixin() {
    // eslint-disable-next-line @typescript-eslint/no-unsafe-return
    return createdAppMixin;
}
