#!/usr/bin/env bash
# Generate one image with the ElevenLabs Flows image API.
#   genimg.sh "<prompt>" <out-path> [aspect] [model] [resolution]
# The key is read from .env and never printed.
set -euo pipefail
PROMPT="$1"; OUT="$2"; ASPECT="${3:-16:9}"; MODEL="${4:-gemini-3-pro-image}"; RES="${5:-2K}"
KEY="$(grep -E '^ELEVENLABS_API_KEY=' .env | head -1 | cut -d= -f2- | tr -d '"'"'"'[:space:]')"
[ -n "$KEY" ] || { echo "no ELEVENLABS_API_KEY in .env" >&2; exit 1; }

body=$(MODEL="$MODEL" PROMPT="$PROMPT" ASPECT="$ASPECT" RES="$RES" python3 -c '
import json,os
print(json.dumps({"model_id":os.environ["MODEL"],"prompt":os.environ["PROMPT"],
                  "aspect_ratio":os.environ["ASPECT"],"resolution":os.environ["RES"]}))')

id=$(curl -sS -X POST https://api.elevenlabs.io/v1/flows/image \
      -H "xi-api-key: $KEY" -H 'Content-Type: application/json' \
      -d "$body" | python3 -c 'import sys,json; d=json.load(sys.stdin); print(d.get("id") or ("ERR "+json.dumps(d))[:300])')
case "$id" in ERR*) echo "$id" >&2; exit 1;; esac

for i in $(seq 1 90); do
  r=$(curl -sS "https://api.elevenlabs.io/v1/flows/image/$id" -H "xi-api-key: $KEY")
  st=$(printf '%s' "$r" | python3 -c 'import sys,json; print(json.load(sys.stdin).get("status",""))')
  [ "$st" = "completed" ] && break
  [ "$st" = "failed" ] && { printf '%s' "$r" | head -c 400 >&2; echo; exit 1; }
  sleep 2
done
[ "$st" = "completed" ] || { echo "timed out (status=$st)" >&2; exit 1; }
url=$(printf '%s' "$r" | python3 -c 'import sys,json; print(json.load(sys.stdin).get("content_url",""))')
mkdir -p "$(dirname "$OUT")"
curl -sS -o "$OUT" "$url"
echo "$(basename "$OUT") $(python3 -c "import os;print(os.path.getsize('$OUT')//1024)")KB"
