#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "$0")/.." && pwd)"
output="${1:-$repo_root/passport-fit-search-install.zip}"
stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT
mkdir -p "$stage/passport-fit-search"
cp "$repo_root/wordpress-plugin/passport-fit-search/passport-fit-search.php" "$stage/passport-fit-search/"
cp "$repo_root/wordpress-plugin/passport-fit-search/homepage-fit-search.js" "$stage/passport-fit-search/"
cp "$repo_root/wordpress-plugin/passport-fit-search/README.md" "$stage/passport-fit-search/"
cp -R "$repo_root/data" "$stage/passport-fit-search/data"
(cd "$stage" && zip -q -r "$output" passport-fit-search)
echo "$output"
