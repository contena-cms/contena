import type { ContainedBlock } from './block-inspector-dom';
import {
    blockNameFromNodeId,
    blockNodeId,
    buildBlockTree,
    pickedNodeId,
    TAG_EXTENDED,
    TAG_NATIVE,
    TAG_PICKED,
    type TreeBlock,
} from './block-inspector-tree';

const blocks: TreeBlock[] = [
    { name: 'ct_blog_detail', component: 'ct-blog-detail', extended: false },
    { name: 'ct_blog_detail_base', component: 'ct-blog-detail-base', extended: false },
    { name: 'ct_blog_detail_base_content', component: 'ct-blog-detail-base', extended: true },
    { name: 'converted-block', component: 'ct-converted', extended: false },
];

// ct_blog_detail > ct_blog_detail_base > { ct_blog_detail_base_content, converted-block }
const hierarchy: ContainedBlock[] = [
    {
        name: 'ct_blog_detail',
        children: [
            {
                name: 'ct_blog_detail_base',
                children: [
                    { name: 'ct_blog_detail_base_content', children: [] },
                    { name: 'converted-block', children: [] },
                ],
            },
        ],
    },
];

type OutlineNode = { label: string; children?: unknown[] };

/** Labels of a tree, nested the same way, for compact assertions. */
function outline(nodes: OutlineNode[]): unknown[] {
    return nodes.map((node) => {
        const children = (node.children ?? []) as OutlineNode[];

        return children.length
            ? [
                  node.label,
                  outline(children),
              ]
            : node.label;
    });
}

describe('adapter/view/block-inspector/block-inspector-tree', () => {
    describe('node ids', () => {
        it('carry the generation and resolve back to the block name', () => {
            expect(blockNodeId('a', 3)).toBe('3|block:a');
            expect(pickedNodeId('a', 3)).toBe('3|picked:a');
            expect(blockNameFromNodeId('3|block:a')).toBe('a');
            expect(blockNameFromNodeId('3|picked:a')).toBe('a');
        });

        it('resolve ids without a generation and reject other nodes', () => {
            expect(blockNameFromNodeId('block:a')).toBe('a');
            expect(blockNameFromNodeId('3|component:ct-x')).toBeNull();
            expect(blockNameFromNodeId('disabled')).toBeNull();
        });
    });

    describe('buildBlockTree', () => {
        it('nests the blocks the way they nest in the page', () => {
            expect(outline(buildBlockTree(hierarchy, blocks, { generation: 1 }))).toEqual([
                [
                    'ct_blog_detail',
                    [
                        [
                            'ct_blog_detail_base',
                            [
                                'ct_blog_detail_base_content',
                                'converted-block',
                            ],
                        ],
                    ],
                ],
            ]);
        });

        it('tags every block with its kind and marks the extended ones', () => {
            const tree = buildBlockTree(hierarchy, blocks, { generation: 1 });
            const base = tree[0]?.children?.[0];

            expect(tree[0]?.id).toBe('1|block:ct_blog_detail');
            expect(tree[0]?.tags).toEqual([TAG_NATIVE]);
            expect(base?.children?.[0]?.tags).toEqual([
                TAG_NATIVE,
                TAG_EXTENDED,
            ]);
            expect(base?.children?.[1]?.tags).toEqual([TAG_NATIVE]);
        });

        it('keeps the path to a match when filtering', () => {
            // The blocks above the match do not match themselves but have to stay, or the match
            // cannot be reached in the tree.
            expect(outline(buildBlockTree(hierarchy, blocks, { generation: 1, filter: 'BASE_CONTENT' }))).toEqual([
                [
                    'ct_blog_detail',
                    [
                        [
                            'ct_blog_detail_base',
                            ['ct_blog_detail_base_content'],
                        ],
                    ],
                ],
            ]);
        });

        it('matches the component a block belongs to', () => {
            expect(outline(buildBlockTree(hierarchy, blocks, { generation: 1, filter: 'ct-converted' }))).toEqual([
                [
                    'ct_blog_detail',
                    [
                        [
                            'ct_blog_detail_base',
                            ['converted-block'],
                        ],
                    ],
                ],
            ]);
        });

        it('is empty when nothing matches', () => {
            expect(buildBlockTree(hierarchy, blocks, { generation: 1, filter: 'nothing' })).toEqual([]);
        });

        it('shows only the picked block and what is inside it, ignoring the filter', () => {
            const tree = buildBlockTree(hierarchy, blocks, {
                generation: 2,
                filter: 'converted',
                pick: {
                    blockName: 'ct_blog_detail_base',
                    contained: [{ name: 'ct_blog_detail_base_content', children: [] }],
                },
            });

            expect(tree[0]).toEqual({
                id: '2|picked:ct_blog_detail_base',
                label: 'ct_blog_detail_base',
                tags: [
                    TAG_PICKED,
                    TAG_NATIVE,
                ],
                children: [
                    {
                        id: '2|picked:ct_blog_detail_base_content',
                        label: 'ct_blog_detail_base_content',
                        tags: [
                            TAG_NATIVE,
                            TAG_EXTENDED,
                        ],
                        children: [],
                    },
                ],
            });
            // Focused on the pick: the block appears once, not next to a second copy in the page tree.
            expect(tree).toHaveLength(1);
        });

        it('changes every node id with the generation so a stale selection cannot survive a pick', () => {
            const collect = (nodes: { id: string; children?: unknown[] }[]): string[] =>
                nodes.flatMap((node) => [
                    node.id,
                    ...collect((node.children ?? []) as { id: string; children?: unknown[] }[]),
                ]);
            const before = collect(buildBlockTree(hierarchy, blocks, { generation: 1 }));
            const after = collect(buildBlockTree(hierarchy, blocks, { generation: 2 }));

            expect(before.some((id) => after.includes(id))).toBe(false);
        });

        it('still lists a picked block that has left the DOM, without kind tags', () => {
            const tree = buildBlockTree([], [], { generation: 1, pick: { blockName: 'gone', contained: [] } });

            expect(tree).toEqual([{ id: '1|picked:gone', label: 'gone', tags: [TAG_PICKED], children: [] }]);
        });
    });
});
