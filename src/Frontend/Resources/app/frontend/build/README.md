# Frontend Build (Vite) Overview

This directory contains build-time tooling for the Frontend.

The current component pipeline is Vite-based and covers:

- building the shared `contena` runtime module
- building Twig component JavaScript/TypeScript and styles (`.scss` or `.css`)
- powering the Frontend Vite development server

## High-level pipeline

From the project root, the full Frontend build is:

```bash
composer build:js:frontend
```

At a high level, this does:

1. `bundle:dump` / `feature:dump` prepare metadata used by JavaScript tooling
2. run the main webpack Frontend build plus the Vite component/runtime builds (`npm run production`)
3. install bundle assets (`bin/console assets:install`)
4. compile Channel themes

For component-only rebuilds:

```bash
composer npm:frontend run build:components
php bin/console assets:install
php bin/console theme:compile --sync
```

## Development server flow

Start from the project root:

```bash
composer frontend:dev-server
```

The Vite development server:

- serves component modules and styles from source
- writes `var/cache/frontend_components.dev.json` with the development import map and CSS/JavaScript URLs
- exposes `/theme-scss/all.css` for theme styles in development
- serves component style files through `/__ct-comp-css/...`

Environment overrides:

- `FRONTEND_VITE_PORT` changes the Vite port (default `5175`)
- `FRONTEND_VITE_HOST` changes the bind host (default `localhost`)
- `FRONTEND_VITE_ORIGIN` sets the absolute URL written to `frontend_components.dev.json`, which is useful in Docker, WSL, or remote environments

When the development server stops, Contena falls back to production assets and the production import map.

## File map

### Core Vite configuration entry points

- `../vite.components.config.mts`
  - main Vite configuration used by `composer frontend:dev-server`
  - builds component entries and wires all development/build plugins
- `../vite.contena.config.mts`
  - builds `src/contena.ts` into `Resources/public/frontend/contena/contena.js`

### Component build orchestration

- `vite/build-components.js`
  - orchestrates component builds across all bundles from `var/plugins.json`
  - clears `<bundle>/Resources/public/frontend/components` before processing each bundle
  - uses a custom `<bundle>/Resources/app/frontend/vite.components.config.mts` when present
  - otherwise performs the generic inline Vite build
  - enforces one style source per component (`Foo.scss` xor `Foo.css`)
  - emits component chunks and `.vite/build-meta.json` below `<bundle>/Resources/public/frontend/components/`
  - publishes installed assets below `public/bundles/<bundle>/frontend/components/`

### Shared component configuration

- `vite/component-config-factory.ts`
  - discovers JavaScript, TypeScript, SCSS, and CSS entries
  - centralizes output naming, resolution, plugins, and style-source collision checks
  - supports both the generic configuration and extension-specific configurations
- `vite/vite.components.generic.config.mts`
  - thin environment-based adapter around `createComponentBuildConfig()`
- `vite/component-entries.ts`
  - discovers component entries in the Core Frontend
  - creates JavaScript/style entries and virtual entries for plain CSS

### Vite plugins

- `vite/component-map-plugin.ts`
  - rewrites entry-chunk vendor imports to bare specifiers
  - emits `.vite/build-meta.json` for PHP runtime import-map aggregation
- `vite/plain-css-shim-plugin.ts`
  - routes plain CSS entries through the Vite CSS pipeline
- `vite/dev-import-map-plugin.ts`
  - writes `var/cache/frontend_components.dev.json`
  - serves development component modules and styles
  - watches component styles and triggers full reloads
- `vite/theme-scss-watcher-plugin.ts`
  - reads `var/theme-files.json`
  - compiles theme style entries and serves `/theme-scss/all.css`
  - watches the SCSS graph and `theme-files.json`
- `vite/dev-server-notice-plugin.ts`
  - replaces the default Vite URL output with a Frontend-specific usage hint
- `vite/extension-module-resolver-plugin.ts`
  - resolves bare imports from extension component files
  - bridges `Resources/views/components` to the sibling `Resources/app/frontend/node_modules`
- `vite/scoped-subpath-exports-plugin.ts`
  - resolves scoped package subpath exports such as `@scope/pkg/subpath`

### Supporting scripts

- `link-component-node-modules.js`
  - creates the `Resources/views/components/node_modules` symlink
  - lets IDEs, TypeScript, and Vitest resolve bare imports outside Vite plugin hooks
  - runs from the Frontend `postinstall` script
- `start-hot-reload.js`
  - deprecated webpack hot-reload proxy retained for the existing main application HMR mode

## Extension custom configuration

Extensions can provide:

`<bundle>/Resources/app/frontend/vite.components.config.mts`

Import and call `createComponentBuildConfig()` from `vite/component-config-factory.ts`, then override only the required options such as source maps, extra plugins, or aliases. This keeps extension configurations aligned with Contena defaults.
