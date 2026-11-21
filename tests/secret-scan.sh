#!/usr/bin/env bash
# Blocca segreti nel codice: file .env, chiavi private, token noti e
# PASSWORD/SECRET/TOKEN/API_KEY con valori lunghi scritti in chiaro.
set -u
fail=0
files=$(git ls-files)
if echo "$files" | grep -qE '(^|/)\.env(\..*)?$'; then echo "FAIL: file .env nel repository"; fail=1; fi
patterns=(
  '-----BEGIN [A-Z ]*PRIVATE KEY-----'
  'github_pat_[A-Za-z0-9_]{20,}'
  'gh[pousr]_[A-Za-z0-9]{30,}'
  'sk-ant-[A-Za-z0-9_-]{20,}'
  'sk_live_[A-Za-z0-9]{16,}'
  'AKIA[0-9A-Z]{16}'
  'xox[baprs]-[A-Za-z0-9-]{10,}'
  '(PASSWORD|SECRET|TOKEN|API_KEY)[A-Z_]*["'"'"']?\s*[:=]>?\s*["'"'"'][^"'"'"'$ ]{16,}["'"'"']'
)
for p in "${patterns[@]}"; do
  hits=$(echo "$files" | grep -v '^tests/secret-scan.sh$' | xargs -r grep -nEI -- "$p" 2>/dev/null)
  if [ -n "$hits" ]; then echo "FAIL: possibile segreto ($p):"; echo "$hits" | sed 's/^/  /'; fail=1; fi
done
[ $fail -eq 0 ] && echo "ok: nessun segreto trovato"
exit $fail
