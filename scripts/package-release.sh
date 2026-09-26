#!/bin/sh
set -eu

root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$root"

if ! git diff --quiet HEAD --; then
    echo 'Release packaging requires a clean committed worktree.' >&2
    exit 2
fi
if [ -n "$(git status --porcelain)" ]; then
    echo 'Release packaging requires no untracked source files.' >&2
    exit 2
fi

output_dir=${1:-"$root/dist"}
case "$output_dir" in
    /*) ;;
    *) output_dir="$root/$output_dir" ;;
esac
mkdir -p "$output_dir"
output_dir=$(CDPATH= cd -- "$output_dir" && pwd -P)

version=$(php -r '$package = json_decode(file_get_contents("package.json"), true, 512, JSON_THROW_ON_ERROR); echo $package["version"];')
ref=$(git rev-parse --verify HEAD)
commit=$(git rev-parse --short=12 "$ref")
source_date=$(git show -s --format=%cI "$ref")
archive_stem="magirc-$version-$commit"
work=$(mktemp -d "${TMPDIR:-/tmp}/magirc-release.XXXXXX")
stage="$work/$archive_stem"
archive="$output_dir/$archive_stem.tar.gz"

cleanup()
{
    rm -rf -- "$work"
}
trap cleanup EXIT HUP INT TERM

mkdir -p "$stage"
git archive "$ref" | tar -x -C "$stage"

composer install \
    --working-dir="$stage" \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

(
    cd "$stage"
    yarn install --frozen-lockfile --production=false --ignore-scripts
    yarn build:editor
    yarn build:runtime
    node tests/frontend/editor-bundle-test.mjs
    node tests/frontend/runtime-assets-test.mjs
)

for file in \
    "$stage/vendor/autoload.php" \
    "$stage/httpdocs/assets/js/magirc.js" \
    "$stage/httpdocs/assets/admin/js/welcome-editor.bundle.js" \
    "$stage/httpdocs/assets/vendor/jquery/jquery.min.js"
do
    if [ ! -f "$file" ]; then
        echo "Release package is missing required file: ${file#"$stage"/}" >&2
        exit 1
    fi
done

rm -rf -- "$stage/node_modules"
if [ -d "$stage/vendor/phpunit" ] || [ -d "$stage/node_modules" ]; then
    echo 'Release package unexpectedly contains development dependencies.' >&2
    exit 1
fi
if find "$stage/httpdocs/assets" -type f -name '*.php' -print -quit | grep -q .; then
    echo 'Release package contains PHP files inside the public asset tree.' >&2
    exit 1
fi

tar --sort=name --mtime="$source_date" --owner=0 --group=0 --numeric-owner -cf - -C "$work" "$archive_stem" \
    | gzip -n > "$archive"
(cd "$output_dir" && sha256sum "$archive_stem.tar.gz" > "$archive_stem.tar.gz.sha256")

echo "Release package: $archive"
echo "Checksum: $archive.sha256"
