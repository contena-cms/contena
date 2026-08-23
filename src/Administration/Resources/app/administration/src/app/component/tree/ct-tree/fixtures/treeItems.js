const ROOT_ID = 'a1d1da1e6d434902a2e5ffed7784c951';
const IDENTITY_ID = 'd3aabfa637cf435e8ad3c9bf1d2de565';

function createTreeItem({ id, name, parentId = null, childCount = 0, afterId = null, path = null }) {
    return {
        id,
        name,
        parentId,
        afterId,
        childCount,
        path,
        data: {
            parentId,
            childCount,
        },
        children: [],
    };
}

export default function getTreeItems() {
    return [
        createTreeItem({
            id: ROOT_ID,
            name: 'Platform',
            childCount: 2,
        }),
        createTreeItem({
            id: IDENTITY_ID,
            name: 'Identity',
            parentId: ROOT_ID,
            childCount: 5,
            path: `|${ROOT_ID}|`,
        }),
        createTreeItem({
            id: '8da86665f27740dd8160c92e27b1c4c8',
            name: 'Operations',
            parentId: ROOT_ID,
            afterId: IDENTITY_ID,
            path: `|${ROOT_ID}|`,
        }),
        createTreeItem({
            id: '37a310885dce42169848338ec1fe9d73',
            name: 'Users',
            parentId: IDENTITY_ID,
            path: `|${ROOT_ID}|${IDENTITY_ID}|`,
        }),
        createTreeItem({
            id: '82dcfd4ada3e41a1a2451b9cbfb1ad81',
            name: 'Roles',
            parentId: IDENTITY_ID,
            afterId: '37a310885dce42169848338ec1fe9d73',
            path: `|${ROOT_ID}|${IDENTITY_ID}|`,
        }),
        createTreeItem({
            id: 'f547f999d3714a3aa620e02e7e299cc5',
            name: 'Integrations',
            parentId: IDENTITY_ID,
            afterId: '82dcfd4ada3e41a1a2451b9cbfb1ad81',
            path: `|${ROOT_ID}|${IDENTITY_ID}|`,
        }),
        createTreeItem({
            id: 'c03fdf1aaa534b7f8631bf16705ef213',
            name: 'Queues',
            parentId: IDENTITY_ID,
            afterId: 'f547f999d3714a3aa620e02e7e299cc5',
            path: `|${ROOT_ID}|${IDENTITY_ID}|`,
        }),
        createTreeItem({
            id: '42e05522afbd4a8183f143318c2fc4ca',
            name: 'Scheduled tasks',
            parentId: IDENTITY_ID,
            afterId: 'c03fdf1aaa534b7f8631bf16705ef213',
            path: `|${ROOT_ID}|${IDENTITY_ID}|`,
        }),
    ];
}
