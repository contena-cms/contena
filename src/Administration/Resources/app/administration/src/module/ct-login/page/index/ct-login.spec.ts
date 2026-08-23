import { readFileSync } from 'node:fs';
import { parse } from '@vue/compiler-sfc';

describe('module/ct-login/page/index', () => {
    const source = readFileSync(__dirname + '/ct-login.vue', 'utf8');
    const styles = readFileSync(__dirname + '/ct-login.scss', 'utf8');
    const { descriptor, errors } = parse(source);
    const template = descriptor.template?.content ?? '';
    const script = descriptor.scriptSetup?.content ?? '';

    it('is a valid Vue Single File Component', () => {
        expect(errors).toHaveLength(0);
        expect(descriptor.scriptSetup?.lang).toBe('ts');
    });

    it('renders the centered Contena mark and nested route outlet', () => {
        expect(template).toContain('class="ct-login-index__logo-image"');
        expect(template).toContain("assetFilter('/administration/administration/static/img/contena-logo-v4.svg')");
        expect(template).toContain('alt="Contena"');
        expect(template).toContain('class="ct-login-index__brand"');
        expect(template).toContain('<ct-block name="sw_login_brand_name" :data="$dataScope">');
        expect(template).toContain('<ct-block name="sw_login_footer" :data="$dataScope">');
        expect(template).toContain('© {{ copyrightYear }} Contena');
        expect(template).not.toContain("$t('ct-login.index.copyright'");
        expect(template).toContain('class="ct-login-index__card"');
        expect(template).toContain('<ct-block name="sw_login_back_link" :data="$dataScope">');
        expect(template).toContain('<router-view v-slot="{ Component }">');
    });

    it('keeps the recovery route back-link behavior', () => {
        expect(template).toContain('v-if="$route.meta.backToLogin"');
        expect(template).toContain(':to="{ name: \'ct.login.index.credentials\' }"');
        expect(template).toContain('@mousedown.prevent');
        expect(template).not.toContain('@click.prevent');
        expect(script).toContain("router.push({ name: 'ct.login.index.credentials' })");
    });

    it('preserves the native extension block contract', () => {
        expect(template).toContain('<ct-block name="sw_login" :data="$dataScope">');
        expect(template).toContain('<ct-block name="sw_login_badge_image" :data="$dataScope">');
        expect(template).toContain('<ct-block name="sw_login_view" :data="$dataScope">');
    });

    it('provides a dedicated dark theme surface', () => {
        expect(styles).toContain('html[data-theme="dark"] .ct-login-index');
        expect(styles).toContain('background: linear-gradient(180deg, #081426');
        expect(styles).toContain('.ct-login-index__card');
    });
});
