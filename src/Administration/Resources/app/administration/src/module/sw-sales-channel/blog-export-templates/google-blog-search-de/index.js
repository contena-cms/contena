/**
 * @sw-package discovery
 */

import header from './header.xml.twig?raw';
import body from './body.xml.twig?raw';
import footer from './footer.xml.twig?raw';

Contena.Service('exportTemplateService').registerProductExportTemplate({
    name: 'google-blog-search-de',
    translationKey: 'sw-sales-channel.detail.blogComparison.templates.template-label.google-blog-search-de',
    headerTemplate: header.trim(),
    bodyTemplate: body,
    footerTemplate: footer.trim(),
    fileName: 'google.xml',
    encoding: 'UTF-8',
    fileFormat: 'xml',
    generateByCronjob: false,
    interval: 86400,
});
