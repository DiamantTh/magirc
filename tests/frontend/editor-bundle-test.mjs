import { readFileSync } from 'node:fs';

const bundle = readFileSync(new URL('../../admin/js/welcome-editor.bundle.js', import.meta.url), 'utf8');
if (!bundle.includes('tiptap-editor') || !bundle.includes('Welcome message')) {
  throw new Error('The Tiptap welcome editor bundle is missing the expected editor contract.');
}
if (bundle.includes('CKEDITOR')) {
  throw new Error('The generated editor bundle still contains CKEditor.');
}
console.log('Tiptap editor bundle: OK');
