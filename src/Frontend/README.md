Frontend Component
==================

The Frontend component is the built-in HTML frontend for Contena Core, written in PHP, Twig, JavaScript, and SCSS.

Getting started
---------------

Run the JavaScript installation and build commands from the Contena project root through Composer:

- `composer build:js:frontend` builds the Frontend application, Twig components, assets, and Channel themes
- `composer frontend:dev-server` starts the Vite development server for Twig components and theme styles
- `composer init:js` installs the Administration and Frontend dependencies
- `composer eslint:frontend` checks the Frontend JavaScript and TypeScript files
- `composer frontend:unit` runs the Frontend Jest suite
- `composer frontend:components:unit` runs the Twig component Vitest suite
- `composer stylelint:frontend` checks the Frontend SCSS files

For example:

```bash
composer build:js:frontend
```

Use `composer frontend:dev-server` while developing Twig components so source modules and theme styles are served directly by Vite.

Resources
---------

- [Frontend build pipeline](Resources/app/frontend/build/README.md)
- [ContentSystem](ContentSystem/README.md)
