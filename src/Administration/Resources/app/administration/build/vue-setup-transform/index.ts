/**
 * Converts Contena's native setup SFC dialect into plain Vue SFC source before Vue compilation.
 *
 * This module owns the per-file transform boundary: parse the SFC, analyze script and template
 * semantics, lower the Contena setup block into source edits, and apply them - while leaving
 * cross-file component-name checks to the build integration. Every edit comes from lowering; nothing
 * generated is decided here.
 */

import { lowerContenaSetupBlock } from './lower';
import { analyzeContenaSetupScript, type ContenaSetupScriptAnalysis } from './script-analyzer';
import { applySourceEdits, type AppliedSourceEdits } from './source-edits/apply-source-edits';
import {
    analyzeBaseTemplate,
    analyzeOverrideTemplate,
    emptyTemplateAnalysis,
    type TemplateAnalysis,
} from './template-analyzer';
import { parseContenaSetupSfc } from './sfc-parser';
import type { ContenaSetupBlock } from './utils/contena-setup-block';
import { ContenaSetupTransformError } from './utils/transform-error';

type ContenaSetupTransformResult = {
    code: string;
    map: AppliedSourceEdits['map'];
    mode: 'base' | 'override';
    componentName: string;
    filename: string;
    // Static names of the base `<ct-block name="ct_...">` blocks this component owns (empty for overrides).
    // Emitted for a later branch to build a cross-file block-ownership registry.
    ownedBlockNames: string[];
    // Static names of the blocks this override `<ct-block extends="ct_...">` extends (empty for base).
    // The registry's other half, for a later branch to cross-check against the emitted ownership.
    extendedBlockNames: string[];
};

/**
 * Moves block-relative analyzer errors to the start of the original script body.
 */
function withBlockOffset(error: unknown, block: ContenaSetupBlock): unknown {
    if (!(error instanceof ContenaSetupTransformError) || error.index !== null) {
        return error;
    }

    return new ContenaSetupTransformError(error.message, block.contentStart);
}

/**
 * Converts a Contena setup SFC into plain Vue-compatible code before Vue compiles it.
 */
function transformContenaSetupSfc(source: string, filename = 'anonymous.vue'): ContenaSetupTransformResult | null {
    const block = parseContenaSetupSfc(source, filename);

    if (!block) {
        return null;
    }

    let analysis: ContenaSetupScriptAnalysis;
    let edits: ReturnType<typeof lowerContenaSetupBlock>;
    let templateAnalysis: TemplateAnalysis = emptyTemplateAnalysis();

    try {
        analysis = analyzeContenaSetupScript(block.content, {
            mode: block.mode,
            lang: block.lang,
            scriptOffset: block.contentStart,
        });
        templateAnalysis = analysis.mode === 'base' ? analyzeBaseTemplate(block) : analyzeOverrideTemplate(block, analysis);

        edits = lowerContenaSetupBlock(block, analysis, templateAnalysis);
    } catch (error) {
        throw withBlockOffset(error, block);
    }

    const transformed = applySourceEdits(source, filename, edits);

    return {
        code: transformed.code,
        map: transformed.map,
        mode: block.mode,
        // Exposed so the build integration can maintain a per-compilation registry and reject two
        // SFCs that resolve to the same extendable component name. Cross-file enforcement lives with
        // the loader/compilation layer; this transform stays a pure per-file step.
        componentName: block.componentName,
        filename,
        ownedBlockNames: templateAnalysis.ownedBlockNames,
        extendedBlockNames: templateAnalysis.extendedBlockNames,
    };
}

/**
 * Runs the shared transform for callers that only need diagnostics.
 */
function validateContenaSetupSfc(source: string, filename = 'anonymous.vue'): void {
    transformContenaSetupSfc(source, filename);
}

/**
 * @private
 */
export { type ContenaSetupTransformResult, ContenaSetupTransformError, transformContenaSetupSfc, validateContenaSetupSfc };
