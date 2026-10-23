"""Small, synthetic, loopback-only Ollama trial; no video-provider call or model download."""
import argparse
import json
import pathlib
import subprocess
import time
import urllib.request


def request(path, body=None, timeout=120):
    data = None if body is None else json.dumps(body).encode()
    req = urllib.request.Request("http://127.0.0.1:11434" + path, data=data, headers={"Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=timeout) as response:
        return json.load(response)


def validate(story):
    shots = story.get("shots", [])
    return isinstance(story.get("title"), str) and bool(story.get("summary")) and len(shots) == 3 and all(
        isinstance(s, dict) and s.get("seconds") == 15 and isinstance(s.get("prompt"), str) and 20 <= len(s["prompt"]) <= 1500
        for s in shots
    )


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--model", default="mistral:latest")
    parser.add_argument("--output", default="artifacts/story-local/result.json")
    parser.add_argument("--with-render", action="store_true")
    args = parser.parse_args()
    installed = request("/api/tags", timeout=10)["models"]
    if args.model not in [m["name"] for m in installed]:
        raise SystemExit("Model not installed. This script never downloads a model.")
    before = request("/api/ps", timeout=10)
    already_loaded = any(m["name"] == args.model for m in before.get("models", []))
    schema = {"type": "object", "required": ["title", "summary", "shots"], "properties": {
        "title": {"type": "string"}, "summary": {"type": "string"}, "shots": {"type": "array", "minItems": 3, "maxItems": 3,
        "items": {"type": "object", "required": ["seconds", "prompt"], "properties": {"seconds": {"type": "integer", "enum": [15]}, "prompt": {"type": "string"}}}}}}
    results = {"model": args.model, "synthetic_only": True, "before": before, "runs": []}
    render = None
    try:
        for episode in range(1, 4):
            if episode == 3 and args.with_render:
                render = subprocess.Popen(["docker", "compose", "exec", "-T", "edit-worker", "ffmpeg", "-nostdin", "-v", "error", "-f", "lavfi", "-i", "testsrc2=size=1080x1920:rate=30", "-t", "45", "-an", "-c:v", "libx264", "-threads", "2", "-preset", "veryfast", "-f", "null", "-"], stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
                render_start = time.monotonic()
            history = [r["story"].get("summary", "") for r in results["runs"] if r.get("valid")]
            prompt = f"""Create episode {episode} of a weekly family-friendly video series.
Series bible: Milo is a ginger cat wearing the same white astronaut suit with a blue collar. He explores a moon garden. No new characters, outfits or locations inside an episode. No dialogue or on-screen text. The next shot must continue the preceding action.
Previously approved episode summaries: {json.dumps(history)}.
Write a NEW small adventure, not a repetition. It needs a visual hook, an action, and a satisfying resolution. It must be feasible as THREE 15-second shots, 45 seconds total.
Return only JSON with title and summary in French, and exactly 3 shots. Each shot has seconds:15 and an English video prompt describing the same character, its action, framing and transition to the next shot. The final shot must conclude the story. No commentary outside JSON."""
            started = time.monotonic()
            print(f"Episode {episode}: local request started", flush=True)
            response = request("/api/generate", {"model": args.model, "prompt": prompt, "stream": False, "format": schema,
                       "keep_alive": "5m", **({"think": False} if args.model.startswith("qwen3") else {}), "options": {"temperature": 0.4, "seed": 20261023 + episode, "num_ctx": 4096, "num_predict": 1000}})
            try:
                story = json.loads(response["response"])
            except json.JSONDecodeError:
                story = {"raw": response.get("response", "")}
            run = {"episode": episode, "wall_seconds": round(time.monotonic() - started, 3), "valid": validate(story), "story": story,
                   "load_seconds": response.get("load_duration", 0) / 1e9, "tokens": response.get("eval_count"), "done_reason": response.get("done_reason"),
                   "tokens_per_second": round(response.get("eval_count", 0) / max(response.get("eval_duration", 1) / 1e9, .001), 2), "resident": request("/api/ps", timeout=10)}
            if render is not None:
                run["render_running_at_text_completion"] = render.poll() is None
                _, error = render.communicate(timeout=120)
                run["synthetic_render"] = {"exit_code": render.returncode, "wall_seconds": round(time.monotonic() - render_start, 3), "error": error.decode(errors="replace")[:1000]}
                render = None
            results["runs"].append(run)
            print(f"Episode {episode}: {run['wall_seconds']} s, valid={run['valid']}, {run['tokens_per_second']} tokens/s", flush=True)
    finally:
        if render is not None:
            render.terminate()
        if not already_loaded:
            request("/api/generate", {"model": args.model, "keep_alive": 0}, timeout=20)
        output = pathlib.Path(args.output)
        output.parent.mkdir(parents=True, exist_ok=True)
        output.write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8")


if __name__ == "__main__":
    main()
