Storefront Component
====================

The Storefront component is a frontend for Contena\Core written in PHP. 

This repository is considered **read-only**. Please send pull requests
to our [main Contena\Core repository](https://github.com/contena-cms/contena).


Getting started
---------

To compile the assets (scss/javascript) you have to run the webpack compiler.
This is easily done by executing the following commands in the contena root folder via `composer`.
You can also run the unit tests and code-style fixers via `composer` scripts.

- `composer build:js:storefront`      Builds the project for production and re-compiles the theme
- `composer watch:storefront`         Runs the webpack development server and starts a proxy server with live reload
- `composer init:js`                  Installs the node.js dependencies
- `composer eslint:storefront`        Code-style checks for all Storefront JS/TS files
- `composer ludtwig:storefront`       Code-style checks for all Storefront twig files using ludtwig
- `composer storefront:unit`          Launches the jest unit test-suite for the Storefront
- `composer storefront:unit:watch`    Launches the interactive jest unit test-suite watcher for the Storefront
- `composer stylelint:storefront`     Code-style checks for all Storefront SCSS files using stylelint

For example:
```
$ composer build:js:storefront
```

It's recommended to use the `composer watch:storefront` command when developing, so the files will be compiled as soon as they change.

Resources
---------

  * [Documentation](https://developer.contena.cn)
  * [Contributing](https://developer.contena.cn/docs/resources/guidelines/code/contribution.html)
  * [Report issues](https://github.com/contena-cms/contena/issues) and
    [send Pull Requests](https://github.com/contena-cms/contena/pulls)
    in the [main Contena\Core repository](https://github.com/contena-cms/contena)
