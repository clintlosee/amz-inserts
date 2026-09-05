# Amazon Inserts

A WordPress plugin for reusable Amazon affiliate inserts: text links, image links, product cards, and card grids. Any blog with an Amazon Associates account can use it.

Inserts work in classic posts via a shortcode and in the block editor via the **Amazon Insert** block. There is no Node, npm, or build step. Copy the plugin folder into `wp-content/plugins/` and activate it.

Requires WordPress 6.4+ and PHP 8.1+.

## Install

1. Copy this folder into `wp-content/plugins/amz-plugin` (or `amazon-inserts`).
2. In wp-admin, activate **Amazon Inserts**.
3. Open **Amazon Inserts → Settings** and save your Associate tag (for example `yourname-20`). Full Amazon product URLs that do not already include `tag=` will use this value. Short links (`amzn.to`, `a.co`, `amzn.com`) are expanded to the product page first; the tag is not applied to the short URL itself. You can also customize the default CTA button label used by cards and grids.

Amazon and FTC rules still require a clear affiliate disclosure on the site. A footer notice is enough. Optionally enable **Show disclosure under inserts** if you also want a line under each insert.

## Create a saved unit

1. Go to **Amazon Inserts → Add New**.
2. Give it a name you will recognize (this label is for you, not shown on the front).
3. Choose a display: text link, image, card, or grid.
4. For a grid, set max columns (2–4). Layout is 2 columns on phones, 3 on tablets, and up to 4 on large screens.
5. Optionally set a CTA label for this unit, or leave it empty to use the global default.
6. Paste one or more Amazon URLs and set a title. For the image, either:
   - Paste an **Image URL** (right-click the product photo on Amazon → Copy image address), or
   - Click **Fetch from URL** (best-effort; Amazon often blocks this, and the plugin falls back to the ASIN image), or
   - **Select image** from the Media Library if you prefer to upload.
7. Publish the unit. Copy the shortcode from the list or the **Insert** box, for example:

```
[amz_unit id="123"]
```

## Product images

Hotlinked Amazon images rot over time, so the plugin tries to end up with a copy of every product photo in your Media Library:

- **Fetch from URL** previews the product page's `og:image`. When Amazon blocks the request or the page has no image, it falls back to Amazon's standard image address for the ASIN. You can replace the suggested Image URL before saving.
- Saving the unit downloads the final Image URL into the Media Library and stores the attachment ID. Units created before this version pick up a local copy the next time you save them.
- The ASIN is parsed from the product URL, and, when the page is readable, from its canonical link. Short links like `amzn.to` and `a.co` are expanded to the final Amazon product URL (and ASIN) when Fetch or unit save can follow the redirect. If Amazon blocks that request, the short URL is kept and the unit still saves; Associates tagging may not apply until the link can be expanded.
- Only images on Amazon's own hosts are downloaded. If you paste an image address of your own, it is left exactly as you typed it.
- Nothing here can cost you a unit. A failed download leaves the URL, title, and ASIN untouched and the unit saves normally; the front end then falls back to the stored image URL, and finally to the ASIN image address. A URL that fails is not retried for six hours.
- Manually selected Media Library images are preserved. Images previously imported by the plugin are not re-downloaded unless you change their Image URL.

Saving a post does not import images for **Custom** block products, because they are not a unit. Those products use the image URL or Media Library attachment stored on the block, then fall back to the ASIN image address. The shared preview endpoint returns the suggested image URL without importing it.

To turn the copying off entirely, return `false` from the `amz_inserts_sideload_images` filter.

## Insert into a post

**Classic editor:** paste `[amz_unit]` or `[amz_link]` where you want the insert.

**Block editor:** add the **Amazon Insert** block.

- **Saved unit** — pick a unit you already published.
- **Custom** — one-off text, image, card, or grid in this post without saving a unit. Paste an Amazon URL and optionally click **Fetch from URL** for a best-effort title, image URL, and ASIN (same lookup as saved units; Amazon often blocks it). Fetch also expands short Amazon links to the product URL and applies the Associate tag there. Custom blocks keep that image URL; they are not imported into the Media Library on post save.

Saved units and custom blocks use the same front-end markup, so a grid looks the same either way.

Cards are fully clickable (image, title, and CTA button). Links open Amazon in a new tab with `rel="nofollow sponsored noopener"`.

## One-off `[amz_link]` shortcode

For a single Amazon link without creating a saved unit, use `[amz_link]`. Default display is a **text** link. Attributes:

| Attribute | Purpose |
| --- | --- |
| `url` | Amazon product URL. Optional if `asin` is set. |
| `asin` | 10-character ASIN. Used to build a URL when `url` is omitted, and for the image fallback. |
| `title` | Link or card text. Text display falls back to the URL if this is empty. |
| `display` | `text` (default), `button`, `image`, or `card`. |
| `image_url` / `image_id` | Optional image for `image` and `card`. Otherwise the ASIN image is used when an ASIN is known. |
| `cta` | Optional button/card label. Otherwise the global default CTA is used. For `button`, `title` is used when `cta` is omitted. |

Examples:

```
[amz_link url="https://www.amazon.com/dp/B0EXAMPLE1" title="Widget"]
[amz_link asin="B0EXAMPLE1" title="Widget"]
[amz_link asin="B0EXAMPLE1" title="Widget" display="button"]
[amz_link asin="B0EXAMPLE1" title="Widget" display="card" cta="See it on Amazon"]
[amz_link url="https://www.amazon.com/dp/B0EXAMPLE1" title="Widget" display="image" image_url="https://m.media-amazon.com/images/I/example.jpg"]
```

When `url` is omitted, the shortcode builds `https://www.amazon.com/dp/{ASIN}`. There is no marketplace setting; amazon.com is the default. Links go through the same normalize / Associate tag / `rel` handling as saved units. Missing or invalid `url`/`asin` renders nothing.

Text, image, and card reuse the same templates as `[amz_unit]`. `button` is a thin CTA styled like the card button.

## What v1 does not do

Live Amazon prices and the Product Advertising API (PA-API) are not included. Each product stores URL, title, image, and ASIN (parsed from the URL when possible) so PA-API can fill the same fields later without a data migration.

If you add PA-API later you will need an active Associates account, API keys, request signing, and a refresh job. v1 is meant to work without any of that.
