# Releasing Amazon Inserts

How to build the stylesheet, bump the version, tag a release, and install the zip in WordPress.

## Building CSS

`public/css/amz-inserts.css` is the source of truth. The plugin enqueues `public/css/amz-inserts.min.css`.

Run `npm install` once. That installs the esbuild dev dependency from `package.json`. After any edit to the CSS source, rebuild the min file:

```
npm run build:css
```

Commit both files in the same change:

- `public/css/amz-inserts.css`
- `public/css/amz-inserts.min.css`

`npm run build:css` runs `scripts/build.sh css`, which rewrites the min file from the source. Do not edit the min file by hand.

`npm run check:css` runs `scripts/build.sh check`. It rebuilds the min file in a temp file and compares it to `public/css/amz-inserts.min.css`. When they differ, the command exits with an error and tells you to run `npm run build:css` and commit the min file.

Pull requests run the Release workflow in `.github/workflows/release.yml`. That check runs `npm run check:css` and fails if the min file is stale.

`keep_cache_bust` re-adds `?ver=` from the min file's mtime because SiteGround strips query strings. The filter is `style_loader_src` at priority 9999.

## Bumping the version

Set the same version in both places in `amz-inserts.php`:

- the plugin header `Version:`
- the `AMZ_INSERTS_VERSION` constant

For a 1.2.0 release, both values are `1.2.0`. Commit that change with the rest of the release, then merge it before you tag.

## Releasing

Merge the pull request into `main` first. Then point your local checkout at that merged commit and tag it:

```
git checkout main && git pull
git tag vX.Y.Z && git push origin vX.Y.Z
```

The tag must match the version you set in the plugin. Version `1.2.0` uses the tag `v1.2.0`.

A git tag is a name stuck to one commit, so you can find that exact snapshot later. It stays on that commit after `main` moves on. Checking out `main` and pulling first puts the tag on the commit you just merged, including the version bump.

Pushing a tag that starts with `v` runs the Release workflow (`.github/workflows/release.yml`). The workflow checks the CSS, runs `npm run zip`, and attaches `amz-inserts.zip` to a GitHub Release.

## Installing

Download `amz-inserts.zip` from the GitHub Release. In wp-admin, open Plugins, Add New, Upload Plugin, and upload that file. The zip's top-level folder is `amz-inserts/`.

`npm run zip` builds the same zip on your machine and writes `dist/amz-inserts.zip`. It also refreshes `public/css/amz-inserts.min.css` before packing. The archive leaves out development files: `.git`, `.github`, `node_modules`, `package.json`, `package-lock.json`, `scripts/`, `docs/`, `AGENTS.md`, and a few other local files (`dist/`, `.cursor/`, `.gitignore`, `.gitattributes`).
