import { Fragment, type VNode } from 'vue';
import type { TabItem } from '@contena/meteor-component-library/dist/esm/MtTabs';

/** @private */
export type TabItemClickHandler = (() => void) | Array<() => void> | undefined;

/** @private */
export type TabItemHandlers = {
    isTabItem: (item: VNode) => boolean;
    createTabItem: (item: VNode) => TabItem;
};

/** @private */
export function isFragment(item: VNode): boolean {
    return item.type === Fragment || (typeof item.type === 'symbol' && item.type.toString() === 'Symbol(v-fgt)');
}

/** @private */
export function getTextFromSlotItem(slotItem: VNode): string {
    if (typeof slotItem.children === 'string') {
        return slotItem.children;
    }

    if (Array.isArray(slotItem.children)) {
        const children = slotItem.children as VNode[];

        return children.map((child): string => getTextFromSlotItem(child)).join('');
    }

    return '';
}

/** @private */
export function triggerTabItemClick(clickHandler: TabItemClickHandler): void {
    if (Array.isArray(clickHandler)) {
        clickHandler.forEach((handler) => handler());
        return;
    }

    if (typeof clickHandler === 'function') {
        clickHandler();
    }
}

/** @private */
export function getTabItemsFromSlotContent(slotContent: VNode[], handlers: TabItemHandlers): TabItem[] {
    return slotContent.reduce<TabItem[]>((items, item) => {
        if (isFragment(item)) {
            const children = Array.isArray(item.children) ? (item.children as VNode[]) : [];

            return [
                ...items,
                ...getTabItemsFromSlotContent(children, handlers),
            ];
        }

        if (!handlers.isTabItem(item)) {
            return items;
        }

        return [
            ...items,
            handlers.createTabItem(item),
        ];
    }, []);
}
