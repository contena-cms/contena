/**
 * @package admin
 * @private
 *
 * Shared source filters for public API list generators and their metadata guards.
 */

const FIXTURE_PATH_REGEX = /(\/(?:_mocks_|__fixtures__)\/|\.spec\/)/;

const isSourceFile =
    (extensionRegex: RegExp) =>
    (filePath: string): boolean =>
        extensionRegex.test(filePath) && !FIXTURE_PATH_REGEX.test(filePath);

export const isTemplateSourceFile = isSourceFile(/\.(html\.twig|vue)$/);

export const isDataSetSourceFile = isSourceFile(/(?<!\.spec|vue2)(?<!\/acl\/index)(?<!\.d)\.(js|ts|vue)$/);

export function captures(code: string, ...patterns: RegExp[]): string[] {
    return patterns.flatMap((pattern) =>
        [...code.matchAll(pattern)].map(
            ([
                ,
                capture,
            ]) => capture,
        ),
    );
}
