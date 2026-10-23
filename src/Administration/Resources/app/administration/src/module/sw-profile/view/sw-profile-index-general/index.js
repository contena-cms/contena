/**
 * @sw-package fundamentals@framework
 */
import template from './sw-profile-index-general.html.twig';
import './sw-profile-index-general.scss';

const { mapPropertyErrors } = Contena.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl', 'ssoSettingsService'],

    emits: [
        'new-password-change',
        'new-password-confirm-change',
        'admin_user-theme-change',
        'admin_user-module-icon-colors-change',
        'media-upload',
        'media-remove',
        'media-open',
    ],

    created() {
        this.ssoSettingsService.isSso().then((isSso) => {
            this.showPasswordChangeCard = !isSso.isSso;
        });
    },

    data() {
        return {
            showPasswordChangeCard: true,
        };
    },

    props: {
        admin_user: {
            type: Object,
            required: true,
        },
        languages: {
            type: Array,
            required: true,
        },
        newPassword: {
            type: String,
            required: false,
            default: null,
        },
        newPasswordConfirm: {
            type: String,
            required: false,
            default: null,
        },
        avatarMediaItem: {
            type: Object,
            required: false,
            default: null,
        },
        isUserLoading: {
            type: Boolean,
            required: true,
        },
        languageId: {
            type: String,
            required: false,
            default: null,
        },
        isDisabled: {
            type: Boolean,
            required: true,
        },
        admin_userRepository: {
            type: Object,
            required: true,
        },
        timezoneOptions: {
            type: Array,
            required: true,
        },
        admin_userTheme: {
            type: String,
            required: false,
            default: 'system',
        },
        admin_userModuleIconColors: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        ...mapPropertyErrors('admin_user', ['password']),

        computedNewPassword: {
            get() {
                return this.newPassword;
            },
            set(newPassword) {
                this.$emit('new-password-change', newPassword);
            },
        },

        computedNewPasswordConfirm: {
            get() {
                return this.newPasswordConfirm;
            },
            set(newPasswordConfirm) {
                this.$emit('new-password-confirm-change', newPasswordConfirm);
            },
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

        computedUserTheme: {
            get() {
                return this.admin_userTheme;
            },
            set(admin_userTheme) {
                this.$emit('admin_user-theme-change', admin_userTheme);
            },
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

        computedUserModuleIconColors: {
            get() {
                return this.admin_userModuleIconColors ? 'module' : 'neutral';
            },
            set(value) {
                this.$emit('admin_user-module-icon-colors-change', value === 'module');
            },
        },
    },

    methods: {
        onUploadMedia(media) {
            this.$emit('media-upload', { targetId: media.targetId });
        },

        onDropMedia(media) {
            this.$emit('media-upload', { targetId: media.id });
        },

        onRemoveMedia() {
            this.$emit('media-remove');
        },

        onOpenMedia() {
            this.$emit('media-open');
        },
    },
};
