import FlatTree from 'src/core/helper/flattree.helper';

describe('core/helper/flattree.helper.js', () => {
    let flatTree;

    beforeEach(async () => {
        flatTree = new FlatTree();
    });

    it('should register new nodes', async () => {
        flatTree
            .add({
                label: 'Foobar',
                id: 'ct.foo.bar',
            })
            .add({
                label: 'BarBatz',
                id: 'ct.bar.batz',
            });

        expect(flatTree.convertToTree()).toEqual([
            expect.objectContaining({
                id: 'ct.foo.bar',
            }),
            expect.objectContaining({
                id: 'ct.bar.batz',
            }),
        ]);
    });

    it('expects a node to have a path or id property', async () => {
        const warnSpy = jest.fn();
        jest.spyOn(global.console, 'warn').mockImplementation(warnSpy);
        flatTree
            .add({
                label: 'with path',
                path: '/foo/bar',
            })
            .add({
                label: 'with id',
                id: 'foo.bar',
            })
            .add({
                label: 'without id and path',
            });

        expect(flatTree.convertToTree()).toEqual([
            expect.objectContaining({
                label: 'with path',
            }),
            expect.objectContaining({
                label: 'with id',
            }),
        ]);

        expect(warnSpy).toHaveBeenCalledWith(
            '[FlatTree]',
            'The node needs an "id" or "path" property. Abort registration.',
            expect.objectContaining({
                label: 'without id and path',
            }),
        );
    });

    it('should not be possible to register different nodes with the same id', async () => {
        const warnSpy = jest.fn();
        jest.spyOn(global.console, 'warn').mockImplementation(warnSpy);

        flatTree
            .add({
                label: 'Foobar',
                id: 'ct.foo.bar',
            })
            .add({
                label: 'BarBatz',
                id: 'ct.foo.bar',
            });

        const tree = flatTree.convertToTree();

        expect(tree).toHaveLength(1);
        expect(tree[0]).toEqual({
            position: 1,
            level: 1,
            label: 'Foobar',
            id: 'ct.foo.bar',
            children: [],
        });

        // [FlatTree] Tree contains node with unique identifier ct.foo.bar already. Please remove it first before adding a new one. { label: 'Foobar', id: 'ct.foo.bar', position: 1 }
        expect(warnSpy).toHaveBeenCalledWith(
            '[FlatTree]',
            'Tree contains node with unique identifier ct.foo.bar already.',
            'Please remove it first before adding a new one.',
            expect.objectContaining({
                label: 'Foobar',
                id: 'ct.foo.bar',
                position: 1,
            }),
        );
    });

    it('automatically sets target to _self if a link is specified', async () => {
        flatTree.add({
            id: 'foo.bar',
            link: 'https://contena.cn',
        });

        expect(flatTree.convertToTree()).toEqual([
            expect.objectContaining({
                id: 'foo.bar',
                link: 'https://contena.cn',
                target: '_self',
            }),
        ]);
    });

    it('should be possible to remove nodes from the tree', async () => {
        flatTree.add({
            label: 'Foobar',
            id: 'ct.foo.bar',
        });

        expect(flatTree.convertToTree()).toEqual([
            expect.objectContaining({
                id: 'ct.foo.bar',
            }),
        ]);

        flatTree.remove('ct.foo.bar');

        expect(flatTree.convertToTree()).toHaveLength(0);
    });

    it('should not remove a node when the node identifier does not match', async () => {
        flatTree
            .add({
                label: 'Foobar',
                id: 'ct.foo.bar',
            })
            .remove('ct.foo.batz');

        expect(flatTree.convertToTree()).not.toEqual(
            expect.arrayContaining[
                expect.objectContaining({
                    id: 'ct.foo.bar',
                })
            ],
        );
    });

    it('should be possible to nest child nodes infinitely (4 levels here)', async () => {
        flatTree
            .add({
                id: 'ct.a',
            })
            .add({
                id: 'ct.b',
                parent: 'ct.a',
            })
            .add({
                id: 'ct.c',
                parent: 'ct.b',
            })
            .add({
                id: 'ct.d',
                parent: 'ct.c',
            });

        expect(flatTree.convertToTree()).toEqual([
            expect.objectContaining({
                id: 'ct.a',
                level: 1,
                children: [
                    expect.objectContaining({
                        id: 'ct.b',
                        level: 2,
                        children: [
                            expect.objectContaining({
                                id: 'ct.c',
                                level: 3,
                                children: [
                                    expect.objectContaining({
                                        id: 'ct.d',
                                        level: 4,
                                        children: [],
                                    }),
                                ],
                            }),
                        ],
                    }),
                ],
            }),
        ]);
    });

    it('should create a tree hierarchy', async () => {
        flatTree
            .add({
                id: 'ct.a',
            })
            .add({
                id: 'ct.a_child_1',
                parent: 'ct.a',
            })
            .add({
                id: 'ct.b',
            })
            .add({
                id: 'ct.a_child_2',
                parent: 'ct.a',
            })
            .add({
                id: 'ct.b_child_1',
                parent: 'ct.b',
            });

        expect(flatTree.convertToTree()).toEqual([
            expect.objectContaining({
                id: 'ct.a',
                level: 1,
                children: [
                    expect.objectContaining({
                        id: 'ct.a_child_1',
                        parent: 'ct.a',
                        level: 2,
                    }),
                    expect.objectContaining({
                        id: 'ct.a_child_2',
                        parent: 'ct.a',
                        level: 2,
                    }),
                ],
            }),
            expect.objectContaining({
                id: 'ct.b',
                level: 1,
                children: [
                    expect.objectContaining({
                        id: 'ct.b_child_1',
                        parent: 'ct.b',
                        level: 2,
                    }),
                ],
            }),
        ]);
    });

    it('respects sorting function for children if given', async () => {
        flatTree = new FlatTree((first, second) => {
            return first.position - second.position;
        });

        flatTree
            .add({
                id: 'ct.30',
                position: 30,
            })
            .add({
                id: 'ct.10',
                position: 10,
            })
            .add({
                id: 'ct.20',
                position: 20,
            })
            .add({
                id: 'ct.40',
                position: 40,
            });

        expect(flatTree.convertToTree()).toEqual([
            expect.objectContaining({
                position: 10,
            }),
            expect.objectContaining({
                position: 20,
            }),
            expect.objectContaining({
                position: 30,
            }),
            expect.objectContaining({
                position: 40,
            }),
        ]);
    });
});
