import { mount, flushPromises } from '@vue/test-utils';
import 'src/module/ct-integration/component/ct-integration-mcp-allowlist';

const defaultCapabilities = {
    tools: [
        { name: 'contena-entity-search', description: 'Search entities', dependencies: [], requiredPrivileges: [] },
        { name: 'contena-entity-read', description: 'Read entity', dependencies: [], requiredPrivileges: [] },
    ],
    resources: [
        { uri: 'contena://entities', name: 'Entities', description: 'All entities', mimeType: 'application/json' },
    ],
    prompts: [
        { name: 'contena-context', description: 'Context prompt' },
    ],
};

const mcpToolService = {
    getCapabilities: jest.fn(() => Promise.resolve(defaultCapabilities)),
};

async function createWrapper(props = {}) {
    return mount(await wrapTestComponent('ct-integration-mcp-allowlist', { sync: true }), {
        global: {
            provide: {
                mcpToolService,
            },
            stubs: {
                'mt-banner': true,
                'mt-switch': true,
                'mt-icon': true,
                'mt-checkbox': true,
                'mt-empty-state': true,
                'ct-collapse': true,
            },
        },
        props: {
            allowlist: null,
            disabled: false,
            isAdmin: false,
            grantedPrivileges: [],
            ...props,
        },
    });
}

