FROM vydflow-php
RUN apt-get update && apt-get install -y --no-install-recommends python3-venv fonts-dejavu-core && rm -rf /var/lib/apt/lists/*
RUN python3 -m venv /opt/vydflow-speech && /opt/vydflow-speech/bin/pip install --no-cache-dir faster-whisper==1.2.1
RUN /opt/vydflow-speech/bin/python -c "from huggingface_hub import snapshot_download; snapshot_download('Systran/faster-whisper-small', revision='536b0662742c02347bc0e980a01041f333bce120', local_dir='/opt/vydflow-speech/model', allow_patterns=['config.json','model.bin','tokenizer.json','vocabulary.*'])"
ENV HF_HUB_OFFLINE=1 HF_HUB_DISABLE_TELEMETRY=1
