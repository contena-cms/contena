/* eslint-disable @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-misused-spread */
import { mount, type VueWrapper } from '@vue/test-utils';
import previewComponent from './index';

describe('module/ct-experience-studio/component/ct-experience-studio-preview', () => {
    const wrappers: VueWrapper[] = [];
    const createWrapper = (props: Record<string, unknown> = {}) => {
        const wrapper = mount(previewComponent, {
            attachTo: document.body,
            props: {
                layout: { layout: [] },
                channelId: 'channel-id',
                entityType: 'blog',
                entityId: 'blog-id',
                suspendAutoReload: true,
                ...props,
            },
        });

        wrappers.push(wrapper);

        return wrapper;
    };

    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
        jest.useRealTimers();
    });

    it('allows reload scheduling only when auto reload is not suspended', async () => {
        const wrapper = createWrapper();
        const debouncedLoadPreview = jest.fn();
        wrapper.vm.debouncedLoadPreview = debouncedLoadPreview;

        wrapper.vm.schedulePreviewReload();
        expect(debouncedLoadPreview).not.toHaveBeenCalled();

        await wrapper.setProps({ suspendAutoReload: false });
        debouncedLoadPreview.mockClear();
        wrapper.vm.schedulePreviewReload();
        expect(debouncedLoadPreview).toHaveBeenCalledTimes(1);
    });

    it('triggers a reload when suspend flag switches off', async () => {
        const wrapper = createWrapper();
        const debouncedLoadPreview = jest.fn();
        wrapper.vm.debouncedLoadPreview = debouncedLoadPreview;

        await wrapper.setProps({ suspendAutoReload: false });

        expect(debouncedLoadPreview).toHaveBeenCalledTimes(1);
    });

    it('supports loading the layout after mounting', async () => {
        const wrapper = createWrapper({ layout: null, suspendAutoReload: false });
        const debouncedLoadPreview = jest.fn();
        wrapper.vm.debouncedLoadPreview = debouncedLoadPreview;

        await wrapper.setProps({ layout: { layout: [] } });

        expect(debouncedLoadPreview).toHaveBeenCalledTimes(1);
    });

    it('validates preview origin and source frame', async () => {
        const wrapper = createWrapper();
        wrapper.vm.iframeAUrl = 'https://frontend.local/preview';
        wrapper.vm.activeFrame = 'a';
        await wrapper.vm.$nextTick();

        const frameWindow = wrapper.find('iframe').element.contentWindow;
        const event = {
            source: frameWindow,
            origin: 'https://frontend.local',
        } as MessageEvent;

        expect(wrapper.vm.isTrustedPreviewMessage(event)).toBe(true);
        expect(wrapper.vm.isTrustedPreviewMessage({ ...event, origin: 'https://other.local' } as MessageEvent)).toBe(false);
    });

    it('captures current active frame scroll position', async () => {
        const wrapper = createWrapper();
        wrapper.vm.iframeAUrl = 'https://frontend.local/preview';
        wrapper.vm.activeFrame = 'a';
        await wrapper.vm.$nextTick();

        const frameWindow = wrapper.find('iframe').element.contentWindow as Window;
        Object.defineProperty(frameWindow, 'scrollY', { configurable: true, value: 240 });
        Object.defineProperty(frameWindow, 'scrollX', { configurable: true, value: 16 });

        expect(wrapper.vm.captureActiveFrameScrollPosition()).toEqual({ top: 240, left: 16 });
    });

    it('restores scroll position before loading frame switch', async () => {
        jest.useFakeTimers();
        const wrapper = createWrapper();
        wrapper.vm.iframeBUrl = 'https://frontend.local/preview';
        wrapper.vm.loadingFrame = 'b';
        wrapper.vm.activeFrame = 'a';
        wrapper.vm.pendingScrollPosition = { top: 140, left: 0 };
        await wrapper.vm.$nextTick();

        const frameWindow = wrapper.find('iframe').element.contentWindow as Window;
        const postMessage = jest.spyOn(frameWindow, 'postMessage').mockImplementation(() => undefined);
        const loading = wrapper.vm.onPreviewFrameLoad('b');
        jest.advanceTimersByTime(250);
        await loading;

        expect(wrapper.vm.activeFrame).toBe('b');
        expect(wrapper.vm.loadingFrame).toBeNull();
        expect(wrapper.vm.pendingScrollPosition).toBeNull();
        expect(postMessage).toHaveBeenCalledWith(
            expect.objectContaining({ type: 'restore-scroll', top: 140, left: 0 }),
            'https://frontend.local',
        );
    });

    it('prefers direct scroll capture before message fallback', async () => {
        const wrapper = createWrapper();
        wrapper.vm.iframeAUrl = 'https://frontend.local/preview';
        wrapper.vm.activeFrame = 'a';
        await wrapper.vm.$nextTick();

        const frameWindow = wrapper.find('iframe').element.contentWindow as Window;
        Object.defineProperty(frameWindow, 'scrollY', { configurable: true, value: 99 });
        Object.defineProperty(frameWindow, 'scrollX', { configurable: true, value: 12 });

        await expect(wrapper.vm.requestActiveFrameScrollPosition()).resolves.toEqual({ top: 99, left: 12 });
    });
});
