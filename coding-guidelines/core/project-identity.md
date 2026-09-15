# Project identity and metadata

Contena is an independent project. Source code, tracked paths, configuration, documentation, fixtures, environment variables, namespaces, and user-facing assets must use `contena`, `Contena`, or `CONTENA`; legacy project branding is forbidden.

Two naming layers, never mixed:

- **Display brand: `Contena CMS`.** Use it in user-facing copy only — interface snippets (en and zh), mail templates, installer text, the frontend footer, and default data from the baseline migrations. Do not append version numbers to the display brand (no "Contena CMS 6").
- **Technical identifiers: plain `contena`/`Contena`.** Namespaces (`Contena\...`), class names, service ids (`contena.*`), config keys, composer packages (`contena/*`), npm packages (`@contena/*`), file and directory names, and asset paths stay unchanged. Renaming any of these is a BC break.