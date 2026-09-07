import { EXPERIENCE_STUDIO_MAX_HISTORY_SIZE } from '../constant/experience-studio-history.constant';
import type { EditorHistoryEntry } from '../types/editor-history.types';
import type { ContentElementNode } from 'src/core/service/content-element.types';

const { cloneDeep } = Contena.Utils.object;

/**
 * @private
 * @ct-package discovery
 */
export function createEditorHistoryEntry(
    layout: ContentElementNode[],
    selectedElementId: string | null,
): EditorHistoryEntry {
    return {
        layout: cloneDeep(layout),
        selectedElementId,
    };
}

/**
 * @private
 * @ct-package discovery
 */
export function trimHistoryStack<T>(stack: T[], maxSize: number = EXPERIENCE_STUDIO_MAX_HISTORY_SIZE): void {
    while (stack.length > maxSize) {
        stack.shift();
    }
}
