# Layout normalizer cases

Each `*.json` file here is one case for the layout normalizer, read by both
the PHP test (`tests/Feature/XelgaLayoutTest.php`) and the editor's JS
tests, so the server and the editor read a page document the same way.

```
{
  "name": "file name without .json",
  "rules": { "min": { "<section type>": <span> }, "main": ["<type>"], "sides": true },
  "input": { ...page document... },
  "expected": { ...normalized document... },
  "moved": [ { "path": "content.sections.<id>", "reason": "<code>", "value": "<new row id>" } ],
  "rows": [ ...optional: what rows() gives for input... ]
}
```

A type not listed under `rules.min` asks for 12 twelfths. Every `expected`
must also normalize to itself with `moved` empty.

`_layout.json` is the editor's `layout` boot payload (`Layout::payload()`).

## Reasons in `moved`

| reason | when |
|---|---|
| `too-wide` | the section needs a wider column or side than it sits in |
| `main-section` | the page's main section sat in a multi-column row or a side |
| `no-sides` | the page cannot have sides, so the section left one |

Each moved section gets its own full-width row: right after its row when it
came from a column, at the end when it came from a side. Sections that sat
in no row and no side are placed without a report.

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
