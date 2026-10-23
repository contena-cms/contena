/**
 * @sw-package framework
 */
describe('FilenameToChunkNamePlugin', () => {
    let FilenameToChunkNamePlugin;

    beforeEach(() => {
        // The plugin keeps the already assigned chunk names in module scope.
        jest.isolateModules(() => {
            FilenameToChunkNamePlugin = require('../../build/webpack/FilenameToChunkNamePlugin');
        });
    });

    function runPlugin(chunks, rootModules) {
        let afterOptimizeChunkIds;
        const compilation = {
            hooks: {
                afterOptimizeChunkIds: {
                    tap: (name, callback) => {
                        afterOptimizeChunkIds = callback;
                    },
                },
            },
            chunkGraph: {
                getChunkRootModules: (chunk) => rootModules[chunk.id],
            },
        };
        const compiler = {
            hooks: {
                compilation: {
                    tap: (name, callback) => callback(compilation),
                },
            },
        };

        new FilenameToChunkNamePlugin().apply(compiler);
        afterOptimizeChunkIds(chunks);

        return chunks;
    }

    test('names the chunk after the file of its root module', () => {
        const [chunk] = runPlugin(
            [{ id: 'a1', runtime: 'frontend' }],
            { a1: [{ admin_userRequest: '/app/src/plugin/foo/foo.plugin.js' }] },
        );

        expect(chunk.name).toBe('frontend.foo.plugin');
    });

    test('strips the resource query from the chunk name', () => {
        const [chunk] = runPlugin(
            [{ id: 'a1', runtime: 'frontend' }],
            { a1: [{ admin_userRequest: '/app/node_modules/three/examples/jsm/libs/draco/draco_wasm_wrapper.js?raw' }] },
        );

        expect(chunk.name).toBe('frontend.draco_wasm_wrapper');
    });

    test('uses the concatenated root module and appends the chunk id to duplicate names', () => {
        const chunks = runPlugin(
            [
                { id: 'a1', runtime: 'frontend' },
                { id: 'b2', runtime: 'frontend' },
            ],
            {
                a1: [{ rootModule: { admin_userRequest: '/app/src/a/index.js' } }],
                b2: [{ admin_userRequest: '/app/src/b/index.js' }],
            },
        );

        expect(chunks.map((chunk) => chunk.name)).toEqual([
            'frontend.index',
            'frontend.index.b2',
        ]);
    });

    test('keeps chunks that already have a name', () => {
        const [chunk] = runPlugin(
            [{ id: 'a1', runtime: 'frontend', name: 'frontend' }],
            { a1: [{ admin_userRequest: '/app/src/main.js' }] },
        );

        expect(chunk.name).toBe('frontend');
    });
});
