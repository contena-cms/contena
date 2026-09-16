/**
 * @private
 *
 * Development-only native block inspector support.
 *
 * When the inspector is enabled, every element that starts a native `<ct-block>` is marked with
 * `data-ct-block`. The Vue devtools plugin reads these markers to list, highlight and pick blocks.
 * Marking is opt-in, read once from localStorage at boot, and never enabled in production builds.
 */

/**
 * Attribute carrying the block names of the element, innermost first, separated by a space.
 * Query the DOM with `[data-ct-block~="block_name"]`.
 *
 * @private
 */
export const BLOCK_MARKER_ATTRIBUTE = 'data-ct-block';

/**
 * `localStorage` key that turns the block markers on for the next boot.
 *
 * @private
 */
export const BLOCK_INSPECTOR_STORAGE_KEY = 'ct-admin-block-inspector';

/**
 * Metadata the inspector shows for a native block.
 *
 * @private
 */
export type InspectedBlock = {
    name: string;
    component: string;
    kind: 'native';
};

const inspectedBlocks = new Map<string, InspectedBlock>();

let enabledState: boolean | null = null;

function readStoredFlag(): boolean {
    if (process.env.NODE_ENV === 'production') {
        return false;
    }

    try {
        return globalThis.localStorage?.getItem(BLOCK_INSPECTOR_STORAGE_KEY) === 'true';
    } catch {
        return false;
    }
}

/**
 * Whether block markers are rendered in this session. Read once from `localStorage` at boot.
 *
 * @private
 */
export function isBlockInspectorEnabled(): boolean {
    if (enabledState === null) {
        enabledState = readStoredFlag();
    }

    return enabledState;
}

/**
 * Persists the flag for the next boot and applies it to this session right away.
 *
 * Blocks rendered before the call keep their state, so the devtools reload the page after flipping
 * the flag.
 *
 * @private
 */
export function setBlockInspectorEnabled(enabled: boolean): void {
    enabledState = enabled;

    try {
        if (enabled) {
            globalThis.localStorage?.setItem(BLOCK_INSPECTOR_STORAGE_KEY, 'true');
        } else {
            globalThis.localStorage?.removeItem(BLOCK_INSPECTOR_STORAGE_KEY);
        }
    } catch {
        // Storage can be unavailable; the in-memory state is enough for this session.
    }
}

/**
 * Records where a block comes from. The first registration wins.
 *
 * @private
 */
export function registerInspectedBlock(block: InspectedBlock): void {
    if (inspectedBlocks.has(block.name)) {
        return;
    }

    inspectedBlocks.set(block.name, block);
}

/**
 * Metadata of one block, or undefined for a block that was never rendered.
 *
 * @private
 */
export function getInspectedBlock(name: string): InspectedBlock | undefined {
    return inspectedBlocks.get(name);
}

/**
 * All blocks seen so far.
 *
 * @private
 */
export function getInspectedBlocks(): ReadonlyMap<string, InspectedBlock> {
    return inspectedBlocks;
}

/**
 * Drops the enabled flag and every registration. Meant for tests.
 *
 * @private
 */
export function resetBlockInspector(): void {
    inspectedBlocks.clear();
    enabledState = null;
}

/**
 * Names in a marker value.
 *
 * @private
 */
export function parseBlockMarker(value: string | null | undefined): string[] {
    return (value ?? '').split(/\s+/).filter((name) => name.length > 0);
}

/**
 * Adds a block name to a marker value, keeping existing names and their order.
 *
 * @private
 */
export function mergeBlockMarker(existing: string | null | undefined, blockName: string): string {
    const names = parseBlockMarker(existing);

    if (!names.includes(blockName)) {
        names.push(blockName);
    }

    return names.join(' ');
}
