import { shallowMount } from '@vue/test-utils';
import 'src/app/component/utils/ct-error-boundary';

describe('src/app/component/utils/ct-error-boundary', () => {
    /** @type Wrapper */
    let wrapper;
    let swErrorBoundary;

    beforeAll(async () => {
        swErrorBoundary = await wrapTestComponent('ct-error-boundary');
    });

    beforeEach(async () => {
        jest.spyOn(console, 'error').mockImplementation();
    });

    afterEach(async () => {
        await flushPromises();
        global.repositoryFactoryMock.clientMock.resetHistory();
        if (wrapper) await wrapper.unmount();
        if (console.error.mockReset) console.error.mockReset();
    });

    it('should catch the error from siblings', async () => {
        expect(console.error).not.toHaveBeenCalled();

        wrapper = shallowMount(swErrorBoundary, {
            slots: {
                default: '<ct-damaged-component></ct-damaged-component>',
            },
            global: {
                stubs: {
                    'ct-damaged-component': {
                        template: '<div class="ct-damaged-component"></div>',
                        mounted() {
                            throw new Error('There is gone something wrong');
                        },
                    },
                },
            },
        });

        expect(console.error).toHaveBeenCalledWith(
            'An error was captured in current module:',
            new Error('There is gone something wrong'),
        );
    });

    it('should log the error to the error logs', async () => {
        const logEntry = {};
        const logEntryRepository = {
            create: jest.fn(() => logEntry),
            save: jest.fn(() => Promise.resolve()),
        };

        wrapper = shallowMount(swErrorBoundary, {
            slots: {
                default: '<ct-damaged-component></ct-damaged-component>',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: jest.fn(() => logEntryRepository),
                    },
                },
                stubs: {
                    'ct-damaged-component': {
                        template: '<div class="ct-damaged-component"></div>',
                        mounted() {
                            throw new Error('There is gone something wrong');
                        },
                    },
                },
            },
        });

        // wait until the component finished all requests
        await flushPromises();

        expect(logEntryRepository.save).toHaveBeenCalledWith(logEntry);
        expect(logEntry.level).toBe(400);
        expect(logEntry.channel).toBe('Administration');
        expect(logEntry.message).toBe('Error: There is gone something wrong');
    });
});
