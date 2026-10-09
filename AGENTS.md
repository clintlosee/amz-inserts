# Amazon Inserts

WordPress plugin for reusable Amazon affiliate inserts: text links, image links, product cards, and grids. Any site with an Amazon Associates account can use it. Classic posts use shortcodes. The block editor uses the **Amazon Insert** block.

Requires WordPress 6.4+ and PHP 8.1+. Version `1.1.3` is the plugin header `Version` and the `AMZ_INSERTS_VERSION` constant. They must stay in sync.

Product Advertising API and live prices are out of scope. Each product stores URL, title, image, and ASIN so those fields can be filled later without a migration. Details for humans are in `README.md`.

## Structure

| Path | Role |
| --- | --- |
| `amz-inserts.php` | Plugin header, constants, file loads, activation and deactivation hooks. |
| `includes/class-plugin.php` | Bootstrap. `plugins_loaded` calls each class `init()`. |
| `includes/class-cpt-unit.php` | Private CPT `amz_unit` and unit meta. |
| `includes/class-settings.php` | **Amazon Inserts → Settings**. Option `amz_inserts_settings`. |
| `includes/class-url.php` | Amazon host allowlist, ASIN parsing, Associate tag. |
| `includes/class-fetch.php` | REST unit list and product preview. Expands short links on unit save. |
| `includes/class-image.php` | Copies Amazon product images into the Media Library. |
| `includes/class-renderer.php` | Shared front-end HTML for shortcodes and the block. |
| `includes/class-shortcode.php` | `[amz_unit]` and `[amz_link]`. |
| `includes/class-block.php` | Block `amz-inserts/insert`. |
| `admin/unit-editor.php` | Unit metaboxes, save handler, admin assets. |
| `admin/js/unit-editor.js` | Product repeater, media modal, Fetch from URL, copy shortcode. |
| `admin/js/block-editor.js` | Block UI. Plain JS on `wp.*` globals. |
| `admin/css/unit-editor.css` | Unit editor styles. |
| `public/css/amz-inserts.css` | Front-end CSS. Enqueued and inlined. |
| `public/css/amz-inserts.min.css` | Minified copy committed in the repo. PHP never enqueues this path. |
| `templates/` | `text.php`, `button.php`, `image.php`, `card.php`, `grid.php`. Included by the renderer. `grid.php` includes `card.php`. |
| `README.md` | Install and usage. |

There is no `languages/` directory, no `vendor/`, and no `.github/` workflows.

## Local setup and testing

No Docker, wp-env, Composer project, npm project, PHPUnit suite, or linter config is in this repo. `.gitignore` lists `vendor/`, `node_modules/`, and PHPUnit caches, but there is no `composer.json`, `package.json`, or test file. A few `phpcs:ignore` comments exist. There is no phpcs ruleset to run.

1. Copy this folder into `wp-content/plugins/` (README uses `amz-plugin` or `amazon-inserts`).
2. Activate **Amazon Inserts** in wp-admin.
3. Open **Amazon Inserts → Settings** and save an Associate tag.

Activation registers the CPT and calls `flush_rewrite_rules()`. The CPT sets `rewrite` to false.

Check a saved unit with `[amz_unit id="123"]`, a one-off `[amz_link]`, and the Amazon Insert block (saved unit and custom). Fetch from URL often fails when Amazon blocks the request. That is expected. See README.

## Conventions and gotchas

Prefixes:

- PHP classes: `Amz_Inserts_`
- Constants: `AMZ_INSERTS_FILE`, `AMZ_INSERTS_DIR`, `AMZ_INSERTS_URL`, `AMZ_INSERTS_VERSION`
- Text domain: `amz-inserts`
- Options, nonces, CPT, meta: `amz_inserts` / `amz_unit` / `_amz_*`
- REST namespace and block: `amz-inserts/v1`, `amz-inserts/insert`
- CSS: `amz-inserts`, `amz-inserts__*`, `amz-inserts--*`. Custom properties `--amz-cols` and `--amz-image-max`
- Admin script global: `amzInsertsAdmin`

PHP uses tabs. Class files and `amz-inserts.php` start with `if ( ! defined( 'ABSPATH' ) ) { exit; }`. Templates are includes and do not have that guard.

Escape output (`esc_html`, `esc_url`, `esc_attr`, `esc_textarea`, `wp_kses`). Sanitize input (`sanitize_text_field`, `sanitize_textarea_field`, `sanitize_key`, `absint`, `esc_url_raw`). Unit save checks nonce action `amz_inserts_unit` (field `amz_inserts_unit_nonce`) and `edit_post`. REST routes require `edit_posts`. Settings require `manage_options`.

`Amz_Inserts_Renderer::image_html()` returns escaped markup. Templates echo it with a phpcs ignore. `link_atts()` returns the fixed string `rel="nofollow sponsored noopener" target="_blank"`.

Strings use the `amz-inserts` text domain (`__`, `esc_html__`, `esc_html_e`, `esc_attr__`). Nothing calls `load_plugin_textdomain()`. The block script calls `wp.i18n.__` with that domain and is not passed to `wp_set_script_translations`.

