import 'src/helper/polyfill-loader.helper';

/*
import base requirements
 */
import * as bootstrap from 'bootstrap';

/*
import helpers
 */
import Feature from 'src/helper/feature.helper';
import PluginManager from 'src/plugin-system/plugin.manager';
import ViewportDetection from 'src/helper/viewport-detection.helper';
import NativeEventEmitter from 'src/helper/emitter.helper';
import FocusHandler from 'src/helper/focus-handler.helper';
import FormValidation from 'src/helper/form-validation.helper';
import CookieStorage from 'src/helper/storage/cookie-storage.helper';

/*
import utils
 */
import TimezoneUtil from 'src/utility/timezone/timezone.util';
import BootstrapUtil from 'src/utility/bootstrap/bootstrap.util';

/*
import synchronous plugins
 */
import SetBrowserClassPlugin from 'src/plugin/set-browser-class/set-browser-class.plugin';
import SpeculationRulesPlugin from 'src/plugin/speculation-rules/speculation-rules.plugin';
import AlertAriaPlugin from 'src/plugin/alert-aria/alert-aria.plugin';

window.Feature = Feature;
window.eventEmitter = new NativeEventEmitter();
window.focusHandler = new FocusHandler();
window.formValidation = new FormValidation();
window.bootstrap = bootstrap;

new ViewportDetection();

/*
register plugins
*/
if (window.useDefaultCookieConsent) {
    PluginManager.register('CookiePermission', () => import('src/plugin/cookie/cookie-permission.plugin'), '[data-cookie-permission]');
    PluginManager.register('CookieConfiguration', () => import('src/plugin/cookie/cookie-configuration.plugin'), '[data-cookie-permission]');
}

