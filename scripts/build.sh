#!/usr/bin/env bash
# Minify public/css/amz-inserts.css and pack an installable plugin zip.
set -euo pipefail

cd "$(dirname "$0")/.."
export PATH="$PWD/node_modules/.bin:$PATH"

css_source="public/css/amz-inserts.css"
css_min="public/css/amz-inserts.min.css"

minify_to() {
	esbuild "$css_source" --minify-whitespace --outfile="$1" --log-level=error
}

cmd="${1:-}"

case "$cmd" in
	css)
		minify_to "$css_min"
		;;
	check)
		tmp="$(mktemp)"
		minify_to "$tmp"
		if ! cmp -s "$tmp" "$css_min"; then
			rm -f "$tmp"
			echo "public/css/amz-inserts.min.css is out of date. Run npm run build:css and commit it." >&2
			exit 1
		fi
		rm -f "$tmp"
		;;
	zip)
		minify_to "$css_min"
		stage="$(mktemp -d)"
		trap 'rm -rf "$stage"' EXIT
		dest="$stage/amz-inserts"
		mkdir -p "$dest"
		tar \
			--exclude='.git' \
			--exclude='.github' \
			--exclude='node_modules' \
			--exclude='scripts' \
			--exclude='dist' \
			--exclude='.cursor' \
			--exclude='package.json' \
			--exclude='package-lock.json' \
			--exclude='AGENTS.md' \
			--exclude='.gitignore' \
			--exclude='.gitattributes' \
			-cf - . | (cd "$dest" && tar xf -)
		mkdir -p dist
		out="$PWD/dist/amz-inserts.zip"
		rm -f "$out"
		(cd "$stage" && zip -r -q "$out" amz-inserts)
		if unzip -l "$out" | grep -E 'node_modules/|package(-lock)?\.json|AGENTS\.md|/\.github/|/\.git/' >/dev/null; then
			echo "zip contains dev files" >&2
			exit 1
		fi
		unzip -l "$out" | grep -q 'amz-inserts/amz-inserts.php'
		unzip -l "$out" | grep -q 'amz-inserts/public/css/amz-inserts.min.css'
		echo "Wrote dist/amz-inserts.zip"
		;;
	*)
		echo "usage: scripts/build.sh css|check|zip" >&2
		exit 1
		;;
esac
