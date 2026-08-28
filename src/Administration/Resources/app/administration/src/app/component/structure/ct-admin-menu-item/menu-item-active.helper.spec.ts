import {
    getActiveRouteNames,
    entryParamsMatchRoute,
    isEntryOnActiveRoute,
} from 'src/app/component/structure/ct-admin-menu-item/menu-item-active.helper';

describe('src/app/component/structure/ct-admin-menu-item/menu-item-active.helper', () => {
    describe('getActiveRouteNames', () => {
        it('collects the route names of the resolved chain', () => {
            const route = {
                matched: [
                    { name: 'core' },
                    { name: 'ct.extension.my-extensions' },
                    { name: 'ct.extension.my-extensions.listing' },
                ],
            };

            expect(getActiveRouteNames(route)).toEqual(
                new Set([
                    'core',
                    'ct.extension.my-extensions',
                    'ct.extension.my-extensions.listing',
                ]),
            );
        });

        it('is empty for a route without a matched chain', () => {
            expect(getActiveRouteNames({})).toEqual(new Set());
            expect(getActiveRouteNames(undefined)).toEqual(new Set());
        });

        it('ignores records without a name', () => {
            const route = {
                matched: [
                    { name: 'core' },
                    {},
                    { name: undefined },
                    { name: 'ct.blog.index' },
                ],
            };

            expect(getActiveRouteNames(route)).toEqual(
                new Set([
                    'core',
                    'ct.blog.index',
                ]),
            );
        });

        it('bridges sibling detail pages to their owning navigation route through parentPath', () => {
            const route = {
                name: 'ct.settings.member.group.detail',
                matched: [{ name: 'ct.settings.member.group.detail' }],
                meta: { parentPath: 'ct.settings.member.group.list' },
            };
            const router = {
                getRoutes: () => [
                    { name: 'ct.settings.member.group.list', meta: { parentPath: 'ct.settings.index' } },
                    { name: 'ct.settings.index', meta: {} },
                ],
            };

            const names = getActiveRouteNames(route, router);

            expect(names.has('ct.settings.member.group.detail')).toBe(true);
            expect(names.has('ct.settings.member.group.list')).toBe(true);
            expect(names.has('ct.settings.index')).toBe(true);
        });

        it('does not loop on a cyclic parentPath chain', () => {
            const route = { name: 'a', matched: [{ name: 'a' }], meta: { parentPath: 'b' } };
            const router = {
                getRoutes: () => [
                    { name: 'b', meta: { parentPath: 'a' } },
                    { name: 'a', meta: { parentPath: 'b' } },
                ],
            };

            expect(getActiveRouteNames(route, router)).toEqual(
                new Set([
                    'a',
                    'b',
                ]),
            );
        });

        it('uses the module navigation path when no parentPath is declared', () => {
            const route = {
                name: 'ct.extension.detail',
                matched: [{ name: 'ct.extension.detail' }],
                meta: {
                    $module: {
                        type: 'plugin' as const,
                        navigation: [{ path: 'ct.extension' }],
                    },
                },
            };

            expect(getActiveRouteNames(route)).toEqual(
                new Set([
                    'ct.extension.detail',
                    'ct.extension',
                ]),
            );
        });

        it('does not infer an ambiguous core module navigation path', () => {
            const route = {
                name: 'ct.core.detail',
                matched: [{ name: 'ct.core.detail' }],
                meta: {
                    $module: {
                        type: 'core' as const,
                        navigation: [
                            { path: 'ct.first' },
                            { path: 'ct.second' },
                        ],
                    },
                },
            };

            expect(getActiveRouteNames(route)).toEqual(new Set(['ct.core.detail']));
        });
    });

    describe('entryParamsMatchRoute', () => {
        it('matches when the entry declares no params', () => {
            expect(entryParamsMatchRoute({ path: 'ct.blog.index' }, { params: {} })).toBe(true);
        });

        it('matches only when every declared param equals the route param', () => {
            const route = { params: { id: 'abc' } };

            expect(entryParamsMatchRoute({ params: { id: 'abc' } }, route)).toBe(true);
            expect(entryParamsMatchRoute({ params: { id: 'xyz' } }, route)).toBe(false);
        });

        it('compares params as strings', () => {
            expect(entryParamsMatchRoute({ params: { id: 5 } }, { params: { id: '5' } })).toBe(true);
        });
    });

    describe('isEntryOnActiveRoute', () => {
        it('lights a leaf whose route is in the matched chain', () => {
            const route = { matched: [{ name: 'ct.blog.index' }], params: {} };

            expect(isEntryOnActiveRoute({ path: 'ct.blog.index' }, route)).toBe(true);
        });

        it('does not light a leaf whose route is not in the matched chain', () => {
            const route = { matched: [{ name: 'ct.member.index' }], params: {} };

            expect(isEntryOnActiveRoute({ path: 'ct.blog.index' }, route)).toBe(false);
        });

        it('lights a path-less parent when a descendant route is in the matched chain', () => {
            const route = {
                matched: [
                    { name: 'ct.extension.my-extensions' },
                    { name: 'ct.extension.my-extensions.listing' },
                ],
                params: {},
            };
            const extensions = {
                id: 'ct-extension',
                children: [
                    { id: 'ct-extension-store', path: 'ct.extension.store', children: [] },
                    { id: 'ct-extension-my-extensions', path: 'ct.extension.my-extensions', children: [] },
                ],
            };

            expect(isEntryOnActiveRoute(extensions, route)).toBe(true);
        });

        it('does not light a path-less parent when no descendant is active', () => {
            const route = { matched: [{ name: 'ct.member.index' }], params: {} };
            const extensions = {
                id: 'ct-extension',
                children: [{ id: 'ct-extension-store', path: 'ct.extension.store', children: [] }],
            };

            expect(isEntryOnActiveRoute(extensions, route)).toBe(false);
        });

        it('disambiguates entries that share a route name by their params', () => {
            const route = { matched: [{ name: 'ct.channel.detail' }], params: { id: 'channel-a' } };
            const entryA = { id: 'a', path: 'ct.channel.detail', params: { id: 'channel-a' }, children: [] };
            const entryB = { id: 'b', path: 'ct.channel.detail', params: { id: 'channel-b' }, children: [] };

            expect(isEntryOnActiveRoute(entryA, route)).toBe(true);
            expect(isEntryOnActiveRoute(entryB, route)).toBe(false);
        });

        it('lights a leaf reachable only through the parentPath bridge', () => {
            const route = {
                name: 'ct.blog.detail.base',
                matched: [
                    { name: 'ct.blog.detail' },
                    { name: 'ct.blog.detail.base' },
                ],
                meta: { parentPath: 'ct.blog.index' },
                params: {},
            };
            const router = { getRoutes: () => [{ name: 'ct.blog.index', meta: {} }] };
            const activeNames = getActiveRouteNames(route, router);

            expect(isEntryOnActiveRoute({ path: 'ct.blog.index' }, route, activeNames)).toBe(true);
        });
    });
});
