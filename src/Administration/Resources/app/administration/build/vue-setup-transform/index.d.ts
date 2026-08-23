/**
 * Types for the CommonJS bridge (`index.js`), which loads `index.ts` through jiti.
 *
 * Everything here is derived from the TypeScript implementation rather than restated, so the two
 * cannot drift: the previous hand-written copy of `ContenaSetupTransformResult` had already fallen
 * behind the `ownedBlockNames` / `extendedBlockNames` fields the transform returns.
 */

export { ContenaSetupTransformError, transformContenaSetupSfc, validateContenaSetupSfc } from './index';

export type { ContenaSetupTransformResult } from './index';

/**
 * Names the filename-inferred transform path used by one Contena setup SFC.
 */
export type ContenaSetupTransformMode = import('./index').ContenaSetupTransformResult['mode'];
