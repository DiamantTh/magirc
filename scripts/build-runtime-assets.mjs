import { cpSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const sourceRoot = resolve(root, 'node_modules');
const targetRoot = resolve(root, 'assets/vendor');

const files = [
  ['jquery/dist/jquery.min.js', 'jquery/jquery.min.js'],
  ['jquery-ui-dist/jquery-ui.min.js', 'jquery-ui/jquery-ui.min.js'],
  ['jquery-ui-themes/themes/smoothness/jquery-ui.min.css', 'jquery-ui/jquery-ui.min.css'],
  ['datatables.net/js/jquery.dataTables.min.js', 'datatables/jquery.dataTables.min.js'],
  ['datatables.net-jqui/js/dataTables.jqueryui.min.js', 'datatables/dataTables.jqueryui.min.js'],
  ['datatables.net-jqui/css/dataTables.jqueryui.min.css', 'datatables/dataTables.jqueryui.min.css'],
  ['flag-icon-css/css/flag-icon.min.css', 'flag-icon/flag-icon.min.css'],
  ['highcharts/highstock.js', 'highcharts/highstock.js'],
  ['js-cookie/src/js.cookie.js', 'js-cookie/js.cookie.js'],
  ['moment/min/moment-with-locales.min.js', 'moment/moment-with-locales.min.js'],
  ['jquery-form/src/jquery.form.js', 'jquery-form/jquery.form.js'],
];

const directories = [
  ['jquery-ui-themes/themes/smoothness/images', 'jquery-ui/images'],
  ['flag-icon-css/flags', 'flags'],
];

for (const [source, target] of files) {
  const destination = resolve(targetRoot, target);
  mkdirSync(dirname(destination), { recursive: true });
  cpSync(resolve(sourceRoot, source), destination);
}

for (const [source, target] of directories) {
  mkdirSync(resolve(targetRoot, target), { recursive: true });
  cpSync(resolve(sourceRoot, source), resolve(targetRoot, target), { recursive: true });
}

console.log(`Runtime assets copied to ${targetRoot}`);
