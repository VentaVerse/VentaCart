# Media library

The media library is the one place a store's images live. Every image field
in the admin (product photos, Variant images, category and brand images, the
mega menu banner, a blog post's featured image, the logo and favicon) and a
theme's editor choose images from it, and the **Catalog > Media library**
page manages it: folders, uploads, renaming, moving and deleting.

Images are files on the `public` disk under `media/`, in folders the
merchant arranges. A field stores the image's storage path, such as
`media/products/demo-001.jpg`, and the storefront builds the address from it.
The library knows which records use each path, so it can refuse to delete an
image that is in use and can rename or move an image without breaking the
records that show it.

## Adding the library to a page

```blade
<x-media-library />
```

The component prints the library's script and stylesheet, and a JSON block
(`#media-library-config`) with the addresses it calls, the CSRF token, the
limits and what the signed-in admin may do. It prints once per page however
many fields include it, and prints nothing for a guest. Every admin image
field (`<x-admin.media-picker>`) includes it; a theme editor includes it in
its own page.

The library reads these settings each time it opens, so a page that replaces
the block is obeyed.

## Opening the library from script

The component's script sets `window.VentaCart.media` and then dispatches
`ventacart:media-ready` on `document`, with the hook as `event.detail`. Code
that may run first waits for the event:

```js
function withMedia(use) {
    if (window.VentaCart?.media) return use(window.VentaCart.media);
    document.addEventListener('ventacart:media-ready', (e) => use(e.detail), { once: true });
}
```

`window.VentaCart.media.version` is `1`.

### open(options)

Opens the library in a dialog and returns `{ close() }`.

```js
VentaCart.media.open({
    mode: 'multi',
    limit: 4,
    inUse: ['media/banners/summer.jpg'],
    kind: 'banners',
    folder: 'media/banners',
    title: 'Add photos',
    where: ['Main banner', 'Slides'],
    onChoose(files) {
        files.forEach((file) => addSlide(file.path));
    },
});
```

| Option | Meaning |
|---|---|
| `mode` | `'single'` (the default) picks one image; `'multi'` picks several, numbered in the order they were picked. |
| `limit` | In `multi` mode, the most images that may be picked; `null` (the default) is no limit. Ignored in `single` mode. |
| `value` | The path, or array of paths, the field already holds. They start picked, and the library opens in the folder of the first. |
| `inUse` | In `multi` mode, paths the field already shows. They are marked "Already added" and cannot be picked again. |
| `kind` | The kind of field, such as `'products'` or `'brands'`. The library remembers the last folder per kind in the browser, so every field of a kind reopens where the last one left off. Defaults to `'default'`. |
| `folder` | The folder to open in when the field holds nothing and no folder is remembered for its kind, such as `'media/banners'`. A folder that does not exist falls back to `media`. |
| `accept` | File extensions that may be chosen, such as `['png']`. Other images stay visible but cannot be picked. Defaults to every extension the library lists. |
| `title` | The dialog's heading. Defaults to "Choose an image" in `single` mode and "Add images" in `multi` mode. |
| `where` | Strings that say what the image is for, shown under the heading: the first in bold, the rest after it. |
| `opener` | The element focus returns to when the dialog closes. Defaults to the element that had focus when `open()` was called. |
| `onChoose` | Called once with an array of chosen images when the merchant presses the choose button. Not called on cancel. |
| `onClose` | Called every time the dialog closes, after `onChoose` when something was chosen. |

`open()` throws when the page has no `<x-media-library />` settings. An
admin without `media.view` gets a small dialog saying they need it, and
`onClose` still runs when it closes.

### mount(element, options)

Shows the same library inside `element` instead of a dialog and returns
`{ go(path), refresh(), destroy() }`. It takes the same options; `mode`
defaults to `'multi'` and `limit` to `null`, and the choose button appears
only when `onChoose` is given.

One option belongs to `mount()` alone: `manage`, set to `true`, adds the management tools of the Media library page
(rename, move, delete, folder menus, the details panel and import from a web
address). They come from the page's own script, so the option does nothing on
another page.

### The chosen image

`onChoose` always receives an array, in the order the images were picked.
Each item is:

| Field | Meaning |
|---|---|
| `path` | The storage path on the `public` disk. Store this. |
| `url` | The image's public address, or `null`. |
| `thumb` | The thumbnail's address, or the image's own address when it has none. |
| `name` | The file name. |
| `folder` | The folder path. |
| `width` | Width in pixels, or `null` when not yet known. |
| `height` | Height in pixels, or `null` when not yet known. |
| `size` | Size in bytes. |
| `usedBy` | `{ total, label }`: how many records use the image and, when they are all one kind, that kind (`"products"`); `label` is `null` for a mix. |

Store `path`, never `url`: the address depends on the disk and the store's
domain, and a path is what the library can find again when the image moves.

## Telling the library where you store paths

The library protects only the paths it knows about. A package (an extension
or a theme) that stores library paths in its own tables registers them in
its service provider's `boot()`, so the library counts those records as
uses, refuses to delete an image they show, and rewrites them when the image
is renamed or moved.

```php
use App\Support\Media\ColumnReference;
use App\Support\Media\MediaReferences;

public function boot(): void
{
    // A column whose whole value is a path.
    MediaReferences::register(ColumnReference::path('lookbook_items', 'image', 'lookbook items'));

    // A JSON column; any string inside it may be a path.
    MediaReferences::register(ColumnReference::json('lookbook_pages', 'content', 'lookbook pages'));
}
```

- `ColumnReference::path($table, $column, $label, $owner = null, $countsAsUse = true)`
  is for a column that holds one path. `$label` is the kind of record as the
  admin shows it, plural ("products", "menus"). With `$owner`, the record
  counted is the value of that column, so two columns that belong to one
  record count it once: core registers a product's main image (`products.image`
  by `id`) and its gallery (`product_images.path` by `product_id`) both under
  "products", and a product that shows an image twice is still one product.
