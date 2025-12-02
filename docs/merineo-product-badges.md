# Merineo Product Badges – Documentation

Merineo Product Badges is a WooCommerce extension that provides flexible, visual product badges driven by automatic rules and manual configuration.

---

## 1. Requirements

- WordPress 6.5+
- WooCommerce 9.0+
- PHP 8.1+
- HPOS compatible (no direct SQL on order tables)

---

## 2. Admin UI Overview

### 2.1. Where to find the settings

- Top-level menu: **Merineo**
- Submenu: **Product Badges**
- Settings page slug: `merineo-product-badges`

The settings are organized into tabs:

1. **Layout & Placement**
2. **Design**
3. **Automatic Badges**
4. **Custom CSS / Advanced** (depending on your version)

There are also editors on:

- **Product categories** (category-level badges)
- **Single products** (product-level badges)

---

## 3. Layout & Placement

### 3.1. Global toggle

- **Enable product badges** (checkbox / toggle)
    - When **disabled**:
        - No badges are rendered anywhere.
    - When **enabled**:
        - Badges are rendered based on the placement configuration below.

### 3.2. Placement areas

Each area has:

- A **hook dropdown** (common WooCommerce hooks)
- A **custom hook text field**
- A **priority** (integer, default 10)
- **Alignment** (Left / Right)

Areas:

1. **Single product**

   Controls badges on single product pages.

   Recommended hooks:
    - `woocommerce_before_single_product_summary`
    - `woocommerce_single_product_summary`
    - `woocommerce_before_add_to_cart_form`

2. **Product archives / loop**

   Controls badges in:

    - Shop page
    - Category archives
    - Tag archives
    - Any standard WooCommerce loop

   Recommended hooks:
    - `woocommerce_before_shop_loop_item_title`
    - `woocommerce_before_shop_loop_item`

3. **Custom placement**

   Allows output on any custom hook provided by the theme or other plugins.

    - If the custom hook does not exist, the badges simply won’t render.

### 3.3. Alignment & layout

For each context (single / loop / custom) you can configure:

- **Alignment**: Left / Right
    - Implemented via `.merineo-badges--align-left` / `--align-right`.
- **Layout**: Inline / Stacked
    - Inline → badges in a row
    - Stacked → badges one per line

Badges in loops and on single products are rendered as **overlays** (absolutely positioned) within:

- `li.product` (loop)
- `div.product` (single)

The plugin ensures `pointer-events: none` on wrappers so badges don’t block clicks on product cards.

---

## 4. Design (Styles)

### 4.1. Global design settings

- **Font size** (px)
- **Text transform**
    - Normal / Uppercase
- **Font weight**
    - Normal / Bold
- **Letter spacing** (px, can be decimal)
- **Shadow**
    - ON/OFF (subtle box-shadow)

These are injected as CSS custom properties:

- `--merineo-badge-font-size`
- `--merineo-badge-text-transform`
- `--merineo-badge-font-weight`
- `--merineo-badge-letter-spacing`
- `--merineo-badge-shadow`

### 4.2. Style variants

Available variants:

- `pill` (default)
- `square`
- `rounded`
- `circle`
- `ribbon-vertical`
- `ribbon-corner`
- `tag`

Rendered via wrapper classes on the container:

- `.merineo-badges--style-pill`
- `.merineo-badges--style-square`
- `.merineo-badges--style-rounded`
- `.merineo-badges--style-circle`
- `.merineo-badges--style-ribbon-vertical`
- `.merineo-badges--style-ribbon-corner`
- `.merineo-badges--style-tag`

Each style uses CSS to create its shape (border-radius, pseudo-elements `::after` for ribbons and tags).

### 4.3. Outline vs solid

- **Outline** mode:
    - Enabled via `style_outline` (Design tab)
    - Adds class `.merineo-badges--outline`
    - Base badge uses:
        - `background-color: transparent;`
        - `border-color: var(--merineo-badge-bg)`

### 4.4. Layout: inline vs stacked

- **Inline**:
    - `.merineo-badges--inline` – `flex-direction: row`
- **Stacked**:
    - `.merineo-badges--stacked` – `flex-direction: column`
    - Additional rules adjust alignment for Right/Left in stacked mode.

---

## 5. Automatic Badges

All automatic badge settings are on the **Automatic Badges** tab. Each badge type has:

- Enable toggle
- Label (text)
- Background color
- Text color
- Additional logic depending on type

### 5.1. New badge

- Shows when product creation date is not older than **N days** (configurable).
- Config:
    - Enabled
    - Label (e.g. `New`, `Novinka`)
    - Days (e.g. `14`)
    - Background / text colors

### 5.2. Featured / Recommended

- Based on WooCommerce `_featured` flag.
- Config:
    - Enabled
    - Label (e.g. `Recommended`)
    - Background / text colors

### 5.3. Sale badge

- Shown when product is on sale (`is_on_sale()`).
- Modes:
    1. **Hidden**
    2. **Label** – use the provided static label
    3. **Percent** – `-15 %` (rounded)
    4. **Amount** – saved amount, using WooCommerce price formatting (`wc_price()`), e.g. `-5,00 €`

- Config:
    - Enabled
    - Label (used in "Label" mode)
    - Mode (`label`, `percent`, `amount`, `hidden`)
    - Background / text colors

Price difference uses:

- `wc_get_price_to_display()` for regular and sale prices
- `wc_price()` with `in_span => false` for display

### 5.4. Stock badges

Based on product stock status:

- `instock` → **In stock** badge
- `outofstock` → **Out of stock**, if backorders are not allowed
- `onbackorder` → **Backorder** badge

Each has:

- Enabled
- Label
- Background / text colors

### 5.5. Bestseller badge

- Shows for top X selling products.

Logic:

1. Reads X from settings (e.g. 20).
2. Uses `wc_get_products()` with:
    - `orderby => 'popularity'`
    - `limit => X`
3. Caches result in a transient for 1 day.
4. If current product ID is in that list, it gets the **Bestseller** badge.

Config:

- Enabled
- Label
- Top count (X)
- Background / text colors

---

## 6. Manual Badges (Categories & Products)

### 6.1. Category-level badges

On **Products → Categories → Edit category** you get a Metabox for badges:

- Repeater list:
    - Label
    - Background color (color picker)
    - Text color (color picker)
- Data are stored as JSON in term meta (key `merineo_product_badges`).

All products in that category inherit these badges.

If product belongs to multiple categories, it inherits badges from all of them.

### 6.2. Product-level badges

On the product edit screen (right-hand side meta box):

- Repeater with:
    - Label
    - Background color
    - Text color
- Stored as JSON in post meta (key `_merineo_product_badges`).

These badges are appended **after** automatic and category-level badges.

---

## 7. Badge Rendering (Frontend)

### 7.1. Wrapper structure

For each product, badges are ultimately rendered as:

```html
<div class="merineo-badges-scope merineo-badges-scope--{context}">
    <div class="merineo-badges
                merineo-badges--align-{left|right}
                merineo-badges--style-{variant}
                merineo-badges--{inline|stacked}
                merineo-badges--context-{single|loop|custom}
                [merineo-badges--outline]">
        <span class="merineo-badge merineo-badge--type-{type} merineo-badge--source-{source}"
              style="--merineo-badge-bg:{color};--merineo-badge-color:{color};">
            Label
        </span>
        …
    </div>
</div>