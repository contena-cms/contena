/**
 * @sw-package discovery
 *
 * @private
 */

import template from './sw-sales-channel-detail-agentic-files.html.twig';
import './sw-sales-channel-detail-agentic-files.scss';

const { Mixin, Context } = Contena;
const { EntityCollection } = Contena.Data;

const FILE_FAMILY_AGENTIC = 'agentic';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl', 'channelFileApiService', 'repositoryFactory'],

    mixins: [Mixin.getByName('notification')],

    props: {
        channel: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            files: [],
            isLoading: false,
            page: 1,
            limit: 10,
            paginationSteps: [10, 25, 50],
        };
    },

    computed: {
        channelFileRepository() {
            return this.repositoryFactory.create('channel_file');
        },

        columns() {
            return [
                {
                    property: 'fileName',
                    label: this.$t('sw-sales-channel.detail.agenticFiles.columnFileName'),
                    primary: true,
                    allowResize: true,
                    width: '200px',
                },
                {
                    property: 'enabled',
                    label: this.$t('sw-sales-channel.detail.agenticFiles.columnEnabled'),
                    allowResize: true,
                    width: '230px',
                },
                {
                    property: 'description',
                    label: this.$t('sw-sales-channel.detail.agenticFiles.columnDescription'),
                    allowResize: true,
                },
            ];
        },

        paginatedFiles() {
            const start = (this.page - 1) * this.limit;
            const end = start + this.limit;

            return this.files.slice(start, end);
        },

        total() {
            return this.files.length;
        },
    },

    watch: {
        channel: {
            immediate: true,
            handler(newSalesChannel, previousSalesChannel) {
                if (!newSalesChannel?.id || newSalesChannel.id === previousSalesChannel?.id) {
                    return;
                }

                void this.loadFiles();
            },
        },
    },

    methods: {
        async loadFiles() {
            if (!this.channel?.id) {
                return;
            }

            this.isLoading = true;

            try {
                const response = await this.channelFileApiService.list(FILE_FAMILY_AGENTIC, this.channel.id);
                this.files = (response?.data ?? []).map((file) => {
                    return {
                        ...file,
                        configuration: this.findSalesChannelFileConfiguration(file) ?? file.configuration,
                    };
                });
                this.page = 1;
            } catch {
                this.files = [];
                this.page = 1;
                this.createNotificationError({
                    message: this.$t('sw-sales-channel.detail.agenticFiles.messageLoadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        onToggleEnabled(file) {
            if (!this.channel?.id) {
                return;
            }

            const configuration = this.ensureSalesChannelFileConfiguration(file);
            configuration.enabled = !this.isEnabled(file);
            configuration.templateOverrides = { ...(configuration.templateOverrides ?? {}) };
            file.configuration = configuration;
        },

        isEnabled(file) {
            return file.configuration?.enabled === true;
        },

        ensureSalesChannelFileConfiguration(file) {
            const existingConfiguration = this.findSalesChannelFileConfiguration(file);
            if (existingConfiguration) {
                return existingConfiguration;
            }

            const configuration = this.channelFileRepository.create(Context.api);
            if (file.configuration?.id) {
                configuration.id = file.configuration.id;
            }
            configuration.channelId = this.channel.id;
            configuration.fileFamily = file.fileFamily;
            configuration.fileName = file.fileName;
            configuration.enabled = file.configuration?.enabled ?? this.isEnabled(file);
            configuration.templateOverrides = { ...(file.configuration?.templateOverrides ?? {}) };

            this.ensureSalesChannelFileCollection().add(configuration);

            return configuration;
        },

        findSalesChannelFileConfiguration(file) {
            const channelFiles = this.channel?.channelFiles;
            if (!channelFiles) {
                return null;
            }

            return (
                channelFiles.find((configuration) => {
                    return (
                        configuration.fileFamily === file.fileFamily &&
                        configuration.fileName.toLowerCase() === file.fileName.toLowerCase()
                    );
                }) ?? null
            );
        },

        ensureSalesChannelFileCollection() {
            if (!this.channel.channelFiles) {
                this.channel.channelFiles = new EntityCollection(
                    `/sales-channel/${this.channel.id}/channelFiles`,
                    'channel_file',
                    Context.api,
                    null,
                );
            }

            return this.channel.channelFiles;
        },

        hasTemplateOverrides(file) {
            return Object.keys(file.configuration?.templateOverrides ?? {}).length > 0;
        },

        getDisplayFileName(file) {
            return file.fileName.split('/').pop();
        },

        getDetailRoute(file) {
            return {
                name: 'sw.sales.channel.detail.agenticFile',
                params: {
                    id: this.$route.params.id,
                    fileName: file.fileName,
                },
            };
        },

        getPublicPath(file) {
            return `/${file.fileName}`;
        },

        getToggleLabel(file) {
            return this.isEnabled(file)
                ? this.$t('sw-sales-channel.detail.agenticFiles.actionDisable')
                : this.$t('sw-sales-channel.detail.agenticFiles.actionEnable');
        },

        getDescription(file) {
            const snippetKey = this.getDescriptionSnippetKey(file);
            const description = this.$te(snippetKey) ? this.$t(snippetKey) : '';

            // The list shows only the first sentence; the full description belongs on the detail page.
            return description.match(/^[^.!?]+[.!?]/)?.[0] ?? description;
        },

        getDescriptionSnippetKey(file) {
            const fileFamily = this.formatSnippetPathSegment(file.fileFamily);
            const fileName = this.formatSnippetPathSegment(file.fileName);

            return `sw-sales-channel.detail.agenticFiles.descriptions${fileFamily}${fileName}`;
        },

        formatSnippetPathSegment(segment) {
            // Slugify so dots and slashes in file names cannot break snippet path resolution.
            const slug = Contena.Utils.string.kebabCase(segment);

            return `[${JSON.stringify(slug)}]`;
        },

        getEnabledVariant(file) {
            return this.isEnabled(file) ? 'success' : 'neutral';
        },

        getEnabledLabel(file) {
            return this.isEnabled(file)
                ? this.$t('sw-sales-channel.detail.agenticFiles.enabledState.enabled')
                : this.$t('sw-sales-channel.detail.agenticFiles.enabledState.disabled');
        },

        onChangePage(data) {
            this.page = data.page;
            this.limit = data.limit;
        },
    },
};
