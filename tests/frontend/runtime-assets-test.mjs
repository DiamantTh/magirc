import { accessSync, constants, readdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const required = [
  'httpdocs/assets/js/magirc.js',
  'httpdocs/assets/vendor/jquery/jquery.min.js',
  'httpdocs/assets/vendor/jquery-ui/jquery-ui.min.js',
  'httpdocs/assets/vendor/jquery-ui/jquery-ui.min.css',
  'httpdocs/assets/vendor/datatables/jquery.dataTables.min.js',
  'httpdocs/assets/vendor/datatables/dataTables.jqueryui.min.js',
  'httpdocs/assets/vendor/datatables/dataTables.jqueryui.min.css',
  'httpdocs/assets/vendor/flag-icon/flag-icon.min.css',
  'httpdocs/assets/vendor/highcharts/highstock.js',
  'httpdocs/assets/vendor/js-cookie/js.cookie.js',
  'httpdocs/assets/vendor/moment/moment-with-locales.min.js',
  'httpdocs/assets/vendor/jquery-form/jquery.form.js',
];

for (const file of required) {
  accessSync(resolve(root, file), constants.R_OK);
}

for (const template of [
  'themes/default/templates/layout.twig',
  'themes/modern-mature/templates/layout.twig',
]) {
  const source = readFileSync(resolve(root, template), 'utf8');
  if (!source.includes('src="assets/js/magirc.js"')) {
    throw new Error(`${template} does not load the migrated runtime asset.`);
  }
}

const stylesheets = [];
function collectStylesheets(directory) {
  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    const file = resolve(directory, entry.name);
    if (entry.isDirectory()) {
      collectStylesheets(file);
    } else if (entry.isFile() && file.endsWith('.css')) {
      stylesheets.push(file);
    }
  }
}

collectStylesheets(resolve(root, 'httpdocs/assets'));
for (const stylesheet of stylesheets) {
  const source = readFileSync(stylesheet, 'utf8');
  for (const match of source.matchAll(/url\(\s*(?:(["'])(.*?)\1|([^)]*?))\s*\)/g)) {
    const url = (match[2] ?? match[3] ?? '').trim();
    if (!url || /^(?:data:|https?:|\/\/|#|var\()/i.test(url)) {
      continue;
    }
    const localPath = decodeURIComponent(url.split(/[?#]/, 1)[0]);
    accessSync(resolve(stylesheet, '..', localPath), constants.R_OK);
  }
}

console.log(`Runtime assets: OK (${required.length} files, ${stylesheets.length} stylesheets)`);
