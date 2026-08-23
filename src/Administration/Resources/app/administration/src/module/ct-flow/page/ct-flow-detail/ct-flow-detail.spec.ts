import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import { routerKey } from 'vue-router';
import type PrivilegesService from 'src/app/service/privileges.service';
import type { EditableFlowSequence } from '../../component/flow-sequence.types';

const mockCreateNotificationError = jest.fn();
jest.mock('src/app/composables/use-notification', () => ({
    useNotification: () => ({
        createNotificationError: mockCreateNotificationError,
        createNotificationSuccess: jest.fn(),
    }),
}));

const rootConditionId = '019fc5b5ad1f7a659e3eea39f1000202';
const trueActionOneId = '019fc5b5ad1f7a659e3eea39f1000203';
const trueActionTwoId = '019fc5b5ad1f7a659e3eea39f1000204';
const falseActionId = '019fc5b5ad1f7a659e3eea39f1000207';
const rootActionOneId = '019fc5b5ad1f7a659e3eea39f1000205';
const rootActionTwoId = '019fc5b5ad1f7a659e3eea39f1000206';
const flow = {
    id: '019fc5b5ad1f7a659e3eea39f1000201',
    name: 'Password recovery',
    description: 'Send password recovery email',
    eventName: 'user.recovery.request',
    priority: 100,
    active: true,
    isNew: () => false,
    sequences: [
        {
            id: rootConditionId,
            parentId: null,
            ruleId: '019fc5b5ad1f7a659e3eea39f1000101',
            rule: { id: '019fc5b5ad1f7a659e3eea39f1000101', name: 'Administrator recovery' },
            actionName: null,
            config: {},
            position: 1,
            displayGroup: 1,
            trueCase: false,
        },
        {
            id: trueActionOneId,
            parentId: rootConditionId,
            ruleId: null,
            actionName: 'action.mail.send',
            config: { mailTemplateId: '019fc5b5ad1f7a659e3eea39f1000002' },
            position: 1,
            displayGroup: 1,
            trueCase: true,
        },
        {
            id: trueActionTwoId,
            parentId: rootConditionId,
            ruleId: null,
            actionName: 'action.stop.flow',
            config: {},
            position: 2,
            displayGroup: 1,
            trueCase: true,
        },
        {
            id: falseActionId,
            parentId: rootConditionId,
            ruleId: null,
            actionName: 'action.stop.flow',
            config: {},
            position: 1,
            displayGroup: 1,
            trueCase: false,
        },
        {
            id: rootActionOneId,
            parentId: null,
            ruleId: null,
            actionName: 'action.mail.send',
            config: { mailTemplateId: '019fc5b5ad1f7a659e3eea39f1000002' },
            position: 1,
            displayGroup: 2,
            trueCase: false,
        },
        {
            id: rootActionTwoId,
            parentId: null,
            ruleId: null,
            actionName: 'action.stop.flow',
            config: {},
            position: 2,
            displayGroup: 2,
            trueCase: false,
        },
    ],
};

const flowRepository = {
    get: jest.fn(() => Promise.resolve(flow)),
    save: jest.fn(() => Promise.resolve()),
    create: jest.fn(),
};
const sequenceRepository = {
    create: jest.fn((_context, id: string) => ({ id })),
    save: jest.fn(() => Promise.resolve()),
    syncDeleted: jest.fn(() => Promise.resolve()),
};
const templateRepository = { get: jest.fn() };
const repositoryFactory = {
    create: jest.fn((entity: string) => {
        if (entity === 'flow') return flowRepository;
        if (entity === 'flow_sequence') return sequenceRepository;
        return templateRepository;
    }),
};

