/**
 * @sw-package discovery
 */

import template from './sw-admin-menu-extension.html.twig';

const { Component } = Contena;

Component.override('sw-admin-menu', {
    template,

    inject: ['acl'],

    computed: {
        canViewSalesChannels() {
            return this.acl.can('channel.viewer');
        },
    },
});
