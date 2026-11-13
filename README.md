# Exception honeycomb

A Zabbix 7.4 dashboard widget. It works like the stock Honeycomb, except that a cell only appears when the
item's latest value satisfies a condition. When everything is fine, the widget shows one "all clear" cell.

## Install

1. Copy this directory to `ui/modules/exceptionhoneycomb` on the frontend server.
2. Go to Administration > General > Modules, click **Scan directory**, and enable **Exception honeycomb**.
3. Add the widget to a dashboard.

## Configuration

Everything from the stock Honeycomb is there (host groups, hosts, host and item tags, item patterns, labels,
thresholds). The additions are below.

**Show when value**: an operator plus a value. Only items whose latest value satisfies it become cells.

| Operator | Value example | Notes |
|---|---|---|
| `=`, `<>` | `1` | Numeric comparison when both sides are numbers, so `1` equals `1.0`. Otherwise string comparison. |
| `<`, `<=`, `>`, `>=` | `90`, `5m`, `1G` | Numeric only. Suffixes K, M, G, T, s, m, h, d, w are accepted. Non-numeric values never match. |
| in list / not in list | `2, 7` | Comma-separated, same equality rules as `=`. |
| matches regex / does not match regex | `^(down\|lowerLayerDown)$` | Bare PCRE pattern, no delimiters. |

Leave the value empty and the widget shows every item, like the stock Honeycomb.

**Compare**: test the condition against the raw value or the value-mapped text. "Mapped value" falls back to the raw
value for items without a value map, or values the map does not cover.

**Show items without data**: include items with no value in the history period. They get the background color and
"No data" as their value label. Useful when "stopped reporting" is as bad as the failure itself.

Under Advanced configuration:

- **Name regex**: applied to the item name. `{MATCH}` in a label is replaced with the first capture group (or the
  whole match if there are no groups, or the full item name if it does not match).
- **Sort cells by**: host and item name, or value ascending or descending. Non-numeric values and items without
  data sort last.
- **Background color**: defaults to red, since everything this widget shows is by definition a problem.
  Thresholds still override it for numeric items.
- **All clear text** and **All clear color**: the single cell shown when items were checked but none matched.
  `{COUNT}` is the number of items checked. If no items match the pattern at all, the stock "No data" cell is shown
  instead, so a misconfigured widget is not mistaken for a healthy one.

## Example: fabric links that are not up

- Item patterns: `*Fabric Physical Link*: Operational status`
- Show when value: `<>` `1`
- Primary label: `{MATCH}`, Name regex: `Interface (.+?)\(`
- Secondary label: Text, `{HOST.NAME}`

## Notes

- Every item matching the pattern is evaluated on each refresh (up to 10,000), because the widget cannot know which
  ones are bad without reading their last values. Only the cells that fit are rendered.
- The JS classes are renamed (`CSVGExceptionHoneycomb`, `CWidgetExceptionHoneycomb`) so the module can coexist with
  the stock Honeycomb. Cell styling reuses the core `svg-honeycomb` CSS, so it follows the active theme.
