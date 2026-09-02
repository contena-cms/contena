/**
 * Behavioural guard for native setup support in every extension config.
 *
 * The generated configuration is exercised through ESLint itself so parser
 * resolution and the native setup guard rules are tested together.
 */

import { execFileSync } from 'child_process';
import path from 'path';
import { pathToFileURL } from 'url';

const factoryUrl = pathToFileURL(path.resolve(__dirname, '../../extension-tooling/eslint.mjs')).href;

const withMarker =
    '<script setup>\nconst count = 1;\nctDefinePublic({ count });\n</script>\n<template><div>{{ count }}</div></template>\n';
const withoutMarker = '<script setup>\nconst count = 1;\n</script>\n<template><div>{{ count }}</div></template>\n';

const probeScript = `
import { ESLint } from 'eslint';

const { contenaAdminExtension } = await import(${JSON.stringify(factoryUrl)});
const config = contenaAdminExtension({ tsconfigRootDir: process.cwd() }).filter(
    (block) =>
        block.name === 'contena/admin-extension/native-setup' ||
        (block.name &&
            block.name.startsWith('contena/admin-extension/vue-') &&
            block.name !== 'contena/admin-extension/vue-typescript'),
);
const eslint = new ESLint({ overrideConfigFile: true, overrideConfig: config });

const cases = [
    ['good', ${JSON.stringify(withMarker)}, 'ct-my-widget.vue'],
    ['noMarker', ${JSON.stringify(withoutMarker)}, 'ct-widget.vue'],
    ['badName', ${JSON.stringify(withMarker)}, 'Bad_Name.vue'],
];
const result = {};
for (const [key, code, file] of cases) {
    const [report] = await eslint.lintText(code, { filePath: file });
    result[key] = report.messages.map((message) => message.ruleId ?? (message.fatal ? 'FATAL' : null));
}

process.stdout.write(JSON.stringify(result));
`;

describe('extension-tooling native setup lint behaviour', () => {
    let result: Record<string, Array<string | null>>;

    beforeAll(() => {
        const output = execFileSync(
            process.execPath,
            [
                '--input-type=module',
                '-e',
                probeScript,
            ],
            {
                cwd: path.resolve(__dirname, '../..'),
                encoding: 'utf8',
            },
        );

        result = JSON.parse(output) as Record<string, Array<string | null>>;
    });

    it('lints a valid native setup SFC cleanly', () => {
        expect(result.good).toEqual([]);
    });

    it('reports a missing setup marker', () => {
        expect(result.noMarker).toContain('ct-core-rules/valid-contena-setup');
    });

    it('reports a non-kebab component filename', () => {
        expect(result.badName).toContain('ct-core-rules/native-setup-filename');
    });
});
