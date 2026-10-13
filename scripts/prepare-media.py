"""Compress original portrait assets; no generative or semantic image editing."""
from pathlib import Path
from PIL import Image
root = Path(__file__).resolve().parents[1]
for source in (root / 'output/media/source').glob('*.png'):
    image = Image.open(source).convert('RGB')
    image.thumbnail((720, 1280), Image.Resampling.LANCZOS)
    image.save(root / 'public/media/creators' / (source.stem + '.webp'), 'WEBP', quality=82, method=6)
    image.thumbnail((270, 480), Image.Resampling.LANCZOS)
    image.save(root / 'public/media/creators' / (source.stem + '-thumb.webp'), 'WEBP', quality=78, method=6)
    print(source.stem + ': encoded portrait + thumbnail')
