/**
 * Shrinks a phone photo in the browser BEFORE it is uploaded - the same idea
 * as Facebook/WhatsApp: a 5-12 MB camera JPEG becomes a few hundred KB, so it
 * uploads fast on mobile data and fits the server's upload limit.
 *
 * Mirrors the server's InvoiceImageService (max 2000 px long edge, JPEG,
 * quality stepped down until it fits ~350 KB). The server still re-compresses
 * everything it receives and never trusts this - this step only saves
 * bandwidth and gives the user instant feedback.
 */
const MAX_EDGE = 2000;
const TARGET_BYTES = 350 * 1024;
const START_QUALITY = 0.82;
const MIN_QUALITY = 0.5;

export interface CompressedImage {
  file: File;
  originalBytes: number;
  compressedBytes: number;
}

export function isImageFile(file: File): boolean {
  return file.type.startsWith('image/');
}

export function formatSize(bytes: number): string {
  return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/** Decode with EXIF rotation applied, so a portrait phone photo is not drawn sideways. */
async function decode(file: File): Promise<{ source: CanvasImageSource; width: number; height: number; release: () => void }> {
  if (typeof createImageBitmap === 'function') {
    try {
      const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
      return { source: bitmap, width: bitmap.width, height: bitmap.height, release: () => bitmap.close() };
    } catch {
      // fall through to the <img> path (older browsers, odd formats)
    }
  }

  const url = URL.createObjectURL(file);
  const img = new Image();
  await new Promise<void>((resolve, reject) => {
    img.onload = () => resolve();
    img.onerror = () => reject(new Error('decode-failed'));
    img.src = url;
  });
  // Browsers that lack createImageBitmap orient <img> elements by EXIF on their own.
  return { source: img, width: img.naturalWidth, height: img.naturalHeight, release: () => URL.revokeObjectURL(url) };
}

function scaled(source: CanvasImageSource, width: number, height: number, maxEdge: number): HTMLCanvasElement {
  const scale = Math.min(1, maxEdge / Math.max(width, height));
  const canvas = document.createElement('canvas');
  canvas.width = Math.max(1, Math.round(width * scale));
  canvas.height = Math.max(1, Math.round(height * scale));
  const ctx = canvas.getContext('2d')!;
  ctx.fillStyle = '#fff'; // JPEG has no transparency - flatten PNGs onto white, not black
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  ctx.drawImage(source, 0, 0, canvas.width, canvas.height);
  return canvas;
}

function encode(canvas: HTMLCanvasElement, quality: number): Promise<Blob> {
  return new Promise((resolve, reject) =>
    canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('encode-failed'))), 'image/jpeg', quality),
  );
}

export async function compressImage(file: File): Promise<CompressedImage> {
  const decoded = await decode(file);
  try {
    const base = scaled(decoded.source, decoded.width, decoded.height, MAX_EDGE);

    let canvas = base;
    let best: Blob | null = null;
    // Same ladder as the server: every quality step, then shrink 15% and retry.
    for (let round = 0; round < 4; round++) {
      for (let quality = START_QUALITY; quality >= MIN_QUALITY - 1e-9; quality -= 0.06) {
        best = await encode(canvas, quality);
        if (best.size <= TARGET_BYTES) {
          return done(file, best);
        }
      }
      canvas = scaled(base, base.width, base.height, Math.max(base.width, base.height) * 0.85 ** (round + 1));
    }
    return done(file, best!);
  } finally {
    decoded.release();
  }
}

function done(original: File, blob: Blob): CompressedImage {
  // An already-tiny file can come out larger after re-encoding - keep whichever is smaller.
  if (blob.size >= original.size && original.type === 'image/jpeg') {
    return { file: original, originalBytes: original.size, compressedBytes: original.size };
  }
  const name = original.name.replace(/\.[^.]+$/, '') || 'invoice';
  return {
    file: new File([blob], `${name}.jpg`, { type: 'image/jpeg' }),
    originalBytes: original.size,
    compressedBytes: blob.size,
  };
}
