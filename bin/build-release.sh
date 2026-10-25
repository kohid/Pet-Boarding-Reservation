#!/usr/bin/env bash
# Builds dist/pawfectly-reservations.zip from the committed files (HEAD), with the plugin in a
# top-level "pawfectly-reservations/" folder so WordPress installs and updates it in place.
#
# Usage: bin/build-release.sh
set -euo pipefail
cd "$(dirname "$0")/.."

if [ -n "$(git status --porcelain)" ]; then
	echo "Uncommitted changes: the zip is built from HEAD, so commit first." >&2
	exit 1
fi

version=$(sed -nE 's/^ \* Version:[[:space:]]*([0-9][^[:space:]]*).*/\1/p' pawfectly-reservations.php | head -1)
const=$(sed -nE "s/.*define\( 'PAW_VERSION', *'([^']+)'.*/\1/p" pawfectly-reservations.php | head -1)
if [ -z "$version" ] || [ "$version" != "$const" ]; then
	echo "Version mismatch: header '$version' vs PAW_VERSION '$const'." >&2
	exit 1
fi

echo "Checking PHP and running tests..."
for f in pawfectly-reservations.php includes/*.php includes/data/*.php templates/*.php; do php -l "$f" >/dev/null; done
bash bin/test.sh >/dev/null

mkdir -p dist
rm -f dist/pawfectly-reservations.zip
git archive --format=zip --prefix=pawfectly-reservations/ -o dist/pawfectly-reservations.zip HEAD

# The zip must carry everything the plugin needs at runtime, and nothing from development.
# List it once into a variable: piping unzip into `grep -q` can fail under pipefail.
listing=$(unzip -Z1 dist/pawfectly-reservations.zip)
for must in pawfectly-reservations/pawfectly-reservations.php pawfectly-reservations/includes/data/countries.php pawfectly-reservations/assets/vendor/flatpickr/flatpickr.min.js pawfectly-reservations/assets/js/form.js pawfectly-reservations/templates/form.php; do
	grep -qxF "$must" <<<"$listing" || { echo "Missing from zip: $must" >&2; exit 1; }
done
if grep -qE '^pawfectly-reservations/(tests|docs|bin|\.github)/' <<<"$listing"; then
	echo "Development files leaked into the zip." >&2
	exit 1
fi

echo "Built dist/pawfectly-reservations.zip (version $version, $(du -h dist/pawfectly-reservations.zip | cut -f1))"
