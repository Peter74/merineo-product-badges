=== Merineo Product Badges ===
Contributors: merineo
Tags: woocommerce, products, badges, labels, sale, stock
Requires at least: 6.5
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Visual, configurable WooCommerce product badges: NEW, SALE, stock statuses, bestseller, category and product-specific labels, with multiple display styles and hooks.

== Description ==

Merineo Product Badges adds a flexible, theme-friendly system for displaying product badges for WooCommerce.

You can configure:

* Global enable/disable toggle
* Where badges appear:
  * Single product page (standard hooks or custom hook)
  * Product archives / categories (standard hooks or custom hook)
  * Custom placement via hook name
* How badges look:
  * Style variants: pill, square, rounded, circle, vertical ribbon, corner ribbon, tag
  * Outline vs solid
  * Inline vs stacked layout
  * Left/right alignment
  * Font size, text transform, font weight, letter spacing
  * Optional subtle shadow
  * Custom CSS editor (scoped to a wrapper to avoid breaking the theme)

Automatic badges (generated from product data):

* **New** – based on product age (configurable number of days)
* **Featured / Recommended** – based on `_featured` (product visibility)
* **Sale** – with multiple modes:
  * Plain text
  * Percentage (e.g. `-15 %`)
  * Saved amount (e.g. `-5,00 €`, using `wc_price()` formatting)
* **Out of stock**
* **In stock**
* **Backorder**
* **Bestseller** – for top X selling products, using a transient cache

Manual badges:

* Per category (all products in that category inherit its badges)
* Per product (product inherits global + category + product-specific badges)

Badges are always rendered in a consistent, predictable order:

1. Featured (Recommended)
2. Sale
3. New
4. Category badges
5. Product-specific badges

The plugin:

* Hides the default WooCommerce `Sale!` badge and replaces it with its own logic.
* Uses WooCommerce CRUD / public APIs (no core hacks).
* Declares compatibility with WooCommerce HPOS (High-Performance Order Storage).
* Loads only on WooCommerce-related pages (shop, product, category, tag).

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via the WordPress Plugins screen.
2. Activate the plugin through the **Plugins → Installed Plugins** screen.
3. Go to **Merineo → Product Badges** in the WordPress admin.
4. Configure:
   * Layout & placement (hooks, alignment, inline/stacked)
   * Styles (variants, outline, typography)
   * Automatic badges (NEW, SALE, stock, bestseller)
   * Category and product-specific badges (via category and product edit screens)
5. Turn on the global **Enable product badges** toggle.

== Frequently Asked Questions ==

= Does this plugin support HPOS (High-Performance Order Storage)? =

Yes. The plugin declares compatibility with HPOS and does not interact directly with order tables.

= Does it replace the default WooCommerce "Sale!" badge? =

Yes. The default `Sale!` badge is disabled globally and replaced by the plugin's own Sale badge (with configurable modes).

= Will it work with my theme? =

The plugin uses standard WooCommerce hooks for placement. You can:

* Use common hooks from dropdowns, or
* Enter any custom hook name

Badges are rendered inside a wrapper `<div>` with scoped CSS classes to avoid breaking your theme layout.

= Can I style the badges with my own CSS? =

Yes. There is a custom CSS editor on the settings page. You can target:

* The wrapper: `.merineo-badges-scope`
* The container: `.merineo-badges`
* Individual badges via:
  * `.merineo-badge--type-{type}` (e.g. `sale`, `new`, `bestseller`)
  * `.merineo-badge--source-{source}` (e.g. `auto`, `category`, `product`)

We recommend scoping your CSS under `.merineo-badges-scope` so you don't affect other elements.

= How are colors handled? =

Each badge can define:

* Background color
* Text color

These values are stored and passed as CSS custom properties (`--merineo-badge-bg`, `--merineo-badge-color`), so all styles (solid, outline, ribbon, tag, etc.) can reuse them consistently.

= How does the bestseller badge work? =

The plugin:

1. Uses `wc_get_products()` with `orderby => 'popularity'` to fetch top X product IDs.
2. Caches them in a transient for 1 day.
3. Marks products whose IDs are in that list with the configured "Bestseller" badge.

== Screenshots ==

1. Global settings: layout, hooks, alignment, layout mode
2. Design tab: style variants, outline, typography, custom CSS
3. Automatic badges: NEW, SALE, stock, bestseller configuration
4. Category badge editor
5. Product badge side meta box
6. Example badges on product archives
7. Example badges on single product

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
First public release of Merineo Product Badges.