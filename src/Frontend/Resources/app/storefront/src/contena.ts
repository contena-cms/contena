/**
 * Contena runtime module.
 *
 * This is the entry point for the standalone Vite build (vite.contena.config.mts).
 * It exports ContenaComponent and Contena for ES module consumers and sets both
 * on window as a side effect for backward compatibility with legacy JS plugins.
 *
 * @sw-package framework
 */

// Sets window.ContenaComponent as side effect.
export { default as ContenaComponent } from './component-system/component';

// Sets window.Contena as side effect.
export { Contena } from './component-system/contena';
