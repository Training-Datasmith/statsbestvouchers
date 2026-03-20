# Architecture: statsbestvouchers

## Purpose

A PrestaShop statistics module that ranks discount vouchers/cart rules by usage frequency and total discount value given, helping merchants measure promotion effectiveness.

## Directory Structure

```
statsbestvouchers.php   - Module class (ModuleGrid subclass); all business logic
upgrade/                - Migration scripts
tests/                  - PHPUnit test stubs and PHPStan bootstrap
translations/           - Locale string overrides
```

## Key Design Decisions

- **ModuleGrid inheritance**: Leverages PrestaShop's grid with built-in sort, pagination, and CSV export.
- **Cart-rule join**: Queries the `cart_rule` and `order_cart_rule` tables for usage and discount totals.

## Extension Points

- Override `getData()` to add voucher-type filtering or extra columns.

## Dependency Flow

```
statsbestvouchers (ModuleGrid)
  └─> hookDisplayAdminStatsModules() — renders the voucher ranking widget
  └─> getData()                      — voucher ranking SQL
        └─> Db::getInstance()
```
