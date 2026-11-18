/** The folder tree: built from the flat list the server sends. No DOM. */

export function buildTree(folders) {
    const tree = new Map();

    folders.forEach((f) => {
        tree.set(f.path, { path: f.path, name: f.name, parent: f.parent ?? null, count: f.count ?? 0, children: [] });
    });
    tree.forEach((node) => {
        if (node.parent !== null && tree.has(node.parent)) tree.get(node.parent).children.push(node.path);
    });

    return tree;
}

/** Folders whose name contains the term, plus every folder above them. */
export function filterTree(tree, term) {
    const needle = String(term || '').trim().toLowerCase();
    const keep = new Set();

    tree.forEach((node) => {
        if (needle !== '' && !node.name.toLowerCase().includes(needle)) return;
        keep.add(node.path);
        let parent = node.parent;
        while (parent !== null && tree.has(parent) && !keep.has(parent)) {
            keep.add(parent);
            parent = tree.get(parent).parent;
        }
    });

    return keep;
}

/** Every folder above a path, the library root first. */
export function ancestors(path) {
    const parts = String(path).split('/');
    return parts.slice(0, -1).map((_, i) => parts.slice(0, i + 1).join('/'));
}

/** "media/products/Demo" reads "Media / products / Demo"; names keep their case. */
export function folderLabel(path) {
    const parts = String(path).split('/');
    if (parts[0]) parts[0] = parts[0][0].toUpperCase() + parts[0].slice(1);
    return parts.join(' / ');
}
