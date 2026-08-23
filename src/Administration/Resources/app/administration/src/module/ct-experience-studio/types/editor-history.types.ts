import type { ContentElementNode } from './content-element.types';

/**
 * @private
 */
export interface EditorHistoryEntry {
    layout: ContentElementNode[];
    selectedElementId: string | null;
}
