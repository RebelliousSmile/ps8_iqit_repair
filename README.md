# ps8_iqit_repair

PrestaShop 8 module — Diagnostic and repair tools for IQIT Warehouse theme modules.

## Problem solved

IQIT Warehouse theme modules (iqitsizechart, iqitmegamenu, etc.) sometimes have missing
shop association entries after a multishop migration. This module detects and repairs
these missing associations.

## Features

- **SizeChart repair**: rebuilds missing `iqitsizechart_shop` associations
- Extensible dispatcher for future iqit* module repairs
- Graceful degradation if no iqit* modules are detected
- Dry-run preview before applying any fix
- CSRF protection on all write operations

## Supported repairs

| Type | Table | Description |
|------|-------|-------------|
| `sizechart_shop` | `iqitsizechart_shop` | Missing shop associations for size charts |

## Requirements

- PrestaShop 8.x
- PHP 8.1+
- At least one `iqit*` module installed (iqitsizechart, iqitmegamenu, etc.)
- Doctrine DBAL (provided by PrestaShop)

## Installation

Upload to `modules/sc_iqit_repair/` and install from Back Office > Modules.

The module registers under **Advanced Parameters > Scriptami** using the shared `AdminScriptami` parent tab.

## Architecture

```
src/
├── Controller/Admin/     # IqitRepairController
├── Service/              # IqitFixerDispatcher, SizeChartFixer, AbstractFixer
└── Traits/               # HaveScriptamiTab
```

## Tests

```bash
composer install
./vendor/bin/phpunit --testdox
```

14 tests, 48 assertions.

## Part of the Scriptami Suite

- [ps8_verify_multishop](https://github.com/RebelliousSmile/ps8_verify_multishop) — Multishop data integrity
- [ps8_replace_text](https://github.com/RebelliousSmile/ps8_replace_text) — Find & replace across the database
- [ps8_giftcard_repair](https://github.com/RebelliousSmile/ps8_giftcard_repair) — Gift card data repair
- [ps8_iqit_repair](https://github.com/RebelliousSmile/ps8_iqit_repair) — IQIT Warehouse theme module repair
