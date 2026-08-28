/**
 * @package admin
 * @private
 */

import { captures } from '../public-api-source-files';

const POSITION_IDENTIFIER_REGEX = /position-identifier="([^"]+)"/g;

export function extractPositionIdentifiers(code: string): string[] {
    return captures(code, POSITION_IDENTIFIER_REGEX).filter((identifier) => identifier !== '' && identifier !== 'null');
}
