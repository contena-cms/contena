/**
 * @sw-package framework
 *
 * You can activate acl roles in the each test like this:
 * global.activeAclRoles = ['blog.editor'];
 */

global.activeAclRoles = [];

const aclService = {
    can: (key) => {
        if (!key) {
            return true;
        }

        return global.activeAclRoles.includes(key);
    },
    hasAccessToRoute: () => true,
};

export default aclService;
