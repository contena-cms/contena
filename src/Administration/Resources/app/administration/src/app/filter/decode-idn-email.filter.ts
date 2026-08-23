import { toUnicode } from 'punycode/';

/**
 * @private
 */
Contena.Filter.register('decode-idn-email', (value: string) => {
    return toUnicode(value);
});
