import { captures } from '../public-api-source-files';

const OLD_BLOCK_START_REGEX = /\{%\s*block\s+([^%\s\}]+)\s*%\}/g;
// Keep block-field, block-parent and block-override components out of the public block list.
const NEW_BLOCK_START_REGEX = /<ct-block(?![-\w])[^>]*\s(?:name|extends)="([^"]+)"/g;

export function extractBlocks(code: string): string[] {
    return captures(code, OLD_BLOCK_START_REGEX, NEW_BLOCK_START_REGEX);
}
