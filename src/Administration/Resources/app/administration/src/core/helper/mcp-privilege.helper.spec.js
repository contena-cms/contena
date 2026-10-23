/**
 * @sw-package fundamentals@framework
 */
import { colonToDot, isPrivilegeGranted, computePrivilegeChips } from 'src/core/helper/mcp-privilege.helper';

describe('colonToDot', () => {
    it('converts read → viewer', () => {
        expect(colonToDot('blog:read')).toBe('blog.viewer');
    });

    it('converts update → editor', () => {
        expect(colonToDot('order:update')).toBe('order.editor');
    });

    it('converts create → creator', () => {
        expect(colonToDot('channel:create')).toBe('channel.creator');
    });

    it('converts delete → deleter', () => {
        expect(colonToDot('user:delete')).toBe('user.deleter');
    });

    it('returns null for unknown operation', () => {
        expect(colonToDot('blog:unknown')).toBeNull();
    });

    it('returns null for dynamic chip placeholder', () => {
        expect(colonToDot('<entity>:read')).toBeNull();
    });

    it('returns null for nullish input instead of throwing', () => {
        expect(colonToDot(undefined)).toBeNull();
        expect(colonToDot(null)).toBeNull();
    });
});

describe('isPrivilegeGranted', () => {
    it('returns true when privilege is in colon format in the list', () => {
        expect(isPrivilegeGranted('channel:read', ['channel:read'])).toBe(true);
    });

    it('returns true when privilege is covered by dot-format role from General tab', () => {
        expect(isPrivilegeGranted('channel:read', ['channel.viewer'])).toBe(true);
        expect(isPrivilegeGranted('order:update', ['order.editor'])).toBe(true);
        expect(isPrivilegeGranted('blog:create', ['blog.creator'])).toBe(true);
        expect(isPrivilegeGranted('user:delete', ['user.deleter'])).toBe(true);
    });

    it('returns false when privilege is not in the list in any format', () => {
        expect(isPrivilegeGranted('order:create', ['channel.viewer'])).toBe(false);
        expect(isPrivilegeGranted('order:create', [])).toBe(false);
    });

    it('returns false for dynamic chip placeholders that have no matching entry', () => {
        expect(isPrivilegeGranted('<entity>:read', [])).toBe(false);
    });

    it('returns false for nullish chips instead of throwing', () => {
        expect(isPrivilegeGranted(undefined, ['blog.viewer'])).toBe(false);
        expect(isPrivilegeGranted(null, ['blog.viewer'])).toBe(false);
    });
});

describe('computePrivilegeChips', () => {
    it('returns an empty list for nullish required privileges', () => {
        expect(computePrivilegeChips(null)).toStrictEqual([]);
        expect(computePrivilegeChips(undefined)).toStrictEqual([]);
    });

    it('drops nullish static privilege entries', () => {
        expect(
            computePrivilegeChips({
                static: [
                    'blog:read',
                    undefined,
                    null,
                    'order:update',
                ],
            }),
        ).toStrictEqual(['blog:read', 'order:update']);
    });

    it('expands entity/operation pairs into dynamic chips', () => {
        expect(
            computePrivilegeChips({
                static: ['blog:read'],
                entityParam: 'entity',
                operations: ['read', 'update'],
            }),
        ).toStrictEqual(['blog:read', '<entity>:read', '<entity>:update']);
    });
});
