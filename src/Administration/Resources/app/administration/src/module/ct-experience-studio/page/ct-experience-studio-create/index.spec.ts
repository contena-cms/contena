import type { RouteLocationNormalized } from 'vue-router';
import route from './index';

describe('module/ct-experience-studio/page/ct-experience-studio-create', () => {
    it('keeps an existing layout id', () => {
        const to = {
            params: { id: 'layout-1' },
            query: {},
        } as unknown as RouteLocationNormalized;

        expect(route.beforeEnter(to)).toBe(true);
    });

    it('creates a layout id and preserves the requested entity context', () => {
        const createId = jest.spyOn(Contena.Utils, 'createId').mockReturnValue('layout-new');
        const to = {
            params: {},
            query: {
                rootSource: 'blog',
                entityId: 'blog-1',
            },
        } as unknown as RouteLocationNormalized;

        expect(route.beforeEnter(to)).toEqual({
            name: 'ct.experience.studio.create',
            params: { id: 'layout-new' },
            query: to.query,
            replace: true,
        });

        createId.mockRestore();
    });
});