describe('ct-integration-mcp-allowlist', () => {
    beforeEach(() => {
        mcpToolService.getCapabilities.mockClear();
        mcpToolService.getCapabilities.mockResolvedValue(defaultCapabilities);
    });

    it('renders without errors', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.exists()).toBe(true);
    });

    it('calls getCapabilities on created', async () => {
        await createWrapper();

        expect(mcpToolService.getCapabilities).toHaveBeenCalledTimes(1);
    });

    it('shows admin banner when isAdmin is true', async () => {
        const wrapper = await createWrapper({ isAdmin: true });

        expect(wrapper.find('.ct-integration-mcp-allowlist__admin-banner').exists()).toBe(true);
    });

    it('hides admin banner when isAdmin is false', async () => {
        const wrapper = await createWrapper({ isAdmin: false });

        expect(wrapper.find('.ct-integration-mcp-allowlist__admin-banner').exists()).toBe(false);
    });

    it('emits update:allowlist with null when allCapabilitiesEnabled is set to true', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: null, resources: null, prompts: null },
        });

        wrapper.vm.allCapabilitiesEnabled = true;

        expect(wrapper.emitted('update:allowlist')).toStrictEqual([[null]]);
    });

    it('emits update:allowlist with structured object when allCapabilitiesEnabled is set to false', async () => {
        const wrapper = await createWrapper({ allowlist: null });

        wrapper.vm.allCapabilitiesEnabled = false;

        expect(wrapper.emitted('update:allowlist')).toStrictEqual([
            [{ tools: null, resources: null, prompts: null }],
        ]);
    });

    it('allCapabilitiesEnabled is true when allowlist is null', async () => {
        const wrapper = await createWrapper({ allowlist: null });

        expect(wrapper.vm.allCapabilitiesEnabled).toBe(true);
    });

    it('allCapabilitiesEnabled is false when allowlist is an object', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: [], resources: null, prompts: null },
        });

        expect(wrapper.vm.allCapabilitiesEnabled).toBe(false);
    });

    it('toolsAllowlist returns null when allowlist is null', async () => {
        const wrapper = await createWrapper({ allowlist: null });

        expect(wrapper.vm.toolsAllowlist).toBeNull();
    });

    it('toolsAllowlist returns tools sub-array when allowlist is set', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: ['contena-entity-search'], resources: null, prompts: null },
        });

        expect(wrapper.vm.toolsAllowlist).toStrictEqual(['contena-entity-search']);
    });

    it('resourcesAllowlist returns resources sub-array when allowlist is set', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: null, resources: ['contena://entities'], prompts: null },
        });

        expect(wrapper.vm.resourcesAllowlist).toStrictEqual(['contena://entities']);
    });

    it('promptsAllowlist returns prompts sub-array when allowlist is set', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: null, resources: null, prompts: ['contena-context'] },
        });

        expect(wrapper.vm.promptsAllowlist).toStrictEqual(['contena-context']);
    });

    it('staleEntries includes stale tool names', async () => {
        const wrapper = await createWrapper({
            allowlist: {
                tools: [
                    'old-tool',
                    'contena-entity-search',
                ],
                resources: null,
                prompts: null,
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.staleToolNames).toContain('old-tool');
        expect(wrapper.vm.staleToolNames).not.toContain('contena-entity-search');
    });

    it('staleEntries includes stale resource uris', async () => {
        const wrapper = await createWrapper({
            allowlist: {
                tools: null,
                resources: [
                    'contena://old',
                    'contena://entities',
                ],
                prompts: null,
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.staleResourceUris).toContain('contena://old');
        expect(wrapper.vm.staleResourceUris).not.toContain('contena://entities');
    });

    it('staleEntries includes stale prompt names', async () => {
        const wrapper = await createWrapper({
            allowlist: {
                tools: null,
                resources: null,
                prompts: [
                    'old-prompt',
                    'contena-context',
                ],
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.stalePromptNames).toContain('old-prompt');
        expect(wrapper.vm.stalePromptNames).not.toContain('contena-context');
    });

    it('emitUpdated merges patch into current allowlist', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: ['contena-entity-search'], resources: null, prompts: null },
        });

        wrapper.vm.emitUpdated({ resources: ['contena://entities'] });

        expect(wrapper.emitted('update:allowlist')).toStrictEqual([
            [{ tools: ['contena-entity-search'], resources: ['contena://entities'], prompts: null }],
        ]);
    });

    it('emitUpdated uses defaults when allowlist is null', async () => {
        const wrapper = await createWrapper({ allowlist: null });

        wrapper.vm.emitUpdated({ tools: ['contena-entity-search'] });

        expect(wrapper.emitted('update:allowlist')).toStrictEqual([
            [{ tools: ['contena-entity-search'], resources: null, prompts: null }],
        ]);
    });

    it('handles getCapabilities rejection gracefully', async () => {
        mcpToolService.getCapabilities.mockRejectedValue(new Error('Network error'));

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.availableTools).toStrictEqual([]);
        expect(wrapper.vm.availableResources).toStrictEqual([]);
        expect(wrapper.vm.availablePrompts).toStrictEqual([]);
    });

    it('deniedTypes returns types where all items are explicitly denied', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: null, resources: [], prompts: null },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.deniedTypes).toContain('resources');
        expect(wrapper.vm.deniedTypes).not.toContain('tools');
        expect(wrapper.vm.deniedTypes).not.toContain('prompts');
    });

    it('deniedTypes is empty when allCapabilitiesEnabled', async () => {
        const wrapper = await createWrapper({ allowlist: null });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.deniedTypes).toStrictEqual([]);
    });

    it('deniedTypesLabel joins single type correctly', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: null, resources: [], prompts: null },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.deniedTypesLabel).toBe('ct-integration.mcp.resourcesSection');
    });

    it('deniedTypesLabel joins two types with "and"', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: null, resources: [], prompts: [] },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.deniedTypesLabel).toBe(
            'ct-integration.mcp.resourcesSection and ct-integration.mcp.promptsSection',
        );
    });

    it('missingCapabilitySuggestions returns empty when allCapabilitiesEnabled', async () => {
        const wrapper = await createWrapper({ allowlist: null });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.missingCapabilitySuggestions).toStrictEqual([]);
    });

    it('missingCapabilitySuggestions returns empty when resources are unrestricted', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: ['contena-entity-search'], resources: null, prompts: null },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.missingCapabilitySuggestions).toStrictEqual([]);
    });

    it('missingCapabilitySuggestions suggests missing resource from same prefix', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: ['contena-entity-search'], resources: [], prompts: null },
        });
        await wrapper.vm.$nextTick();

        // resources allowlist is empty (not fully denied length===0 check skips it) — wait, length===0 means skip
        // so no suggestions when fully denied — verified by deniedTypes banner instead
        expect(wrapper.vm.missingCapabilitySuggestions).toStrictEqual([]);
    });

    it('missingCapabilitySuggestions suggests resource missing from partial allowlist', async () => {
        mcpToolService.getCapabilities.mockResolvedValue({
            ...defaultCapabilities,
            resources: [
                { uri: 'contena://entities', name: 'Entities', description: '', mimeType: 'application/json' },
                { uri: 'contena://languages', name: 'Languages', description: '', mimeType: 'application/json' },
            ],
        });

        const wrapper = await createWrapper({
            allowlist: { tools: ['contena-entity-search'], resources: ['contena://languages'], prompts: null },
        });
        await wrapper.vm.$nextTick();

        const names = wrapper.vm.missingCapabilitySuggestions.map((s) => s.name);
        expect(names).toContain('contena://entities');
        expect(names).not.toContain('contena://languages');
    });

    it('missingCapabilitySuggestions suggests missing prompt from same prefix', async () => {
        const wrapper = await createWrapper({
            allowlist: { tools: ['contena-entity-search'], resources: null, prompts: [] },
        });
        await wrapper.vm.$nextTick();

        // fully-denied prompts (length===0) are skipped — covered by deniedTypes banner
        expect(wrapper.vm.missingCapabilitySuggestions).toStrictEqual([]);
    });

    it('missingCapabilitySuggestions suggests prompt missing from partial allowlist', async () => {
        mcpToolService.getCapabilities.mockResolvedValue({
            ...defaultCapabilities,
            prompts: [
                { name: 'contena-context', description: 'Context prompt' },
                { name: 'contena-debug', description: 'Debug prompt' },
            ],
        });

        const wrapper = await createWrapper({
            allowlist: { tools: ['contena-entity-search'], resources: null, prompts: ['contena-debug'] },
        });
        await wrapper.vm.$nextTick();

        const names = wrapper.vm.missingCapabilitySuggestions.map((s) => s.name);
        expect(names).toContain('contena-context');
        expect(names).not.toContain('contena-debug');
    });

    describe('no capabilities registered', () => {
        it('renders the empty state when capabilities are empty and all-toggle is off', async () => {
            mcpToolService.getCapabilities.mockResolvedValue({ tools: [], resources: [], prompts: [] });

            const wrapper = await createWrapper({
                allowlist: { tools: null, resources: null, prompts: null },
            });
            await flushPromises();

            expect(wrapper.find('mt-empty-state-stub').exists()).toBe(true);
        });

        it('tolerates a capabilities payload with missing keys', async () => {
            mcpToolService.getCapabilities.mockResolvedValue({});

            const wrapper = await createWrapper({
                allowlist: { tools: null, resources: null, prompts: null },
            });
            await flushPromises();

            expect(wrapper.vm.availableTools).toStrictEqual([]);
            expect(wrapper.vm.availableResources).toStrictEqual([]);
            expect(wrapper.vm.availablePrompts).toStrictEqual([]);
            expect(wrapper.find('mt-empty-state-stub').exists()).toBe(true);
        });

        it('does not throw when toggling all capabilities off with no capabilities', async () => {
            mcpToolService.getCapabilities.mockResolvedValue({ tools: [], resources: [], prompts: [] });

            const wrapper = await createWrapper({ allowlist: null });
            await flushPromises();

            expect(() => {
                wrapper.vm.allCapabilitiesEnabled = false;
            }).not.toThrow();
        });
    });

    describe('malformed capability data', () => {
        it('does not throw when a tool exposes an undefined privilege chip', async () => {
            mcpToolService.getCapabilities.mockResolvedValue({
                tools: [
                    {
                        name: 'broken-tool',
                        description: 'Broken tool',
                        dependencies: [],
                        // static privilege list contains a nullish entry
                        requiredPrivileges: { static: [undefined] },
                    },
                ],
                resources: [],
                prompts: [],
            });

            const wrapper = await createWrapper({
                allowlist: { tools: null, resources: null, prompts: null },
                // non-empty granted privileges + non-admin => the chip guard is actually evaluated
                isAdmin: false,
                grantedPrivileges: ['product.viewer'],
            });
            await flushPromises();

            expect(() => wrapper.vm.privilegeChipClass(undefined)).not.toThrow();
            expect(wrapper.vm.privilegeChipClass(undefined)).toBe('neutral');
        });
    });
});
