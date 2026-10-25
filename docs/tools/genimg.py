#!/usr/bin/env python3
"""Generate demo artwork from a shot manifest (--manifest).

    python3 docs/tools/genimg.py --provider gemini            # all missing shots
    python3 docs/tools/genimg.py --provider gemini --only hero
    python3 docs/tools/genimg.py --provider elevenlabs --limit 2 --dry-run

Keys are read from .env and never printed. Existing files are skipped, so
the run is resumable and a failed shot can be retried on its own.
"""
import argparse, base64, json, os, pathlib, re, sys, time, urllib.request, urllib.error

ROOT = pathlib.Path(__file__).resolve().parents[2]

def env(name):
    f = ROOT / ".env"
    if not f.exists():
        return os.environ.get(name)
    for line in f.read_text().splitlines():
        m = re.match(rf"^{name}=(.*)$", line.strip())
        if m:
            return m.group(1).strip().strip("'\"") or os.environ.get(name)
    return os.environ.get(name)

def post(url, body, headers, timeout=180):
    req = urllib.request.Request(url, data=json.dumps(body).encode(), headers={"Content-Type": "application/json", **headers})
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return json.loads(r.read().decode())

def get(url, headers=None, timeout=120):
    req = urllib.request.Request(url, headers=headers or {})
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return r.read()

# --- providers: each returns raw image bytes ---------------------------------

def gemini(shot, model):
    key = env("GEMINI_API_KEY") or env("GOOGLE_API_KEY")
    if not key:
        raise SystemExit("no GEMINI_API_KEY in .env (create one at aistudio.google.com)")
    url = f"https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={key}"
    prompt = f"{shot['prompt']}. Aspect ratio {shot['aspect_ratio']}."
    data = post(url, {"contents": [{"parts": [{"text": prompt}]}]}, {})
    for part in data.get("candidates", [{}])[0].get("content", {}).get("parts", []):
        blob = part.get("inlineData") or part.get("inline_data")
        if blob and blob.get("data"):
            return base64.b64decode(blob["data"])
    raise RuntimeError("no image in response: " + json.dumps(data)[:300])

def elevenlabs(shot, model):
    key = env("ELEVENLABS_API_KEY")
    if not key:
        raise SystemExit("no ELEVENLABS_API_KEY in .env")
    h = {"xi-api-key": key}
    body = {"model_id": model, "prompt": shot["prompt"], "aspect_ratio": shot["aspect_ratio"]}
    # Only some models take a resolution; the flash tiers reject it outright.
    if "resolution" in shot and model not in NO_RESOLUTION:
        body["resolution"] = shot["resolution"]
    # The GPT tiers take a quality dial the Gemini ones do not.
    if model.startswith("gpt-image"):
        body["quality"] = os.environ.get("IMG_QUALITY", "high")
    job = post("https://api.elevenlabs.io/v1/flows/image", body, h)
    if not job.get("id"):
        raise RuntimeError(json.dumps(job)[:300])
    for _ in range(90):
        time.sleep(2)
        st = json.loads(get(f"https://api.elevenlabs.io/v1/flows/image/{job['id']}", h).decode())
        if st.get("status") == "completed":
            return get(st["content_url"])
        if st.get("status") == "failed":
            raise RuntimeError(f"{st.get('failure_reason')}: {st.get('error_message')}")
    raise RuntimeError("timed out")

NO_RESOLUTION = {"gemini-2.5-flash-image", "gemini-3.1-flash-image", "gemini-3.1-flash-lite-image"}

DEFAULT_MODEL = {"gemini": "gemini-2.5-flash-image", "elevenlabs": "gemini-2.5-flash-image"}

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--provider", choices=["gemini", "elevenlabs"], default="gemini")
    ap.add_argument("--model")
    ap.add_argument("--manifest", default="docs/tools/store-demo-shots.json",
                    help="which sheet to generate from, relative to the project root")
    ap.add_argument("--only", help="one folder name from the manifest's paths, e.g. hero or products")
    ap.add_argument("--limit", type=int)
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--force", action="store_true", help="regenerate even if the file exists")
    a = ap.parse_args()

    # A manifest is either a bare list of shots or an object with a "shots"
    # key, so a sheet can carry a note about what it is for.
    loaded = json.loads((ROOT / a.manifest).read_text())
    shots = loaded["shots"] if isinstance(loaded, dict) else loaded
    if a.only:
        shots = [s for s in shots if f"/{a.only}/" in s["path"]]
    todo = [s for s in shots if a.force or not (ROOT / s["path"]).exists()]
    if a.limit:
        todo = todo[: a.limit]

    print(f"{len(shots)} shots, {len(todo)} to make, provider={a.provider}")
    if a.dry_run:
        for s in todo:
            print("  ", s["path"], "|", s["prompt"][:90])
        return

    fn = {"gemini": gemini, "elevenlabs": elevenlabs}[a.provider]
    model = a.model or DEFAULT_MODEL[a.provider]
    made = failed = 0
    for i, s in enumerate(todo, 1):
        out = ROOT / s["path"]
        out.parent.mkdir(parents=True, exist_ok=True)
        try:
            data = fn(s, model)
            out.write_bytes(data)
            made += 1
            print(f"  [{i}/{len(todo)}] {out.name}  {len(data)//1024}KB")
        except Exception as e:
            failed += 1
            print(f"  [{i}/{len(todo)}] {out.name}  FAILED  {str(e)[:160]}")
    print(f"done: {made} made, {failed} failed")

if __name__ == "__main__":
    main()
