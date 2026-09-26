import { accessSync, constants, readFileSync } from 'node:fs';
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

console.log(`Runtime assets: OK (${required.length} files)`);
