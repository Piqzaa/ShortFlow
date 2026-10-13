"""Compress original portrait assets; no generative or semantic image editing."""
from pathlib import Path
from PIL import Image
root = Path(__file__).resolve().parents[1]
for source in (root / 'output/media/source').glob('*.png'):
    sequence = source.stem.startswith(('horizon-', 'edit-'))
    destination = root / 'public/media' / ('edit' if source.stem.startswith('edit-') else 'horizon' if sequence else 'creators')
    destination.mkdir(exist_ok=True)
    image = Image.open(source).convert('RGB')
    image.thumbnail((540, 960) if sequence else (720, 1280), Image.Resampling.LANCZOS)
    image.save(destination / (source.stem + '.webp'), 'WEBP', quality=80 if sequence else 82, method=6)
    image.thumbnail((135, 240) if sequence else (270, 480), Image.Resampling.LANCZOS)
    image.save(destination / (source.stem + '-thumb.webp'), 'WEBP', quality=75 if sequence else 78, method=6)
    print(source.stem + ': encoded portrait + thumbnail')
