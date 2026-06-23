#!/usr/bin/env bash
# Validate the module's JSON files: well-formed + required IP-Symcon keys present.
# Guards the exact regression that broke the v0.1 install (library.json missing
# date/license -> "Library nicht vorhanden"). Runs locally and in CI (needs jq).
set -uo pipefail

cd "$(dirname "$0")/.."
fail=0

echo "== JSON well-formed =="
while IFS= read -r -d '' f; do
  if jq empty "$f" >/dev/null 2>&1; then
    echo "  ok: $f"
  else
    echo "  BROKEN JSON: $f"; fail=1
  fi
done < <(find . -name '*.json' -not -path './.git/*' -print0)

echo "== library.json required keys =="
req_lib='has("id") and has("author") and has("name") and has("url") and has("version") and has("date") and has("license") and has("compatibility")'
if jq -e "$req_lib" library.json >/dev/null; then
  echo "  ok"
else
  echo "  FEHLT Pflichtfeld in library.json (id/author/name/url/version/date/license/compatibility)"; fail=1
fi

echo "== module.json required keys =="
req_mod='has("id") and has("name") and has("type") and has("prefix") and has("vendor")'
if jq -e "$req_mod" MCPBridge/module.json >/dev/null; then
  echo "  ok"
else
  echo "  FEHLT Pflichtfeld in MCPBridge/module.json (id/name/type/prefix/vendor)"; fail=1
fi

if [ "$fail" -eq 0 ]; then echo "ALLE JSON-CHECKS GRUEN"; else echo "JSON-CHECKS FEHLGESCHLAGEN"; fi
exit $fail
