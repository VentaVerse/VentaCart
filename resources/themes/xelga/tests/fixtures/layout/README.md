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
