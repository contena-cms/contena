/**
 * @package admin
 * @private
 */

import fs from 'fs';
import path from 'path';
import cliProgress from 'cli-progress';
import colors from 'picocolors';
import { globSync } from 'glob';
import { extractDataSetIds } from './extract-data-set-ids';
import { isDataSetSourceFile } from '../public-api-source-files';

const srcFiles = globSync(`${path.join(__dirname, '../../src')}/**/*.*`).filter(isDataSetSourceFile);

console.log(colors.blue('Gathering data sets...\n'));

// Create and start a progress bar
const pb = new cliProgress.SingleBar({}, cliProgress.Presets.shades_classic);
pb.start(srcFiles.length, 0);

const result = srcFiles.flatMap((file) => {
    pb.increment();

    return extractDataSetIds(fs.readFileSync(file, { encoding: 'utf-8' }));
});

// Stop the progress bar
pb.stop();

const outputFile = path.join(__dirname, '../../src/meta/data-sets.json');

console.log(colors.blueBright(`\nWriting to ${outputFile}`));
fs.writeFileSync(outputFile, JSON.stringify(result));

console.log(colors.green('\nAll done!'));
