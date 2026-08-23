import CookieStorageHelper from 'src/helper/storage/cookie-storage.helper';
import Storage from 'src/helper/storage/storage.helper';
import { COOKIE_CONFIGURATION_UPDATE } from 'src/plugin/cookie/cookie-configuration.plugin';
import LoginEvent from 'src/plugin/google-analytics/events/login.event';
import SearchAjaxEvent from 'src/plugin/google-analytics/events/search-ajax.event';
import SignUpEvent from 'src/plugin/google-analytics/events/sign-up.event';
import ViewSearchResultsEvent from 'src/plugin/google-analytics/events/view-search-results';
import Plugin from 'src/plugin-system/plugin.class';

/**
 * @package buyers-experience
 */
export default class GoogleAnalyticsPlugin extends Plugin
{
    init() {
        this.cookieEnabledName = 'google-analytics-enabled';
        this.cookieAdsEnabledName = 'google-ads-enabled';
        this.storage = Storage;

        this.handleTrackingLocation();
        this.handleCookieChangeEvent();

        if (window.useDefaultCookieConsent
            && !CookieStorageHelper.getItem(this.cookieEnabledName)
            && !CookieStorageHelper.getItem(this.cookieAdsEnabledName)) {
            return;
        }

        this.startGoogleAnalytics();
    }

    startGoogleAnalytics() {
        const gtmScript = document.createElement('script');
        gtmScript.src = window.gtagURL;
        document.head.append(gtmScript);

        gtag('js', new Date());
        gtag('config', window.gtagTrackingId, window.gtagConfig);

        this.activeRoute = window.activeRoute;
        this.events = [];

        this.registerDefaultEvents();
        this.handleEvents();
    }

    handleTrackingLocation() {
        this.trackingUrl = new URL(window.location.href);

        const gclid = this.trackingUrl.searchParams.get('gclid');
        if (gclid) {
            this.storage.setItem(
                this._getGclidStorageKey(),
                gclid,
            );
        } else if (this.storage.getItem(this._getGclidStorageKey())) {
            this.trackingUrl.searchParams.set(
                'gclid',
                this.storage.getItem(this._getGclidStorageKey()),
            );
        }

        if (this.trackingUrl.searchParams.get('gclid')) {
            window.gtagConfig.page_location = this.trackingUrl.toString();
        }
    }

    handleEvents() {
        this.events.forEach(event => {
            if (!event.supports(this.activeRoute)) {
                return;
            }

            event.execute();
        });
    }

    registerDefaultEvents() {
        this.registerEvent(LoginEvent);
        this.registerEvent(SearchAjaxEvent);
        this.registerEvent(SignUpEvent);
        this.registerEvent(ViewSearchResultsEvent);
    }

    /**
     * @param { AnalyticsEvent } event
     */
    registerEvent(event) {
        this.events.push(new event());
    }

    handleCookieChangeEvent() {
        document.$emitter.subscribe(COOKIE_CONFIGURATION_UPDATE, this.handleCookies.bind(this));
    }

    handleCookies(cookieUpdateEvent) {
        const updatedCookies = cookieUpdateEvent.detail;

        this._updateConsent(updatedCookies);

        const analyticsEnabled = updatedCookies[this.cookieEnabledName];
        const adsEnabled = updatedCookies[this.cookieAdsEnabledName];

        // Strict undefined check to distinguishe if the cookie has been updated in the event
        if (analyticsEnabled === undefined && adsEnabled === undefined) {
            return;
        }

        if (analyticsEnabled || adsEnabled) {
            this.startGoogleAnalytics();
            return;
        }

        this.removeCookies();
        this.disableEvents();
    }

    removeCookies() {
        const allCookies = document.cookie.split(';');
        const gaCookieRegex = /^(_contena_ga|_gat_gtag)/;

        allCookies.forEach(cookie => {
            const cookieName = cookie.split('=')[0].trim();
            if (!cookieName.match(gaCookieRegex)) {
                return;
            }

            CookieStorageHelper.removeItem(cookieName);
        });
    }

    disableEvents() {
        this.events.forEach(event => {
            event.disable();
        });
    }

    /**
     * @param {Object} updatedCookies
     * @private
     */
    _updateConsent(updatedCookies) {
        if (Object.keys(updatedCookies).length === 0) {
            return;
        }

        const consentUpdateConfig = {};

        if (Object.hasOwn(updatedCookies, this.cookieEnabledName)) {
            consentUpdateConfig.analytics_storage = updatedCookies[this.cookieEnabledName] ? 'granted' : 'denied';
        }

        if (Object.hasOwn(updatedCookies, this.cookieAdsEnabledName)) {
            consentUpdateConfig.ad_storage = updatedCookies[this.cookieAdsEnabledName] ? 'granted' : 'denied';
            consentUpdateConfig.ad_user_data = updatedCookies[this.cookieAdsEnabledName] ? 'granted' : 'denied';
            consentUpdateConfig.ad_personalization = updatedCookies[this.cookieAdsEnabledName] ? 'granted' : 'denied';
        }

        if (Object.keys(consentUpdateConfig).length === 0) {
            return;
        }

        gtag('consent', 'update', consentUpdateConfig);
    }

    /**
     * @private
     */
    _getGclidStorageKey() {
        return `google-analytics-${window.channelId || ''}-gclid`;
    }
}
