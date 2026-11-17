#!/bin/sh
# Agregat required-check. Wejście z env: CHANGES_RESULT, KIND (code|docs), DOCS_RESULT,
# FRONTEND_RESULT, BACKEND_RESULT, E2E_RESULT (wyniki `needs.*.result`), E2E_ENABLED.
# code: E2E wymagane tylko przy E2E_ENABLED=true; wyłączone E2E musi być skipped.
set -eu
fail=0
need_success() {
  if [ "$2" != "success" ]; then
    echo "::error::Job $1 zakończył się wynikiem '$2' (wymagane: success)."
    fail=1
  fi
}
need_success changes "${CHANGES_RESULT:-missing}"
need_success docs "${DOCS_RESULT:-missing}"
case "${KIND:-}" in
  code)
    need_success frontend "${FRONTEND_RESULT:-missing}"
    need_success backend "${BACKEND_RESULT:-missing}"
    if [ "${E2E_ENABLED:-false}" = "true" ]; then
      need_success e2e "${E2E_RESULT:-missing}"
    elif [ "${E2E_RESULT:-missing}" != "skipped" ]; then
      echo "::error::Wyłączony job e2e powinien być pominięty, jest '${E2E_RESULT:-missing}'."
      fail=1
    fi
    ;;
  docs)
    for pair in "frontend:${FRONTEND_RESULT:-missing}" "backend:${BACKEND_RESULT:-missing}" "e2e:${E2E_RESULT:-missing}"; do
      case "${pair#*:}" in skipped) ;; *) echo "::error::Docs-only PR: job ${pair%%:*} powinien być pominięty, jest '${pair#*:}'."; fail=1 ;; esac
    done
    ;;
  *)
    echo "::error::Nieznana kategoria zmiany '${KIND:-}' — traktuję jako błąd."
    fail=1
    ;;
esac
[ "$fail" -eq 0 ] && echo "Wymagane kontrole dla kategorii '$KIND' zakończone sukcesem."
exit "$fail"
