import { accessSync, constants } from 'node:fs';
import { resolve } from 'node:path';
import { dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const required = [
  'assets/vendor/jquery/jquery.min.js',
  'assets/vendor/jquery-ui/jquery-ui.min.js',
  'assets/vendor/jquery-ui/jquery-ui.min.css',
  'assets/vendor/datatables/jquery.dataTables.min.js',
  'assets/vendor/datatables/dataTables.jqueryui.min.js',
  'assets/vendor/datatables/dataTables.jqueryui.min.css',
  'assets/vendor/flag-icon/flag-icon.min.css',
  'assets/vendor/highcharts/highstock.js',
  'assets/vendor/js-cookie/js.cookie.js',
  'assets/vendor/moment/moment-with-locales.min.js',
  'assets/vendor/jquery-form/jquery.form.js',
];

for (const file of required) {
  accessSync(resolve(root, file), constants.R_OK);
}

console.log(`Runtime assets: OK (${required.length} files)`);
