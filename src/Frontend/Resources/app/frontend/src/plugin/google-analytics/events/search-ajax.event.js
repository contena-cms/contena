import EventAwareAnalyticsEvent from 'src/plugin/google-analytics/event-aware-analytics-event';

export default class SearchAjaxEvent extends EventAwareAnalyticsEvent
{
    /* eslint-disable no-unused-vars */
    /**
     * @param {string} activeRoute
     * @returns {boolean}
     */
    supports(activeRoute) {
        return true;
    }
    /* eslint-enable no-unused-vars */

    getPluginName() {
        return 'SearchWidget';
    }

    getEvents() {
        return {
            'handleInputEvent':  this._onSearch.bind(this),
        };
    }

    _onSearch(event) {
        if (!this.active) {
            return;
        }

        gtag('event', 'search', {
            'search_term': event.detail.value,
        });
    }
}
