/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-detail-hreflang.html.twig';

const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        channel: {
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        domainCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('channelId', this.channel.id));

            return criteria;
        },
    },
};
