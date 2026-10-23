import { CONTENT_STREAM_CONDITIONS } from './sw-settings-rule.constant';

/**
 * @sw-package fundamentals@after-sales
 */

describe('sw-settings-rule.constant', () => {
    describe('CONTENT_STREAM_CONDITIONS', () => {
        it('should be an array', () => {
            expect(Array.isArray(CONTENT_STREAM_CONDITIONS)).toBe(true);
        });

        it('should not be empty', () => {
            expect(CONTENT_STREAM_CONDITIONS.length).toBeGreaterThan(0);
        });

        it('should only contain strings', () => {
            expect(CONTENT_STREAM_CONDITIONS.every((condition) => typeof condition === 'string')).toBe(true);
        });
    });
});