On a release, bump the header `Version` and `AMZ_INSERTS_VERSION` together. Admin CSS and JS use that constant. Front CSS uses `filemtime()` of `amz-inserts.css`. `keep_cache_bust` (filter `style_loader_src` at priority 9999) puts `ver` back on the stylesheet URL because SiteGround minifies it to `amz-inserts.min.css` and drops `?ver=`. The renderer also inlines the full unminified file. Edit `public/css/amz-inserts.css` for front-end style changes.

TODO for Clint: say whether `public/css/amz-inserts.min.css` must be regenerated by hand when the source CSS changes.

Other fragile behavior:

- `with_tag()` adds `tag=` only when the query has no `tag`. Hosts `amzn.to`, `a.co`, and `amzn.com` are returned unchanged. Tag the expanded product URL.
- Short-link expansion runs on unit save (`expand_item_urls`) and on Fetch from URL. Rendering `[amz_link]` leaves short links unexpanded, so the Associate tag is skipped until the URL is a full product page.
- `from_asin()` always builds `https://www.amazon.com/dp/{ASIN}`. There is no marketplace setting. Pasted URLs may use the other hosts in `allowed_host_suffixes()`.
- Preview is `wp_remote_get` with an 8 second timeout and up to 5 redirects. If the final URL is off the Amazon allowlist, the fetched HTML is discarded (SSRF guard). The preview response suggests an image URL and does not import it.
- Sideload runs on unit save when filter `amz_inserts_sideload_images` is true (default) and the user can `upload_files`. Only Amazon image hosts are downloaded. Any other image URL is stored as typed. A failed download must leave the unit save intact. Failures sit in transient `amz_inserts_dl_fail_` plus the URL md5. Filter `amz_inserts_sideload_retry_delay` defaults to `6 * HOUR_IN_SECONDS`. Custom block products are not imported on post save.
- Front image order: Media Library attachment, then the stored image URL, then `https://m.media-amazon.com/images/P/{ASIN}.01._SCLZZZZZZZ_.jpg`.
- A unit that is not published renders only for a user who can `edit_post` that unit. Missing or invalid `[amz_link]` URL and ASIN render nothing.
- "Used in" is a `LIKE` scan of post content for `[amz_unit` or `wp:amz-inserts/insert`. Treat the count as approximate.
- Grid columns are clamped to 2-4. CSS is 2 columns, then 3 from 640px, then 4 from 1024px, capped by `--amz-cols`.
- Image units default to align `center` and size `medium` (320px). Cards default to size `full`. Small is 200px, large is 480px.
- `card.php` is one card. The renderer wraps a single card in the layout div. `grid.php` includes `card.php` per item, so a card markup change hits both displays.

## Branches and pull requests

Work on a branch. Open a pull request. Do not merge without Clint's explicit approval.

## Deploy

No GitHub Actions, release workflow, or release zip is in this repo. README says to copy the plugin folder into `wp-content/plugins/` and activate it. SiteGround is mentioned only in the front-end CSS cache-bust comment, not as a deploy pipeline.

TODO for Clint: confirm how production updates are shipped.

## Secrets and config

The plugin reads no env vars and stores no API keys. PA-API credentials are not implemented. `.gitignore` ignores `.env` and `.env.*` and keeps `.env.example`, but no `.env.example` file is in the repo.

Names only:

| Name | What |
| --- | --- |
| `amz_inserts_settings` | Option array. Keys: `associate_tag`, `cta_label`, `disclosure`, `show_disclosure`. |
| `amz_inserts` | Settings group for `register_setting` / `settings_fields`. |
| `amz-inserts-settings` | Settings submenu slug. Capability `manage_options`. |
| `amz_inserts_unit` | Nonce action for the unit editor. |
| `amz_inserts_unit_nonce` | Nonce field name. |
| `_amz_display`, `_amz_columns`, `_amz_items`, `_amz_cta_label`, `_amz_align`, `_amz_image_size` | Unit post meta. |
| `_amz_inserts_source_hash`, `_amz_inserts_source_url`, `_amz_inserts_asin` | Attachment meta on imported images. |
| `amz_inserts_dl_fail_{md5}` | Transient after a failed image download. |
| `amz_inserts_sideload_images` | Filter. `false` skips sideload. |
| `amz_inserts_sideload_retry_delay` | Filter. Seconds before a failed image URL is tried again. |
| `amz-inserts/v1/units` | `GET`. Requires `edit_posts`. |
| `amz-inserts/v1/preview` | `POST` field `url`. Requires `edit_posts`. |

## Do not

- Add PA-API, live prices, or a marketplace setting unless Clint asks.
- Abort a unit save because an image download or Amazon fetch failed.
- Put `tag=` on `amzn.to`, `a.co`, or `amzn.com`. Expand to a product URL first.
- Download images from hosts outside `Amz_Inserts_Url::allowed_image_host_suffixes()`.
- Commit secrets, `.env` values, or a real Associate tag.
