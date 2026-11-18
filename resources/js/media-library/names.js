/**
 * The server's rule for folder names, message for message, so a refusal
 * shows as the name is typed instead of after a request. No DOM.
 *
 * The library cannot open a path with "..", "./" or a leading dot in it,
 * so a folder named that way could be made but never browsed or removed;
 * those names are refused with the characters a file system or a web
 * address cannot hold and the hidden characters a pasted name can carry.
 */

export const FOLDER_NAME_MAX = 80;

// What PHP's trim() removes: the server trims with it before it checks.
const PHP_SPACE = /^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g;

export const phpTrim = (value) => String(value).replace(PHP_SPACE, '');

/** The message the server gives for a folder name, or null when it is fine. */
export function folderNameError(name) {
    if (typeof name !== 'string' || phpTrim(name) === '') return 'Give the folder a name.';

    const n = phpTrim(name);

    // Counted in characters, not UTF-16 units, as the server counts them.
    if ([...n].length > FOLDER_NAME_MAX) return `A folder name can be at most ${FOLDER_NAME_MAX} characters.`;
    if (/[\x00-\x1F\x7F]/.test(n)) return 'A folder name cannot contain line breaks, tabs or other hidden characters.';
    if (/[/\\:*?"<>|]/.test(n)) return 'A folder name cannot contain / \\ : * ? " < > or |.';
    if (n.startsWith('.')) return 'A folder name cannot start with a dot.';
    if (n.endsWith('.')) return 'A folder name cannot end with a dot.';
    if (n.includes('..')) return 'A folder name cannot contain two dots in a row.';

    return null;
}
