/**
 * Normalizes parsed SFC script blocks into Contena setup metadata.
 *
 * The transform derives base/override mode and component name from the filename convention so later
 * stages can work with a single block shape instead of repeating path parsing.
 */

import type { ScriptBlock } from './sfc-script-block';

type ContenaSetupMode = 'base' | 'override';

type ContenaSetupTemplate = {
    content: string;
    contentStart: number;
};

/**
 * Represents a `<script setup>` block plus the Contena component identity inferred for it.
 *
 * Base files use `<name>.vue` or `index.vue`; override files use `<name>.override.vue` or
 * `index.override.vue`. The component name is what runtime registration and overrides share.
 */
type ContenaSetupBlock = ScriptBlock & {
    mode: ContenaSetupMode;
    componentName: string;
    lang: string | null;
    template: ContenaSetupTemplate | null;
};

type InferredContenaSetup = {
    mode: ContenaSetupMode;
    componentName: string;
};

/** `ct-thing.vue?vue&type=script` -> `ct-thing.vue` */
function stripFilenameQuery(filename: string): string {
    // `String.split(_, 1)` always yields at least one element, so `[0]` is never undefined here.
    return filename.split(/[?#]/, 1)[0];
}

/** `src/app/ct-thing.vue` -> `ct-thing.vue` */
function basename(filename: string): string {
    const parts = stripFilenameQuery(filename).replace(/\\/g, '/').split('/').filter(Boolean);

    return parts[parts.length - 1] ?? filename;
}

/** `src/app/ct-thing/index.vue` -> `ct-thing` */
function parentDirectoryName(filename: string): string {
    const parts = stripFilenameQuery(filename).replace(/\\/g, '/').split('/').filter(Boolean);

    if (parts.length < 2) {
        return basename(filename);
    }

    return parts[parts.length - 2] ?? basename(filename);
}

/**
 * Infers Contena setup mode and component name from the SFC filename.
 *
 * - `ct-thing.vue` / `ct-thing/index.vue` -> base component `ct-thing`
 * - `ct-thing.override.vue` / `ct-thing/index.override.vue` -> override of `ct-thing`
 */
function inferContenaSetupFromFilename(filename: string): InferredContenaSetup {
    const file = basename(filename);
    const mode: ContenaSetupMode = file.endsWith('.override.vue') ? 'override' : 'base';
    // The mode already decided which suffix this file carries; reuse it instead of re-testing.
    const suffix = mode === 'override' ? '.override.vue' : '.vue';
    const componentName = (() => {
        if (file === `index${suffix}`) {
            return parentDirectoryName(filename);
        }

        if (file.endsWith(suffix)) {
            return file.slice(0, -suffix.length);
        }

        return file;
    })();

    return {
        mode,
        componentName,
    };
}

/**
 * Turns a generic script setup block into the filename-inferred base/override Contena mode.
 */
function normalizeContenaSetupBlock(block: ScriptBlock, filename: string): Omit<ContenaSetupBlock, 'template'> {
    const inferred = inferContenaSetupFromFilename(filename);

    return {
        ...block,
        mode: inferred.mode,
        componentName: inferred.componentName,
        lang: block.lang,
    };
}

/**
 * @private
 */
export {
    type ContenaSetupBlock,
    type ContenaSetupMode,
    type ContenaSetupTemplate,
    inferContenaSetupFromFilename,
    normalizeContenaSetupBlock,
};
