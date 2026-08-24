# Amazon Inserts

A WordPress plugin for reusable Amazon affiliate inserts: text links, image links, product cards, and card grids. Any blog with an Amazon Associates account can use it.

Inserts work in classic posts via a shortcode and in the block editor via the **Amazon Insert** block. There is no Node, npm, or build step. Copy the plugin folder into `wp-content/plugins/` and activate it.

Requires WordPress 6.4+ and PHP 8.1+.

## Install

1. Copy this folder into `wp-content/plugins/amz-plugin` (or `amazon-inserts`).
2. In wp-admin, activate **Amazon Inserts**.
3. Open **Amazon Inserts → Settings** and save your Associate tag (for example `yourname-20`). Links that do not already include `tag=` will use this value.

Amazon and FTC rules still require a clear affiliate disclosure on the site. A footer notice is enough. Optionally enable **Show disclosure under inserts** if you also want a line under each insert.

## Create a saved unit

1. Go to **Amazon Inserts → Add New**.
2. Give it a name you will recognize (this label is for you, not shown on the front).
3. Choose a display: text link, image, card, or grid.
4. For a grid, set max columns (2–4). Layout is 2 columns on phones, 3 on tablets, and up to 4 on large screens.
5. Paste one or more Amazon URLs and set a title. For the image, either:
   - Paste an **Image URL** (right-click the product photo on Amazon → Copy image address), or
   - Click **Fetch from URL** (best-effort; Amazon often blocks this), or
   - **Select image** from the Media Library if you prefer to upload.
6. Publish the unit. Copy the shortcode from the list or the **Insert** box, for example:

```
[amz_unit id="123"]
```

## Insert into a post

**Classic editor:** paste the shortcode where you want the insert.

**Block editor:** add the **Amazon Insert** block.

- **Saved unit** — pick a unit you already published.
- **Custom** — one-off text, image, card, or grid in this post without saving a unit.

Saved units and custom blocks use the same front-end markup, so a grid looks the same either way.

Cards are fully clickable (image and title). Links open Amazon in a new tab with `rel="nofollow sponsored noopener"`.

## What v1 does not do

Live Amazon prices and the Product Advertising API (PA-API) are not included. Each product stores URL, title, image, and ASIN (parsed from the URL when possible) so PA-API can fill the same fields later without a data migration.

If you add PA-API later you will need an active Associates account, API keys, request signing, and a refresh job. v1 is meant to work without any of that.
