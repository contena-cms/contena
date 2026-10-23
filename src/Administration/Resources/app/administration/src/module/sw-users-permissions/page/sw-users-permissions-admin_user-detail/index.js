/**
 * @sw-package fundamentals@framework
 */
import useTheme from 'src/app/composables/use-theme';
import useModuleIconColors from 'src/app/composables/use-module-icon-colors';
import template from './sw-users-permissions-admin_user-detail.html.twig';
import './sw-users-permissions-admin_user-detail.scss';

const { Component, Mixin } = Contena;
const { Criteria } = Contena.Data;
const { mapPropertyErrors } = Component.getComponentHelper();
const { warn } = Contena.Utils.debug;
const { ContenaError } = Contena.Classes;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'admin_userService',
        'loginService',
        'mediaDefaultFolderService',
        'admin_userValidationService',
        'integrationService',
        'repositoryFactory',
        'acl',
        'feature',
    ],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('salutation')],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel',
    },

    data() {
        return {
            isLoading: false,
            admin_userId: '',
            admin_user: null,
            currentUser: null,
            languages: [],
            integrations: [],
            currentIntegration: null,
            mediaItem: null,
            newPassword: '',
            newPasswordConfirm: '',
            isEmailAlreadyInUse: false,
            isUsernameUsed: false,
            isIntegrationsLoading: false,
            isSaveSuccessful: false,
            isModalLoading: false,
            showSecretAccessKey: false,
            showDeleteModal: null,
            skeletonItemAmount: 3,
            confirmPasswordModal: false,
            timezoneOptions: [],
            mediaDefaultFolderId: null,
            showMediaModal: false,
            // Only edited for the own admin_user — the theme select & module icon colors select are hidden otherwise.
            admin_userThemeSelection: null,
            admin_userModuleIconColorsSelection: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        admin_userTheme: {
            get() {
                return this.admin_userThemeSelection ?? useTheme().theme.value;
            },
            set(theme) {
                this.admin_userThemeSelection = theme;
            },
        },

        admin_userModuleIconColors: {
            get() {
                const enabled = this.admin_userModuleIconColorsSelection ?? useModuleIconColors().enabled.value;

                return enabled ? 'module' : 'neutral';
            },
            set(value) {
                this.admin_userModuleIconColorsSelection = value === 'module';
            },
        },

        ...mapPropertyErrors('admin_user', [
            'firstName',
            'lastName',
            'email',
            'username',
            'localeId',
            'password',
        ]),

        identifier() {
            return this.fullName;
        },

        fullName() {
            return this.salutation(this.admin_user, this.$t('sw-users-permissions.users.admin_user-detail.labelNewUser'));
        },

        admin_userRepository() {
            return this.repositoryFactory.create('admin_user');
        },

        admin_userCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('accessKeys');
            criteria.addAssociation('locale');
            criteria.addAssociation('aclRoles');

            return criteria;
        },

        aclRoleCriteria() {
            const criteria = new Criteria(1, 25);

            // Roles created by apps should not be assignable in the admin
            criteria.addFilter(Criteria.equals('app.id', null));
            criteria.addFilter(Criteria.equals('deletedAt', null));

            return criteria;
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        languageCriteria() {
            const criteria = new Criteria(1, 500);

            criteria.addAssociation('locale');
            criteria.addSorting(Criteria.sort('locale.name', 'ASC'));
            criteria.addSorting(Criteria.sort('locale.territory', 'ASC'));

            return criteria;
        },

        localeRepository() {
            return this.repositoryFactory.create('locale');
        },

        avatarMedia() {
            return this.mediaItem;
        },

        isError() {
            return this.isEmailAlreadyInUse || this.isUsernameUsed || !this.hasLanguage;
        },

        hasLanguage() {
            return this.admin_user && this.admin_user.localeId;
        },

        disableConfirm() {
            return this.newPassword !== this.newPasswordConfirm || this.newPassword === '' || this.newPassword === null;
        },

        isCurrentUser() {
            if (!this.admin_user || !this.currentUser) {
                return false;
            }

            return this.admin_userId === this.currentUser.id;
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        integrationColumns() {
            return [
                {
                    property: 'accessKey',
                    label: this.$t('sw-users-permissions.users.admin_user-detail.labelAccessKey'),
                },
            ];
        },

        languageId() {
            return Contena.Store.get('session').languageId;
        },

        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        localeOptions() {
            return this.languages.map((language) => {
                return {
                    id: language.locale.id,
                    value: language.locale.id,
                    label: language.customLabel,
                };
            });
        },

        moduleIconColorsOptions() {
            return [
                {
                    value: 'neutral',
                    label: this.$t('sw-profile.index.optionModuleIconColorsNeutral'),
                },
                {
                    value: 'module',
                    label: this.$t('sw-profile.index.optionModuleIconColorsColored'),
                },
            ];
        },

        mcpGrantedPrivileges() {
            if (!this.admin_user?.aclRoles) {
                return [];
            }

            return [...new Set(this.admin_user.aclRoles.flatMap((role) => role.privileges ?? []))];
        },
    },

    watch: {
        languageId() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            // Create the theme & module icon colors singleton before the first render — creating it inside a computed would trigger Vue's onMounted warning
            useTheme();
            useModuleIconColors();

            Contena.ExtensionAPI.publishData({
                id: 'sw-users-permissions-admin_user-detail__currentUser',
                path: 'currentUser',
                scope: this,
            });

            Contena.ExtensionAPI.publishData({
                id: 'sw-users-permissions-admin_user-detail__user',
                path: 'admin_user',
                scope: this,
            });

            this.isLoading = true;

            if (!this.languageId) {
                this.isLoading = false;
                return;
            }

            this.getMediaDefaultFolderId()
                .then((id) => {
                    this.mediaDefaultFolderId = id;
                })
                .catch(() => {
                    this.mediaDefaultFolderId = null;
                });

            this.timezoneOptions = Contena.Service('timezoneService').getTimezoneOptions();
            const languagePromise = new Promise((resolve) => {
                Contena.Store.get('context').api.languageId = this.languageId;
                resolve(this.languageId);
            });

            const promises = [
                languagePromise,
                this.loadLanguages(),
                this.loadUser(),
                this.loadCurrentUser(),
            ];

            Promise.all(promises).then(() => {
                this.isLoading = false;
            });
        },

        loadLanguages() {
            return this.languageRepository.search(this.languageCriteria).then((result) => {
                this.languages = [];
                result.forEach((lang) => {
                    lang.customLabel = `${lang.locale.translated.name} (${lang.locale.translated.territory})`;
                    this.languages.push(lang);
                });

                return this.languages;
            });
        },

        loadUser() {
            this.admin_userId = this.$route.params.id?.toLowerCase();

            return this.admin_userRepository.get(this.admin_userId, Contena.Context.api, this.admin_userCriteria).then((admin_user) => {
                this.admin_user = admin_user;

                if (this.admin_user.avatarId) {
                    this.loadMediaItem(this.admin_user.avatarId);
                }

                this.keyRepository = this.repositoryFactory.create(admin_user.accessKeys.entity, this.admin_user.accessKeys.source);
                this.loadKeys();
            });
        },

        loadCurrentUser() {
            return this.admin_userService.getUser().then((response) => {
                this.currentUser = response.data;
            });
        },

        loadKeys() {
            this.integrations = this.admin_user.accessKeys;
        },

        addAccessKey() {
            const newKey = this.keyRepository.create();

            this.isModalLoading = true;
            newKey.quantityStart = 1;
            this.integrationService.generateKey({}, {}, true).then((response) => {
                newKey.accessKey = response.accessKey;
                newKey.secretAccessKey = response.secretAccessKey;
                this.currentIntegration = newKey;
                this.isModalLoading = false;
                this.showSecretAccessKey = true;
            });
        },

        async checkEmail() {
            if (!this.admin_user.email) {
                return true;
            }

            const { emailIsUnique } = await this.admin_userValidationService.checkUserEmail({
                email: this.admin_user.email,
                id: this.admin_user.id,
            });

            this.isEmailAlreadyInUse = !emailIsUnique;

            if (this.isEmailAlreadyInUse) {
                const expression = `admin_user.${this.admin_user.id}.email`;
                const error = new ContenaError({
                    code: 'USER_EMAIL_ALREADY_EXISTS',
                    detail: this.$t('sw-users-permissions.users.admin_user-detail.errorEmailUsed'),
                });

                Contena.Store.get('error').addApiError({
                    expression,
                    error,
                });
                return false;
            }

            return true;
        },

        checkUsername() {
            return this.admin_userValidationService
                .checkUserUsername({
                    username: this.admin_user.username,
                    id: this.admin_user.id,
                })
                .then(({ usernameIsUnique }) => {
                    this.isUsernameUsed = !usernameIsUnique;
                });
        },

        loadMediaItem(targetId) {
            this.mediaRepository.get(targetId).then((media) => {
                this.mediaItem = media;
                this.admin_user.avatarMedia = media;
            });
        },

        setMediaItem({ targetId }) {
            this.admin_user.avatarId = targetId;
            this.loadMediaItem(targetId);
        },

        onUnlinkLogo() {
            this.mediaItem = null;
            this.admin_user.avatarMedia = null;
            this.admin_user.avatarId = null;
        },

        onDropMedia(mediaItem) {
            this.setMediaItem({ targetId: mediaItem.id });
        },

        onOpenMedia() {
            this.showMediaModal = true;
        },

        onMediaSelectionChange([mediaEntity]) {
            this.mediaItem = mediaEntity;
            this.admin_user.avatarMedia = mediaEntity;
            this.admin_user.avatarId = mediaEntity.id;
        },

        getMediaDefaultFolderId() {
            return this.mediaDefaultFolderService.getDefaultFolderId('admin_user');
        },

        onSearch(value) {
            this.term = value;
            this.clearSelection();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.confirmPasswordModal = true;
        },

        async saveUser(context) {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            try {
                if (this.currentUser.id === this.admin_user.id) {
                    await Contena.Service('localeHelper').setLocaleWithId(this.admin_user.localeId);
                }

                const isEmailValid = await this.checkEmail();

                if (!isEmailValid) {
                    return;
                }

                await this.admin_userRepository.save(this.admin_user, context);

                if (this.currentUser.id === this.admin_user.id) {
                    if (this.admin_user.password) {
                        await this.updateAuthToken();
                    }
                    await this.updateCurrentUser();
                    await useTheme().saveUserTheme(this.admin_userTheme);
                    await useModuleIconColors().saveUserModuleIconColors(this.admin_userModuleIconColors === 'module');
                    this.admin_userThemeSelection = null;
                    this.admin_userModuleIconColorsSelection = null;
                }

                this.createdComponent();

                this.confirmPasswordModal = false;
                this.isSaveSuccessful = true;
            } catch (exception) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t(
                        'sw-users-permissions.users.admin_user-detail.notification.saveError.message',
                        { name: this.fullName },
                        0,
                    ),
                });
                warn(this._name, exception.message, exception.response);
                throw exception;
            } finally {
                this.isLoading = false;
            }
        },

        async updateCurrentUser() {
            await this.admin_userService.getUser().then((response) => {
                const data = response.data;
                delete data.password;
                Contena.Store.get('session').setCurrentUser(data);
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.users.permissions.index' });
        },

        setPassword(password) {
            if (typeof password === 'string' && password.length <= 0) {
                delete this.admin_user.password;
                return;
            }

            this.admin_user.password = password;
        },

        onShowDetailModal(id) {
            if (!id) {
                this.addAccessKey();
                return;
            }

            this.currentIntegration = this.admin_user.accessKeys.get(id);
        },

        onCloseDetailModal() {
            this.currentIntegration = null;
            this.showSecretAccessKey = false;
            this.isModalLoading = false;
        },

        onSaveIntegration() {
            if (!this.currentIntegration) {
                return;
            }

            if (!this.admin_user.accessKeys.has(this.currentIntegration.id)) {
                this.admin_user.accessKeys.add(this.currentIntegration);
            }

            this.onCloseDetailModal();
        },

        onMcpAllowlistUpdate(allowlist) {
            if (!this.admin_userId) {
                return;
            }

            this.admin_userService
                .saveMcpAllowlist(this.admin_userId, allowlist)
                .then(() => {
                    this.admin_user.mcpAllowlist = allowlist;
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-users-permissions.users.admin_user-detail.mcpAllowlistSaveError'),
                    });
                });
        },

        onCloseDeleteModal() {
            this.showDeleteModal = null;
        },

        onConfirmDelete(id) {
            if (!id) {
                return;
            }

            this.onCloseDeleteModal();
            this.admin_user.accessKeys.remove(id);
        },

        onCloseConfirmPasswordModal() {
            this.confirmPasswordModal = false;
        },

        async updateAuthToken() {
            const verifiedToken = await this.loginService.verifyUserToken(this.admin_user.password);
            Contena.Store.get('context').api.authToken.access = verifiedToken;
            const authObject = {
                ...this.loginService.getBearerAuthentication(),
                access: verifiedToken,
            };
            this.loginService.setBearerAuthentication(authObject);
        },
    },
};
