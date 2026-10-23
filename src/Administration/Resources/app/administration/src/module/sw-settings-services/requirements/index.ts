/**
 * @sw-package framework
 *
 * Centralizes service requirement checks used to determine
 * available actions and messaging in the Services UI,
 * including services that cannot be removed.
 */

/**
 * @private
 */
export const CONTENA_ACCOUNT_REQUIREMENT = 'contena_account';

/**
 * @private
 */
export interface ServiceWithContenaAccountRequirement {
    name: string;
    label: string;
}

/**
 * Minimal ServiceDescription type used by this module.
 *
 * @private
 */
export interface ServiceDescription extends ServiceWithContenaAccountRequirement {
    requirements: string[];
}

/**
 * Services that require a Contena account cannot be removed or deactivated.
 *
 * @private
 */
export function serviceHasContenaAccountRequirement(requirements: string[]): boolean {
    return requirements.includes(CONTENA_ACCOUNT_REQUIREMENT);
}

/**
 * List of services that require a Contena account, used to determine messaging in the UI.
 *
 * @private
 */
export function getServicesWithContenaAccountRequirement(
    services: ServiceDescription[],
): ServiceWithContenaAccountRequirement[] {
    return services
        .filter((service) => serviceHasContenaAccountRequirement(service.requirements))
        .map((service) => ({
            name: service.name,
            label: service.label,
        }));
}
