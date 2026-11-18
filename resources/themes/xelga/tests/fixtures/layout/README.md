# Layout normalizer cases

Each `*.json` file here is one case for the layout normalizer, read by both
the PHP test (`tests/Feature/XelgaLayoutTest.php`) and the editor's JS
tests, so the server and the editor read a page document the same way.

```
{
  "name": "file name without .json",
  "rules": {
    "min": { "<section type>": <span> }, "main": ["<type>"], "sides": true,
    "own_row": ["<type>"], "content_only": ["<type>"]
  },
  "input": { ...page document... },
  "expected": { ...normalized document... },
  "moved": [ { "path": "content.sections.<id>", "reason": "<code>", "value": "<new row id>" } ],
  "rows": [ ...optional: what rows() gives for input... ]
}
```

A type not listed under `rules.min` asks for 12 twelfths. `rules.sides`
says whether the page may have areas and sides; a page has them only when
it also holds one of the `rules.main` types. `rules.own_row` lists the
types that keep a one-column row of their own, `rules.content_only` the
types that belong in Content only; both are optional. Every `expected` must
also normalize to itself with `moved` empty.

`_layout.json` is the editor's `layout` boot payload (`Layout::payload()`).
Its `sides_on` lists the theme's own page types that have areas and sides
(those with a main section, except the cart); the real payload also lists
the page types of installed extensions that have them.

## Columns beside the sides

A column draws its part of the content area, and the content area is what
the sides leave of the page: 12 twelfths without sides, 9 with one, 6 with
both. So a column of part `p` is really `p x content / 12` twelfths of the
page wide, and a section stays in it only when its span is no more than
that (`span x 12 <= p x content`). An 8 column beside one side is 6
twelfths wide; beside two, 4. A one-column row is always the whole content
area wide, so only columns of a multi-column row are measured this way; on
a page with areas, Content measures its one-column rows too (see Areas).

The content area counts each side that still holds a section once the
side's own misfits have left it, hidden sections included: a hidden section
can be shown again without a save, and a section must never draw narrower
than it was made for. (The storefront leaves out a side whose sections are
all hidden, so the content is then wider than measured, never narrower.)
A page that cannot have sides is measured at 12.

These cases live in `content-width/`, and in `areas/` for pages with areas
(the `column-fit-*` cases there). Both tests read `content-width/` with the
rest.

## Areas

A page with page content has areas: each row is in Top, Content or Bottom
(its `area`), in that order down the page. The sides stand beside Content
only; Top and Bottom run the whole page width and are measured at 12.
Content is measured at the content area the sides leave, one-column rows
included, except for the main section.

A row without a stored `area` takes one from its place: Top above the main
section's row, Content for that row, Bottom below it. A page stored with
sides and no `area` on any row had its sides beside every row, so each of
its rows is Content. Then the main row is Content, and down the page the
area never goes back. When a Content row does not fit, it leaves Content
with every Content row between it and the edge of Content, so the page
keeps its reading order. `order` reads Top rows, left side, Content rows,
right side, Bottom rows. A page without sides drops its rows when they are
plain and each row's area is the one its place gives it.

A page without areas (one that may not have sides, or that holds no main
section) has no `area` keys and no sides: its side sections go to rows at
the end.

These cases live in `areas/`, read by both the PHP and the JS tests. For
them, `rows` is what rows() (the editor's rowsOf()) gives for input with
the case's rules, so each row carries its area.

## Reasons in `moved`

| reason | value | when |
|---|---|---|
| `too-wide` | the new row id, or the area | the section needs a wider column, side or Content than it sits in |
| `in-order` | the area | the section's row left Content with a too-wide row, to keep the page's order |
| `main-section` | the new row id | the page's main section sat in a multi-column row or a side |
| `own-row` | the new row id | a section that keeps a row of its own sat in a multi-column row or a side |
| `content-only` | the new row id | a section that belongs in Content sat in Top or Bottom |
| `no-sides` | the new row id | the page cannot have sides, so the section left one |

A section moved out of a column or side gets its own full-width row: right
after its row when it came from a column (in that row's area), at the end
when it came from a side. A section that belongs in Content moves to the
start of Content from Top, to the end of Content from Bottom. Sections that
sat in no row and no side are placed without a report.

## Row limit and row ids

A page never comes out with more than `MAX_ROWS` rows (`_layout.json`
`max_rows`). When the rows made for moved or unplaced sections take it over,
empty rows are dropped: first a row this run emptied by moving its sections
out, then other empty rows from the end. See `row-limit-drops-emptied-row`.

A new row id is `row-` and the start of the SHA-1 of its seed (the first
section in the row, or `row` for an empty one), 8 to 36 hex digits, the
shortest one free. When every length is taken, the seed gets a number
(`row` becomes `row-2`, then `row-3`, ...) and that is hashed the same way. See
`many-empty-rows-get-ids`.
