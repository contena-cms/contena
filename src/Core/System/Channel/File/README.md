# Channel Files

## Purpose

The `File` namespace provides generic public files scoped to a channel. Agentic files such as `llms.txt`, `AGENTS.md`, and `.well-known/ai-catalog.json` are the first file family, but the backend is intentionally not tied to agentic use cases.

Templates are registered below `Resources/views/files/<file-family>/**/*.twig`. The current default file family is `agentic`, so `Resources/views/files/agentic/llms.txt.twig` is served as `/llms.txt` when enabled for a channel.

## Terminology

- File family: the first path segment below `files`, for example `agentic` or a future `seo` family.
- File name: the normalized public path below the file family, without the `.twig` suffix, for example `llms.txt` or `.well-known/ucp.json`.
- Twig namespace: the owning template namespace, for example `Framework`, a plugin name, or a theme name.

## Key Decisions

- Discovery is template based. Core, plugins, and themes contribute files by shipping Twig templates in the registered template system. There is no provider interface for individual files.
- File names are matched case-insensitively. When templates differ only by case, they form one template chain and the lexicographically first spelling is exposed as the canonical public path.
- The database stores channel state only: one `channel_file` row per channel, file family, and file name. The row controls enablement and stores merchant overrides in `template_overrides`, keyed by Twig namespace. The reserved `user_provided_content` key stores plain merchant notes that are rendered through a generated Twig override for the dedicated block of the same name.
- Shipped template content is never copied into the database. When code templates change, no migration is needed to update stored rows.
- Public serving is a fallback. Normal routes keep precedence because `ChannelFileNotFoundSubscriber` only handles unresolved 404s for main `GET` and `HEAD` requests that already have a channel context.
- Request paths are validated before they are mapped to a template path. Template discovery paths come from code and registered template storage.
- Discovery is cached per file family because it can run during 404 handling. Runtime responses are tagged with the matching `channel_file.id`; discovery caches are cleared when plugin, theme-template, or system update events can change registered templates.
- Merchant overrides are rendered through `ChannelFileTemplateOverrideLoader`, a high-priority Twig loader that is activated only for the duration of one render. The loader does not read the database, so public rendering and Administration previews use the same renderer without coupling Twig to request state.
- Additional render-time data for core templates, such as the public base URL or Channel API MCP endpoint, is passed through the scoped `channelFileContext` Twig variable instead of extending the exposed `channelFile` metadata object.
- Extensions that need more Twig data can subscribe to `ChannelFileRenderParametersExtension::onPost()` and add values only for the files they handle.

## Namespace Layout

- `Discovery`: catalogues available files and resolves the contributing Twig template chain for each file.
- `Loader`: loads channel configuration and coordinates public or preview rendering.
- `Rendering`: activates merchant override templates and renders the resolved Twig stack.
- `Api`: exposes Administration HTTP endpoints and assembles Administration read payloads.
- Root namespace: request path validation, 404 fallback serving, cache invalidation, and exceptions.

## Discovery Flow

```mermaid
classDiagram
    class ChannelFileDiscovery {
        +discover(fileFamily) array
        +get(templatePath) ?ChannelFile
        -catalogueRegisteredFiles(fileFamily) array
        -resolveTemplateChainForFile(templatePath) array
    }

    class TemplatePathIteratorInterface {
        +getTemplatePathsForSubPath(subPath, includeDotFiles) iterable
    }

    class TemplateFinder {
        +find(template, ignoreMissing, source) string
    }

    class CacheInterface {
        +get(key, callback) mixed
    }

    class ChannelFile {
        +fileFamily string
        +fileName string
        +templatePath string
        +contentType string
        +baseTemplateName string
        +templates array
    }

    ChannelFileDiscovery --> CacheInterface : caches discovered catalogue
    ChannelFileDiscovery --> TemplatePathIteratorInterface : catalogues files/*
    ChannelFileDiscovery --> TemplateFinder : resolves Twig chain
    ChannelFileDiscovery --> ChannelFile : creates descriptors
```

Discovery has two responsibilities:

1. Catalogue which public files exist for a file family.
2. Resolve the Twig template chain for each file using the same `TemplateFinder` behavior used during rendering.

## Rendering Flow

```mermaid
classDiagram
    class ChannelFileNotFoundSubscriber {
        +onNotFound(event) void
    }

    class ChannelFileController {
        +list(fileFamily, channelId, context) JsonResponse
        +detail(fileFamily, channelId, request, context) JsonResponse
        +preview(fileFamily, channelId, dataBag) JsonResponse
    }

    class ChannelFileAdministrationReader {
        +list(fileFamily, channelId, context) array
        +detail(fileFamily, fileName, channelId, context) ?array
    }

    class ChannelFileRequestPathResolver {
        +buildTemplatePath(fileFamily, fileName) string
        +validateFileFamily(fileFamily) void
    }

    class ChannelFileLoader {
        +load(templatePath, context) ?ChannelFileRenderResult
        +preview(templatePath, context, templateOverrides) ?ChannelFileRenderResult
    }

    class ChannelFileDiscovery {
        +discover(fileFamily) array
        +get(templatePath) ?ChannelFile
    }

    class ChannelFileConfigurationLoader {
        +load(fileFamily, fileName, channelId, context) ?ChannelFileEntity
        +loadForFileFamily(fileFamily, channelId, context) array
    }

    class ChannelFileRenderer {
        +render(file, context, templateOverrides) string
    }

    class ExtensionDispatcher {
        +publish(name, extension, function) mixed
    }

    class ChannelFileChannelApiMcpSubscriber {
        +addChannelApiMcpContext(extension) void
    }

    class ChannelFileTemplateOverrideLoader {
        +withTemplateOverrides(templateOverrides, callback) mixed
    }

    class TemplateFinder {
        +find(template) string
    }

    class Environment {
        +render(template, parameters) string
    }

    class CacheTagCollector {
        +addTag(tag) void
    }

    ChannelFileNotFoundSubscriber --> ChannelFileRequestPathResolver : validates request path
    ChannelFileNotFoundSubscriber --> ChannelFileLoader : public load
    ChannelFileController --> ChannelFileRequestPathResolver : validates API input
    ChannelFileController --> ChannelFileAdministrationReader : list/detail payloads
    ChannelFileController --> ChannelFileLoader : preview
    ChannelFileAdministrationReader --> ChannelFileDiscovery : lists discovered files
    ChannelFileAdministrationReader --> ChannelFileConfigurationLoader : loads stored rows
    ChannelFileAdministrationReader --> Environment : reads template source
    ChannelFileLoader --> ChannelFileDiscovery : resolves descriptor
    ChannelFileLoader --> ChannelFileConfigurationLoader : loads enabled row
    ChannelFileLoader --> CacheTagCollector : tags public response
    ChannelFileLoader --> ChannelFileRenderer : renders file
    ChannelFileRenderer --> ExtensionDispatcher : publishes render parameters
    ExtensionDispatcher --> ChannelFileChannelApiMcpSubscriber : dispatches render-parameters post event
    ChannelFileRenderer --> ChannelFileTemplateOverrideLoader : activates overrides
    ChannelFileRenderer --> TemplateFinder : resolves base template
    ChannelFileRenderer --> Environment : renders Twig
```

For public requests the loader requires an enabled `channel_file` row. For previews the loader renders a discovered file with the provided override payload, so unsaved Administration changes can be shown without writing to the database first.
