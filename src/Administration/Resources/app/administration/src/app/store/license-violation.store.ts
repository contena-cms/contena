/**
 * @sw-package framework
 * @private
 */
const licenseViolationStore = Contena.Store.register({
    id: 'licenseViolation',

    state: () => ({
        violations: [] as unknown[],
        warnings: [] as unknown[],
        other: [] as unknown[],
    }),
});

/**
 * @private
 */
export type LicenseViolationStore = ReturnType<typeof licenseViolationStore>;

/**
 * @private
 */
export default licenseViolationStore;