- `ColumnReference::json($table, $column, $label, $countsAsUse = true)` is for
  a column holding JSON. Every string in the decoded document is compared, and
  a value that is not JSON is compared whole. Every row is read on each
  lookup and move, so use it only for small tables (settings, page designs).
- A source skips itself while its table does not exist, so registering a
  table that a disabled package removed is harmless.
- Anything else implements `App\Support\Media\MediaReferenceSource`: `label()`,
  `countsAsUse()`, `owners(array $paths)` (who points at each path) and
  `repoint(string $from, string $to)` (point every stored copy at the new
  path and return how many rows changed). The library calls `repoint()` inside
  its own transaction.

Core registers products, product images, Variant images, categories, brands,
menu items, settings, blog posts' featured images, order lines and the
library's own `media_files` rows. Settings are registered whole, so a theme
setting of type `media` or `images` is protected without registering
anything.

### What counts as a use

A source registered with `countsAsUse: false` is a record, not a use. Its
copies are rewritten when an image moves, so the record keeps showing the
image, but it never stops a merchant deleting the image. Order lines are the
example: an order keeps a copy of the photo it was sold with, and a store
must still be able to delete a photo no product shows any more. After such a
delete, the order shows a blank tile where the photo was. A theme's saved
earlier versions are another.

A folder is moved by moving every path inside it: a stored value matches a
folder when it equals the folder or starts with the folder followed by `/`.

### Paths are matched exactly

Comparison is exact: case-sensitive, and `_` and `%` are ordinary
characters. A store can hold `media/products/Demo` and `media/products/demo`
side by side, and MySQL's usual collations compare text without regard to
case, so SQL is used only to narrow the rows (with `=`, or `LIKE ... ESCAPE`
with `%`, `_` and `\` escaped), and every candidate is checked in PHP with
`===` or `str_starts_with($value, $folder . '/')` before it counts or is
written. Every write goes by primary key. JSON is decoded and its strings
compared; escaped forms such as `\/` never matter. A custom source must follow
the same rule.

### Images inside rich text

Images placed inside rich text (a product or category description, a page's
body, a blog post's content) are not tracked. They do not count as uses, and
renaming or moving the image does not rewrite them, so such a picture breaks
when its file is renamed, moved or deleted. Link to a stable image, or edit
the text after a move.

## Permissions

| Permission | Allows |
|---|---|
| `media.view` | browsing, searching, and seeing where an image is used |
| `media.create` | uploading, importing from a web address, and making folders |
| `media.edit` | renaming and moving images and folders |
| `media.delete` | deleting images, and deleting empty folders |

On **Settings > User Groups** they show as Media: View, Create, Edit and
Delete. The component tells the script what the signed-in admin holds, and
the library draws only the tools they may use; the server checks every
request anyway. The routes are `admin.media.*`. A theme editor or extension
opens the library through `window.VentaCart.media` and never calls these
routes itself, so their addresses and answers can change without breaking it.

## Limits

| Limit | Value |
|---|---|
| Upload types | JPG, PNG and WebP, decided by the file's content, not its name |
| Largest upload | 5 MB (`MediaLibrary::MAX_KB`, 5120) |
| Largest image | 6000 pixels on either side (`MediaLibrary::MAX_SIDE`) |
| Images per upload request | 20 (`MediaLibrary::MAX_FILES`) |
| Images per page when browsing | 60 (`MediaLibrary::PER_PAGE`) |
| Listed types | `jpg`, `jpeg`, `png`, `gif` and `webp`; `*_thumb.*` files are thumbnails and never listed |

The largest image is 6000 pixels because making a thumbnail decodes the whole
image at about 4 bytes per pixel: 6000 x 6000 is about 144 MB, which fits a
256 MB PHP memory limit, while 8000 x 8000 needs about 256 MB on its own and
ends the request with a fatal error that no catch block can handle.

The limits live as constants on `App\Support\MediaLibrary` and reach the
script only through `<x-media-library />`.

### Rate limits

Counted per signed-in admin, per minute:

| Limiter | Requests | Covers |
|---|---|---|
| `media-reads` | 240 | browse, folder tree, where an image is used |
| `media-writes` | 60 | upload, import, new folder, rename, move, delete |
| `media-imports` | 10 | import from a web address, on top of `media-writes` |

Reads are generous because the library searches as the merchant types.
Importing makes the server download someone else's file, so it has the
tightest limit. A refused request answers 429 with a JSON `message` the
library shows as it is.

## Image sizes: media:dimensions

The library shows each image's width and height, kept in `media_files` (one
row per path). Uploads record them, and browsing fills them in the first time
an older image is shown, so nothing has to run on upgrade.

```bash
php artisan media:dimensions
```

measures every library image that has no row yet, in chunks, so memory stays
flat. It is optional: it only saves the first browse of a large library from
measuring. It can be run again at any time.

- `--prune` also removes rows whose file is gone. It changes nothing when the
  `media` folder is missing, unreadable or empty, since no row can then be
  shown to be orphaned, and keeps any row whose path it cannot check.
- `--force` lets `--prune` go ahead when most rows would be removed, which
  more often means the disk is unavailable than that the library was emptied.

## Tables that are not the library

`store_media` and `media_folders` come from an earlier design. Nothing in
core reads or writes them, and the library does not use them. The library
keeps no folder table: folders are directories on the disk, and
`media_files` holds only sizes.
