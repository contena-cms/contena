/**
 * @sw-package fundamentals@framework
 */
import template from './sw-users-permissions-admin_user-listing.html.twig';
import './sw-users-permissions-admin_user-listing.scss';

const { Data, Mixin } = Contena;
const { Criteria } = Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'admin_userService',
        /** @deprecated tag:v6.8.0 - Will be removed. Extend sw-verify-admin_user-modal instead. */
        'loginService',
        'repositoryFactory',
        'acl',
        'ssoSettingsService',
    ],

    emits: ['get-list'],

    mixins: [Mixin.getByName('listing'), Mixin.getByName('notification'), Mixin.getByName('salutation')],

    created() {
        this.ssoSettingsService.isSso().then((response) => {
            this.isSso = response.isSso;
        });
    },

    data() {
        return {
            admin_user: [],
            isLoading: false,
            itemToDelete: null,
            disableRouteParams: true,
            /** @deprecated tag:v6.8.0 - Will be removed. Extend sw-verify-admin_user-modal instead. */
            confirmPassword: '',
            sortBy: 'username',
            /** @deprecated tag:v6.8.0 - Will be removed. Extend sw-verify-admin_user-modal instead. */
            isConfirmingPassword: false,
            isConfirmDeleteModalOpen: false,
            isConfirmingPasswordModalOpen: false,
            showInvitationModal: false,
            isSso: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        admin_userRepository() {
            return this.repositoryFactory.create('admin_user');
        },

        currentUser: {
            get() {
                return Contena.Store.get('session').currentUser;
            },
        },

        admin_userDetailRouterLink() {
            return this.isSso ? 'sw.users.permissions.admin_user.sso.detail' : 'sw.users.permissions.admin_user.detail';
        },

        admin_userCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            if (this.term) {
                criteria.setTerm(this.term);
            }

            if (this.sortBy) {
                criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection || 'ASC'));
            }

            criteria.addAssociation('aclRoles');
            criteria.addAssociation('avatarMedia');

            return criteria;
        },

        admin_userColumns() {
            if (this.isSso) {
                return [
                    {
                        property: 'email',
                        label: this.$t('sw-users-permissions.users.admin_user-grid.labelEmail'),
                    },
                    {
                        property: 'aclRoles',
                        sortable: false,
                        label: this.$t('sw-users-permissions.users.admin_user-grid.labelRoles'),
                    },
                    {
                        property: 'status',
                        label: this.$t('sw-users-permissions.users.admin_user-grid.status'),
                    },
                ];
            }

            return [
                {
                    property: 'username',
                    label: this.$t('sw-users-permissions.users.admin_user-grid.labelUsername'),
                },
                {
                    property: 'firstName',
                    label: this.$t('sw-users-permissions.users.admin_user-grid.labelFirstName'),
                },
                {
                    property: 'lastName',
                    label: this.$t('sw-users-permissions.users.admin_user-grid.labelLastName'),
                },
                {
                    property: 'aclRoles',
                    sortable: false,
                    label: this.$t('sw-users-permissions.users.admin_user-grid.labelRoles'),
                },
                {
                    property: 'email',
                    label: this.$t('sw-users-permissions.users.admin_user-grid.labelEmail'),
                },
                {
                    property: 'status',
                    label: this.$t('sw-users-permissions.users.admin_user-grid.status'),
                },
            ];
        },
    },

    methods: {
        getItemToDelete(item) {
            if (!this.itemToDelete) {
                return false;
            }
            return this.itemToDelete.id === item.id;
        },

        onSearch(value) {
            this.term = value;

            this.getList();
        },

        getList() {
            this.isLoading = true;
            this.admin_user = [];

            this.$emit('get-list');

            return this.admin_userRepository
                .search(this.admin_userCriteria)
                .then((users) => {
                    this.total = users.total;
                    this.admin_user = users;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onDelete(admin_user) {
            this.itemToDelete = admin_user;
            this.isConfirmDeleteModalOpen = true;
        },

        onUserInvited() {
            this.getList();
            this.closeInvitationModal();
        },

        openInvitationModal() {
            this.showInvitationModal = true;
        },

        closeInvitationModal() {
            this.showInvitationModal = false;
        },

        invitationFailed() {
            this.createNotificationError({
                title: this.$t('global.default.error'),
                message: this.$t('sw-users-permissions.sso.error.cannotInviteUser'),
            });
        },

        onConfirmDelete(admin_user) {
            if (admin_user.id === this.currentUser.id) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('sw-users-permissions.users.admin_user-grid.notification.deleteUserLoggedInError.message'),
                });

                this.onCloseDeleteModal();

                return;
            }

            this.isConfirmDeleteModalOpen = false;

            if (this.isSso) {
                this.deleteUser({ ...Contena.Context.api });

                return;
            }

            this.isConfirmingPasswordModalOpen = true;
        },

        deleteUser(context) {
            const admin_user = this.itemToDelete;
            const username = `${admin_user.firstName} ${admin_user.lastName} `;
            const titleDeleteSuccess = this.$t('global.default.success');
            const messageDeleteSuccess = this.$t(
                'sw-users-permissions.users.admin_user-grid.notification.deleteSuccess.message',
                { name: username },
                0,
            );
            const titleDeleteError = this.$t('global.default.error');
            const messageDeleteError = this.$t(
                'sw-users-permissions.users.admin_user-grid.notification.deleteError.message',
                {
                    name: username,
                },
                0,
            );

            this.isConfirmingPasswordModalOpen = false;

            this.admin_userRepository
                .delete(admin_user.id, context)
                .then(() => {
                    this.createNotificationSuccess({
                        title: titleDeleteSuccess,
                        message: messageDeleteSuccess,
                    });
                    this.getList();
                })
                .catch(() => {
                    this.createNotificationError({
                        title: titleDeleteError,
                        message: messageDeleteError,
                    });
                });

            this.itemToDelete = null;
        },

        onCloseConfirmPasswordModal() {
            this.isConfirmingPasswordModalOpen = false;
        },

        onCloseDeleteModal() {
            this.isConfirmDeleteModalOpen = false;
            this.itemToDelete = null;
        },
    },
};
