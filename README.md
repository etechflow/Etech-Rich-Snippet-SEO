# Etechflow_RichSnippets

Hyvä-native structured-data module for Magento 2 / Adobe Commerce. Emits a single
cross-linked schema.org **JSON-LD `@graph`** per page plus **OpenGraph + Twitter
Card** meta. A leaner, self-owned alternative to Mirasvit_SeoMarkup.

## Schema coverage

| Page | Output (one `@graph` per page) |
|------|--------------------------------|
| Product | `Product` / `ProductGroup` (+ `variesBy`/`hasVariant` for configurables) with tax-aware `Offer`/`AggregateOffer`, sale `UnitPriceSpecification`, `priceValidUntil`, `itemCondition`, payment/delivery methods, `AggregateRating` + `Review`, `BreadcrumbList`, `Organization`, `WebSite` |
| Category / landing | current-page `ItemList` + `BreadcrumbList` + `Organization` + `WebSite` |
| CMS + home | `WebPage` + `Organization` + `WebSite` |
| All pages | `og:*` + `twitter:*` meta, and the `og:` head `prefix` |

Entities are linked by `@id` (e.g. `Offer.seller` and `WebSite.publisher` resolve
to the single `Organization` node).

## Architecture

- **Block classes render via `_toHtml()`** (`Block/Product`, `Category`, `Cms`, `Meta`)
  returning the `<script>`/`<meta>` string directly — **no `.phtml` templates**. This is
  deliberate: in production mode with HTML minification, new module templates require a
  preprocessed copy; rendering from PHP sidesteps that entirely.
- Schema-building logic lives in `ViewModel/*` (`Product`, `Category`, `CmsPage`, `Site`,
  `Breadcrumbs`, `OpenGraph`, `Config`) so it is unit-testable and reusable.
- Prices are tax-aware (honour `tax/display/type`) and use the active store currency.

## Configuration

**Stores → Configuration → Etechflow → Rich Snippets**

| Path | Default | Notes |
|------|---------|-------|
| `etechflow_richsnippets/general/enabled` | `0` | Master switch. OFF = emits nothing (safe alongside another provider). |
| `etechflow_richsnippets/product/enabled` | `1` | |
| `etechflow_richsnippets/product/variants_enabled` | `1` | ProductGroup for configurables |
| `etechflow_richsnippets/product/{brand,mpn,gtin13,condition}_attribute` | brand/sku/–/– | Attribute-code overrides |
| `etechflow_richsnippets/category/enabled` | `1` | |
| `etechflow_richsnippets/opengraph/enabled` | `1` | OpenGraph + Twitter |
| `etechflow_richsnippets/cms/enabled` | `1` | CMS + home WebPage |

## Install

```bash
bin/magento module:enable Etechflow_RichSnippets
bin/magento setup:upgrade
rm -rf generated/code/* generated/metadata/*   # required: di:compile won't clean this itself
bin/magento setup:di:compile
bin/magento cache:flush
# then flip the master switch on:
bin/magento config:set etechflow_richsnippets/general/enabled 1
```

If replacing another provider (e.g. Mirasvit), disable its output first to avoid
duplicate schema. To avoid a duplicate breadcrumb, suppress the theme's native
breadcrumb JSON-LD (this module's breadcrumb is the full category path).

## Tests

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Etechflow/RichSnippets/Test/Unit
```
