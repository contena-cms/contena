import { defineComponent, type ComponentPublicInstance } from 'vue';
import { mount, type VueWrapper } from '@vue/test-utils';
import type PrivilegesService from 'src/app/service/privileges.service';

interface EditableRuleCondition {
    key: string;
    type:
        | 'dateRange'
        | 'timeRange'
        | 'dayOfWeek'
        | 'language'
        | 'userDaysSinceFirstLogin'
        | 'userDaysSinceLastLogin'
        | 'andContainer'
        | 'orContainer';
    value: Record<string, unknown>;
    children?: EditableRuleCondition[];
}

let wrapper: VueWrapper<ComponentPublicInstance> | null = null;

async function createWrapper(conditions: EditableRuleCondition[] = [], mode: 'all' | 'any' = 'all') {
    wrapper = mount(await wrapTestComponent('ct-rule-condition-editor', { sync: true }), {
        props: { conditions, mode },
        global: {
            stubs: {
                'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                'mt-button': true,
                'mt-icon': true,
                'mt-select': true,
                'mt-text-field': true,
                'mt-number-field': true,
                'ct-entity-single-select': true,
            },
        },
    });

    return wrapper;
}

describe('module/ct-settings-rule/component/ct-rule-condition-editor', () => {
    beforeAll(async () => {
        // eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
        Contena.Service().register(
            'privileges',
            () =>
                ({
                    addPrivilegeMappingEntry: jest.fn(),
                    getPrivileges: jest.fn(() => () => []),
                }) as unknown as PrivilegesService,
        );
        await import('../../index');
    });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
    });

    it('adds a nested condition group using the opposite boolean operator', async () => {
        const editorWrapper = await createWrapper();
        const editor = editorWrapper.vm as unknown as { addGroup: () => void };

        editor.addGroup();

        const emittedConditions = editorWrapper.emitted('update:conditions')?.[0]?.[0] as EditableRuleCondition[];
        expect(emittedConditions).toEqual([
            expect.objectContaining({
                type: 'orContainer',
                value: {},
                children: [expect.objectContaining({ type: 'timeRange' })],
            }),
        ]);
    });

    it('preserves nested children when changing a group operator', async () => {
        const children: EditableRuleCondition[] = [
            {
                key: 'child',
                type: 'dayOfWeek',
                value: { operator: '=', dayOfWeek: 1 },
            },
        ];
        const editorWrapper = await createWrapper([
            { key: 'group', type: 'orContainer', value: {}, children },
        ]);
        const editor = editorWrapper.vm as unknown as {
            updateGroupMode: (index: number, mode: 'all' | 'any') => void;
        };

        editor.updateGroupMode(0, 'all');

        expect(editorWrapper.emitted('update:conditions')?.[0]?.[0]).toEqual([
            expect.objectContaining({ type: 'andContainer', children }),
        ]);
    });
});
