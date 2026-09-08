/* eslint-disable ct-deprecation-rules/private-feature-declarations */
import { h } from 'vue';

const { Locale } = Contena;
const { debug } = Contena.Utils;

/** App-provided CMS blocks use the same generic content categories as native blocks. */
export const BLOCKS_CATEGORIES = ['text', 'image', 'video', 'text-image', 'sidebar', 'commerce', 'form'];

export default class AppCmsService {
    defaultBlockConfig = {
        prefix: 'ct-cms-block-',
        componentSuffix: '-component',
        previewComponentSuffix: '-preview-component',
    };

    blockStyles = '';

    constructor(appCmsBlocksService, vueAdapter) {
        this.appCmsBlocksService = appCmsBlocksService;
        this.vueAdapter = vueAdapter;

        return this.requestAppSystemBlocks().then((blocks) => {
            if (!blocks) return this;
            this.iterateCmsBlocks(blocks);
            this.injectStyleTag();
            return this;
        });
    }

    requestAppSystemBlocks() {
        return this.appCmsBlocksService.fetchAppBlocks();
    }

    iterateCmsBlocks(blocks) {
        blocks.forEach((block) => this.registerCmsBlock(block));
        return true;
    }

    registerCmsBlock(block) {
        if (!this.validateBlockCategory(block.category)) {
            debug.warn(this.constructor.name, `The category "${block.category}" is not a valid category.`);
            return false;
        }

        this.registerBlockSnippets(block.name, block.label);
        this.registerStyles(block);

        const component = this.createBlockComponent(block);
        const previewComponent = this.createBlockPreviewComponent(block);
        const config = this.createBlockConfiguration(block, component, previewComponent);

        Contena.Service('cmsService').registerCmsBlock(config);
        return config;
    }

    createBlockConfiguration(block, component, previewComponent) {
        return {
            name: `${block.name}${this.defaultBlockConfig.componentSuffix}`,
            label: `ct-app-system-cms.label-${block.name}`,
            category: block.category,
            slots: block.slots,
            defaultConfig: block.defaultConfig,
            component,
            previewComponent,
        };
    }

    createBlockComponent(block) {
        const componentName = `${this.defaultBlockConfig.prefix}${block.name}${this.defaultBlockConfig.componentSuffix}`;
        const component = {
            name: componentName,
            render() {
                const slotEntries = Object.entries(block.slots);
                if (slotEntries.every((entry) => !!entry[1].position)) {
                    slotEntries.sort((a, b) => a[1].position - b[1].position);
                }
                return h('div', { class: componentName }, slotEntries.map(([slotName]) => this.$slots[slotName]?.()));
            },
        };
        this.vueAdapter.buildAndCreateComponent(component);
        return component;
    }

    createBlockPreviewComponent(block) {
        const component = {
            name: `${this.defaultBlockConfig.prefix}${block.name}${this.defaultBlockConfig.previewComponentSuffix}`,
            template: block.template,
        };
        this.vueAdapter.buildAndCreateComponent(component);
        return component;
    }

    registerBlockSnippets(blockName, label) {
        return Object.keys(label).reduce((valid, localeKey) => {
            if (!Locale.getByName(localeKey)) {
                debug.warn(this.constructor.name, `The locale "${localeKey}" is not registered in Contena.Locale.`);
                return false;
            }
            Locale.extend(localeKey, { 'ct-app-system-cms': { [`label-${blockName}`]: label[localeKey] } });
            return valid;
        }, true);
    }

    validateBlockCategory(categoryName) {
        return BLOCKS_CATEGORIES.includes(categoryName);
    }

    setDefaultConfig(config) {
        this.defaultBlockConfig = { ...this.defaultBlockConfig, ...config };
        return true;
    }

    registerStyles(block) {
        if (!block.styles?.length) return false;
        this.blockStyles = `${this.blockStyles}${block.styles}`;
        return true;
    }

    injectStyleTag() {
        if (!this.blockStyles.length) return false;
        const tag = document.createElement('style');
        tag.setAttribute('type', 'text/css');
        tag.appendChild(document.createTextNode(this.blockStyles));
        document.head.appendChild(tag);
        return true;
    }
}
