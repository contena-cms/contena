/**
 * @sw-package fundamentals@framework
 */
import template from './sw-admin-menu.html.twig';

const { Component } = Contena;

Component.override('sw-admin-menu', {
    template,
    inject: ['acl'],
});