PluginManager.register('FormRegionSelect', () => import('src/plugin/forms/form-region-select.plugin'), '[data-region-select]');
PluginManager.register('AddressSearch', () => import('src/plugin/address-search/address-search.plugin'), '[data-address-search]');
PluginManager.register('DateFormat', () => import('src/plugin/date-format/date-format.plugin'), '[data-date-format]');
PluginManager.register('ScrollUp', () => import('src/plugin/scroll-up/scroll-up.plugin'), '[data-scroll-up]');
PluginManager.register('SearchWidget', () => import('src/plugin/header/search-widget.plugin'), '[data-search-widget]');
PluginManager.register('CollapseFooterColumns', () => import('src/plugin/collapse/collapse-footer-columns.plugin'), '[data-collapse-footer-columns]');
PluginManager.register('Navbar', () => import('src/plugin/navbar/navbar.plugin'), '[data-navbar]');
PluginManager.register('OffCanvasMenu', () => import('src/plugin/main-menu/offcanvas-menu.plugin'), '[data-off-canvas-menu]');
PluginManager.register('FormHandler', () => import('src/plugin/forms/form-handler.plugin'), '[data-form-handler]');
PluginManager.register('FormFieldToggle', () => import('src/plugin/forms/form-field-toggle.plugin'), '[data-form-field-toggle]');
PluginManager.register('FormAutoSubmit', () => import('src/plugin/forms/form-auto-submit.plugin'), '[data-form-auto-submit]');
PluginManager.register('FormAjaxSubmit', () => import('src/plugin/forms/form-ajax-submit.plugin'), '[data-form-ajax-submit]');
PluginManager.register('FormAddHistory', () => import('src/plugin/forms/form-add-history.plugin'), '[data-form-add-history]');
PluginManager.register('FormPreserver', () => import('src/plugin/forms/form-preserver.plugin'), '[data-form-preserver]');
PluginManager.register('FormAjaxPagination', () => import('src/plugin/forms/form-ajax-pagination.plugin'), '[data-form-ajax-pagination]');
PluginManager.register('FormAddDynamicRedirect', () => import('src/plugin/forms/form-add-dynamic-redirect-plugin'), '[data-form-add-dynamic-redirect]');
PluginManager.register('AccountMenu', () => import('src/plugin/header/account-menu.plugin'), '[data-account-menu]');
PluginManager.register('OffCanvasTabs', () => import('src/plugin/offcanvas-tabs/offcanvas-tabs.plugin'), '[data-off-canvas-tabs]');
PluginManager.register('Listing', () => import('src/plugin/listing/listing.plugin'), '[data-listing]');
PluginManager.register('OffCanvasFilter', () => import('src/plugin/offcanvas-filter/offcanvas-filter.plugin'), '[data-off-canvas-filter]');
PluginManager.register('FilterBoolean', () => import('src/plugin/listing/filter-boolean.plugin'), '[data-filter-boolean]');
PluginManager.register('FilterRange', () => import('src/plugin/listing/filter-range.plugin'), '[data-filter-range]');
PluginManager.register('FilterMultiSelect', () => import('src/plugin/listing/filter-multi-select.plugin'), '[data-filter-multi-select]');
PluginManager.register('ListingPagination', () => import('src/plugin/listing/listing-pagination.plugin'), '[data-listing-pagination]');
PluginManager.register('ListingSorting', () => import('src/plugin/listing/listing-sorting.plugin'), '[data-listing-sorting]');
PluginManager.register('BaseSlider', () => import('src/plugin/slider/base-slider.plugin'), '[data-base-slider]');
PluginManager.register('GallerySlider', () => import('src/plugin/slider/gallery-slider.plugin'), '[data-gallery-slider]');
PluginManager.register('ZoomModal', () => import('src/plugin/zoom-modal/zoom-modal.plugin'), '[data-zoom-modal]');
PluginManager.register('Magnifier', () => import('src/plugin/magnifier/magnifier.plugin'), '[data-magnifier]');
PluginManager.register('RemoteClick', () => import('src/plugin/remote-click/remote-click.plugin'), '[data-remote-click]');
PluginManager.register('DatePicker', () => import('src/plugin/date-picker/date-picker.plugin'), '[data-date-picker]');
PluginManager.register('ClearInput', () => import('src/plugin/clear-input-button/clear-input.plugin'), '[data-clear-input]');
PluginManager.register('AjaxModal', () => import('src/plugin/ajax-modal/ajax-modal.plugin'), '[data-ajax-modal][data-url]');
PluginManager.register('BasicCaptcha', () => import('src/plugin/captcha/basic-captcha.plugin'), '[data-basic-captcha]');
PluginManager.register('SpatialArViewer', () => import('src/plugin/spatial/spatial-ar-viewer-plugin'), '[data-spatial-ar-viewer]');
PluginManager.register('PageQrcodeGenerator', () => import('src/plugin/qrcode/page-qrcode-generator'), '[data-page-qrcode-generator]');
PluginManager.register('SpeculationRules', SpeculationRulesPlugin, '[data-speculation-rules]');
PluginManager.register('SetBrowserClass', SetBrowserClassPlugin, 'html');
PluginManager.register('AlertAria', AlertAriaPlugin, '[data-alert-aria]');

if (window.gtagActive) {
    PluginManager.register('GoogleAnalytics', () => import('src/plugin/google-analytics/google-analytics.plugin'));
}

if ((window.googleReCaptchaV2Active || window.googleReCaptchaV3Active) && typeof window.grecaptcha === 'undefined') {
    window.grecaptcha = {
        ready: (cb) => {
            const c = '___grecaptcha_cfg';
            window[c] = window[c] || {};
            window[c].fns = window[c].fns || [];
            window[c].fns.push(cb);
        },
    };
}

function registerGoogleReCaptchaPlugins() {
    const cookiesAccepted = CookieStorage.getItem('cookie-preference') === '1';

    if (cookiesAccepted || !window.useDefaultCookieConsent) {
        if (window.googleReCaptchaV2Active) {
            PluginManager.register(
                'GoogleReCaptchaV2',
                () => import('src/plugin/captcha/google-re-captcha/google-re-captcha-v2.plugin'),
                '[data-google-re-captcha-v2]',
            );
        }

        if (window.googleReCaptchaV3Active) {
            PluginManager.register(
                'GoogleReCaptchaV3',
                () => import('src/plugin/captcha/google-re-captcha/google-re-captcha-v3.plugin'),
                '[data-google-re-captcha-v3]',
            );
        }
    }
}

window.registerGoogleReCaptchaPlugins = registerGoogleReCaptchaPlugins;
registerGoogleReCaptchaPlugins();

/*
run plugins
*/
document.addEventListener('DOMContentLoaded', () => {
    PluginManager.initializePlugins();
}, false);

new TimezoneUtil();
BootstrapUtil.initBootstrapPlugins();
