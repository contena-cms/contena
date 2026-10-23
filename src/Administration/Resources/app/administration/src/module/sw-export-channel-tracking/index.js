/**
 * @sw-package discovery
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_CHANNEL
 */

import './mixin/export-channel-filter.mixin';
import './extension/sw-order-list';
import './extension/sw-user-list';

// No routes or navigation — this module only adds columns and filters to existing lists.
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Contena.Module.register('sw-export-channel-tracking', {
    type: 'core',
    name: 'export-channel-tracking',
    title: 'sw-export-channel-tracking.general.mainMenuItemGeneral',
    description: 'sw-export-channel-tracking.general.descriptionTextModule',
    routeMiddleware: (next) => next(),
});
