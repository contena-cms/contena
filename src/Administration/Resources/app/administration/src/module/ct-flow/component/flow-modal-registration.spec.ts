describe('module/ct-flow/component modal registration', () => {
    it.each([
        [
            'notification',
            () => import('./ct-flow-notification-modal'),
        ],
        [
            'rule',
            () => import('./ct-flow-rule-modal'),
        ],
        [
            'tag',
            () => import('./ct-flow-tag-modal'),
        ],
        [
            'user custom field',
            () => import('./ct-flow-user-custom-field-modal'),
        ],
        [
            'user status',
            () => import('./ct-flow-user-status-modal'),
        ],
    ])('marks the %s modal as a native SFC for the component registry', async (name, loadComponent) => {
        const component = (await loadComponent()).default as { _renderedBySfcTemplate?: boolean };

        expect(component._renderedBySfcTemplate).toBe(true);
    });
});
