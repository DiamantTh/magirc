import { Editor } from '@tiptap/core';
import { StarterKit } from '@tiptap/starter-kit';

const textarea = document.querySelector('#welcome-content');
const editorElement = document.querySelector('#welcome-editor');

if (textarea && editorElement) {
    const editor = new Editor({
        element: editorElement,
        extensions: [StarterKit],
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
