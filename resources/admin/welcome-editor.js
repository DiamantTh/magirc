import { Editor } from '@tiptap/core';
import { Image } from '@tiptap/extension-image';
import { Link } from '@tiptap/extension-link';
import { Underline } from '@tiptap/extension-underline';
import { StarterKit } from '@tiptap/starter-kit';

const textarea = document.querySelector('#welcome-content');
const editorElement = document.querySelector('#welcome-editor');

if (textarea && editorElement) {
    const editor = new Editor({
        element: editorElement,
        extensions: [
            StarterKit,
            Link.configure({ openOnClick: false }),
            Image.configure({ allowBase64: false }),
            Underline,
        ],
        content: textarea.value,
        editorProps: {
            attributes: { class: 'tiptap-editor', role: 'textbox', 'aria-label': 'Welcome message' },
        },
        onUpdate: ({ editor: currentEditor }) => {
            textarea.value = currentEditor.getHTML();
        },
    });

    document.querySelector('#welcome-submit')?.addEventListener('click', () => {
        textarea.value = editor.getHTML();
    });
}
