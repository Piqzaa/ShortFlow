"""Local CPU transcription. Model weights are installed at image build, never downloaded here."""
import json
import os
import sys
import wave

os.environ["HF_HUB_OFFLINE"] = "1"
os.environ["HF_HUB_DISABLE_TELEMETRY"] = "1"

from faster_whisper import WhisperModel

model = WhisperModel("/opt/vydflow-speech/model", device="cpu", compute_type="int8", cpu_threads=2, num_workers=1, local_files_only=True)
with wave.open(sys.argv[1], "rb") as audio_file:
    duration = audio_file.getnframes() / audio_file.getframerate()
language = sys.argv[2] if len(sys.argv) > 2 and sys.argv[2] in ("fr", "en") else None
segments, _ = model.transcribe(sys.argv[1], language=language, beam_size=3, word_timestamps=True, vad_filter=True, condition_on_previous_text=False)
captions = []
for segment in segments:
    if segment.no_speech_prob > 0.65:
        continue
    words = [word for word in segment.words or [] if word.word.strip()]
    for index in range(0, len(words), 5):
        block = words[index:index + 5]
        start = max(0.0, block[0].start)
        if captions:
            start = max(start, captions[-1]["end"])
        end = min(duration, block[-1].end)
        text = "".join(word.word for word in block).strip()
        if end > start and text:
            captions.append({"start": round(start, 3), "end": round(end, 3), "text": text[:160]})
        if len(captions) >= 300:
            break
    if len(captions) >= 300:
        break
print(json.dumps(captions, ensure_ascii=False))