describe('module/ct-flow/page/ct-flow-detail', () => {
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

    beforeEach(() => {
        jest.clearAllMocks();
        sequenceRepository.create.mockImplementation((_context, id: string) => ({ id }));
        sequenceRepository.save.mockResolvedValue(undefined);
        sequenceRepository.syncDeleted.mockResolvedValue(undefined);
        flowRepository.save.mockResolvedValue(undefined);
    });

    it('hydrates and saves display groups with sibling actions like upstream', async () => {
        const wrapper = mount(await wrapTestComponent('ct-flow-detail', { sync: true }), {
            props: { flowId: flow.id },
            global: {
                provide: {
                    repositoryFactory,
                    acl: { can: jest.fn(() => true) },
                    businessEventService: {
                        getBusinessEvents: jest.fn(() =>
                            Promise.resolve({
                                'user.recovery.request': {
                                    name: 'user.recovery.request',
                                    aware: [
                                        'mailAware',
                                        'userAware',
                                    ],
                                },
                            }),
                        ),
                    },
                    flowActionService: {
                        getFlowActions: jest.fn(() =>
                            Promise.resolve({
                                'action.mail.send': {
                                    name: 'action.mail.send',
                                    requirements: ['mailAware'],
                                },
                                'action.user.status.assign': {
                                    name: 'action.user.status.assign',
                                    requirements: ['userAware'],
                                },
                                'action.user.tag.add': {
                                    name: 'action.user.tag.add',
                                    requirements: ['userAware'],
                                },
                                'action.user.tag.remove': {
                                    name: 'action.user.tag.remove',
                                    requirements: ['userAware'],
                                },
                                'action.stop.flow': { name: 'action.stop.flow', requirements: [] },
                            }),
                        ),
                    },
                    [routerKey as symbol]: { push: jest.fn(), replace: jest.fn() },
                },
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'ct-page': defineComponent({ template: '<div><slot name="content" /></div>' }),
                    'ct-card-view': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-card': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-tabs': true,
                    'mt-text-field': true,
                    'mt-number-field': true,
                    'mt-switch': true,
                    'mt-textarea': true,
                    'ct-flow-trigger': true,
                    'ct-flow-sequence-editor': true,
                },
            },
        });
        await flushPromises();
        const page = wrapper.vm as unknown as {
            sequences: EditableFlowSequence[];
            availableActions: Array<{ value: string; group: string }>;
            onSave: () => Promise<void>;
        };
        expect(page.availableActions).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ value: 'action.user.status.assign', group: 'general' }),
                expect.objectContaining({ value: 'action.user.tag.add', group: 'tags' }),
                expect.objectContaining({ value: 'action.user.tag.remove', group: 'tags' }),
            ]),
        );
        expect(page.availableActions.some((action) => action.group === 'user')).toBe(false);
        expect(page.sequences).toHaveLength(2);
        expect(page.sequences[0].type).toBe('condition');
        expect(page.sequences[0].trueChild?.type).toBe('action');
        expect(page.sequences[0].trueChild?.actions).toHaveLength(2);
        expect(page.sequences[1]).toEqual(expect.objectContaining({ type: 'action' }));
        expect(page.sequences[1].actions).toHaveLength(2);
        await page.onSave();

        expect(mockCreateNotificationError).not.toHaveBeenCalled();
        expect(sequenceRepository.syncDeleted).toHaveBeenCalledWith(
            [
                rootConditionId,
                rootActionOneId,
                rootActionTwoId,
            ],
            Contena.Context.api,
        );
        expect(sequenceRepository.save).toHaveBeenCalledWith(
            expect.objectContaining({
                id: trueActionOneId,
                parentId: rootConditionId,
                displayGroup: 1,
                position: 1,
                trueCase: true,
            }),
        );
        expect(sequenceRepository.save).toHaveBeenCalledWith(
            expect.objectContaining({
                id: trueActionTwoId,
                parentId: rootConditionId,
                displayGroup: 1,
                position: 2,
                trueCase: true,
            }),
        );
        expect(sequenceRepository.save).toHaveBeenCalledWith(
            expect.objectContaining({
                id: rootActionTwoId,
                parentId: null,
                displayGroup: 2,
                position: 2,
            }),
        );
    });
});
