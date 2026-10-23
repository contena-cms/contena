Frontend Component
====================

The Frontend component is a frontend for Contena\Core written in PHP. 

This repository is considered **read-only**. Please send pull requests
to our [main Contena\Core repository](https://github.com/contena-cms/contena).


Getting started
---------

To compile the assets (scss/javascript) you have to run the webpack compiler.
This is easily done by executing the following commands in the contena root folder via `composer`.
You can also run the unit tests and code-style fixers via `composer` scripts.

- `composer build:js:frontend`      Builds the project for production and re-compiles the theme
- `composer watch:frontend`         Runs the webpack development server and starts a proxy server with live reload
- `composer init:js`                  Installs the node.js dependencies
- `composer eslint:frontend`        Code-style checks for all Frontend JS/TS files
- `composer ludtwig:frontend`       Code-style checks for all Frontend twig files using ludtwig
- `composer frontend:unit`          Launches the jest unit test-suite for the Frontend
- `composer frontend:unit:watch`    Launches the interactive jest unit test-suite watcher for the Frontend
- `composer stylelint:frontend`     Code-style checks for all Frontend SCSS files using stylelint

For example:
```
$ composer build:js:frontend
```

It's recommended to use the `composer watch:frontend` command when developing, so the files will be compiled as soon as they change.

Resources
---------

  * [Documentation](https://developer.contena.cn)
  * [Contributing](https://developer.contena.cn/docs/resources/guidelines/code/contribution.html)
  * [Report issues](https://github.com/contena-cms/contena/issues) and
    [send Pull Requests](https://github.com/contena-cms/contena/pulls)
    in the [main Contena\Core repository](https://github.com/contena-cms/contena)
