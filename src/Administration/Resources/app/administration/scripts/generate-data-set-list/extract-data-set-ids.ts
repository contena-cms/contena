/**
 * @package admin
 * @private
 */

import { captures } from '../public-api-source-files';

const DATA_SET_ID_REGEX = /\.publishData\(\{[^}]*?\bid\s*:\s*['"]([^'"]+)['"]/gm;

export function extractDataSetIds(code: string): string[] {
    return captures(code, DATA_SET_ID_REGEX);
}
