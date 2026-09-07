/* eslint-disable @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access */
import { createWrapper, resetWrappers } from './test.helper';

describe('module/ct-experience-studio/page/ct-experience-studio-detail layout type options', () => {
    afterEach(() => {
        resetWrappers();
    });

    it('keeps the expected layout type order', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.layoutTypeOptions.map(({ value }: { value: string }) => value)).toEqual([
            'category',
            'blog',
            'landing_page',
        ]);
    });
});
