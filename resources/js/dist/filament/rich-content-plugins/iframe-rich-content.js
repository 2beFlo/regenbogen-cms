const { Node, mergeAttributes } = window.FilamentRichEditor.tiptap.core

const attr = (name, defaultValue = null) => ({
    default: defaultValue,
    parseHTML: (element) => element.getAttribute(name),
})

export default Node.create({
    name: 'regenbogenIframe',
    group: 'block',
    atom: true,
    selectable: true,
    draggable: true,

    addAttributes() {
        return {
            src: attr('src'),
            title: attr('title'),
            width: attr('width'),
            height: attr('height'),
            loading: attr('loading'),
            referrerpolicy: attr('referrerpolicy'),
            allow: attr('allow'),
            allowfullscreen: {
                default: null,
                parseHTML: (element) => element.hasAttribute('allowfullscreen') ? 'allowfullscreen' : null,
                renderHTML: (attributes) => attributes.allowfullscreen ? { allowfullscreen: 'allowfullscreen' } : {},
            },
            sandbox: attr('sandbox'),
            class: attr('class'),
            style: attr('style'),
            scrolling: attr('scrolling'),
            frameborder: attr('frameborder'),
        }
    },

    parseHTML() {
        // Keep the node during source-mode round trips. The server-side
        // sanitizer is responsible for validating the URL in src.
        return [{ tag: 'iframe' }]
    },

    renderHTML({ HTMLAttributes }) {
        return ['iframe', mergeAttributes(HTMLAttributes)]
    },

    addNodeView() {
        return ({ node }) => {
            const dom = document.createElement('div')
            dom.className = 'regenbogen-iframe-placeholder'
            dom.setAttribute('contenteditable', 'false')

            const label = document.createElement('strong')
            label.textContent = 'Eingebetteter Inhalt (iframe)'

            const url = document.createElement('span')
            url.textContent = node.attrs.src || 'ohne Adresse'

            dom.append(label, url)

            return { dom }
        }
    },
})
