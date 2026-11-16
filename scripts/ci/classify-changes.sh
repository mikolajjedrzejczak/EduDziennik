#!/bin/sh
# Klasyfikuje zmianę: docs-only (same pliki *.md poza tests/ i .github/) albo code.
# Użycie: classify-changes.sh <base-sha> <head-sha>. Wynik: "code" lub "docs" na stdout.
# Brak bazy (nowa gałąź, force-push) lub błąd diffu => "code" (pełne kontrole, bezpieczny wariant).
set -eu
base="${1:-}"
head="${2:-HEAD}"
case "$base" in ''|0000000000000000000000000000000000000000) echo code; exit 0 ;; esac
if ! files=$(git diff --name-only "$base" "$head" 2>/dev/null); then
  echo code
  exit 0
fi
[ -n "$files" ] || { echo code; exit 0; }
result=docs
while IFS= read -r f; do
  case "$f" in
    tests/*|.github/*|scripts/*) result=code ;;
    *.md) ;;
    *) result=code ;;
  esac
done <<LIST
$files
LIST
echo "$result"
