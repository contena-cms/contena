import { readFileSync } from 'node:fs';
import { parse } from '@vue/compiler-sfc';

describe('module/ct-login/view/ct-login-recovery', () => {
    const source = readFileSync(__dirname + '/ct-login-recovery.vue', 'utf8');
    const { descriptor, errors } = parse(source);
    const template = descriptor.template?.content ?? '';

    it('uses the large button size for sending the reset link', () => {
        expect(errors).toHaveLength(0);
        expect(template).toContain('class="ct-login-recovery__submit"');
        expect(template).toContain('size="large"');
    });

    it('keeps navigation away from the recovery view free of native validation', () => {
        expect(template).toContain('<form class="ct-login-recovery" novalidate');
    });
});
