import client from '../api/client';

/**
 * Inside the Android app (a WebView) the page can't save a blob: URL or open a
 * new tab for a PDF - a WebView just ignores both. The app injects window.GVApp,
 * and the bytes are handed to it in chunks; it writes them to the phone's
 * Downloads folder and opens them. In an ordinary browser GVApp doesn't exist
 * and the normal blob/new-tab paths below run unchanged.
 */
interface NativeBridge {
  beginFile(name: string, mime: string): boolean;
  appendChunk(base64: string): boolean;
  endFile(): boolean;
}

function nativeBridge(): NativeBridge | null {
  return (window as unknown as { GVApp?: NativeBridge }).GVApp ?? null;
}

export function isNativeApp(): boolean {
  return nativeBridge() !== null;
}

function toBase64(bytes: Uint8Array): string {
  let binary = '';
  for (let i = 0; i < bytes.length; i += 0x8000) {
    binary += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
  }
  return btoa(binary);
}

async function saveInApp(bridge: NativeBridge, blob: Blob, filename: string) {
  const mime = blob.type || 'application/octet-stream';
  if (!bridge.beginFile(filename, mime)) throw new Error('save-failed');
  const CHUNK = 384 * 1024; // keeps each JS->Java string small (a PDF can be tens of MB)
  for (let offset = 0; offset < blob.size; offset += CHUNK) {
    const bytes = new Uint8Array(await blob.slice(offset, offset + CHUNK).arrayBuffer());
    if (!bridge.appendChunk(toBase64(bytes))) throw new Error('save-failed');
  }
  if (!bridge.endFile()) throw new Error('save-failed');
}

/**
 * PDF/Excel export routes require the Sanctum bearer token, which a plain
 * <a href> download link can't send. Fetch the file through the same
 * authenticated axios client instead, then hand the browser the bytes via
 * a throwaway object URL.
 */
export async function downloadFile(path: string, filename: string) {
  const response = await client.get(path, { responseType: 'blob' });

  const bridge = nativeBridge();
  if (bridge) {
    await saveInApp(bridge, response.data, filename);
    return;
  }

  const url = URL.createObjectURL(response.data);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

/**
 * With responseType 'blob' even a JSON error body (422 "too many invoices",
 * 404 "no invoice photo") arrives as a Blob - read the message back out of it.
 */
export async function blobErrorMessage(error: any, fallback: string): Promise<string> {
  const data = error?.response?.data;
  if (data instanceof Blob) {
    try {
      const json = JSON.parse(await data.text());
      const first = json.errors ? (Object.values(json.errors) as string[][])[0]?.[0] : null;
      return first || json.message || fallback;
    } catch {
      return fallback;
    }
  }
  return data?.message || fallback;
}

/**
 * Open a server-generated PDF in a new tab (the browser's own PDF viewer has
 * the Print button). The tab is opened synchronously, inside the click, because
 * popup blockers refuse a window.open() that happens after an await.
 * In the Android app there are no tabs: the PDF is saved to Downloads and opened.
 */
export async function openPdf(path: string, params?: Record<string, string>, filename = 'document.pdf') {
  const bridge = nativeBridge();
  if (bridge) {
    const response = await client.get(path, { responseType: 'blob', params });
    await saveInApp(bridge, response.data, filename);
    return;
  }

  const tab = window.open('', '_blank');
  try {
    const response = await client.get(path, { responseType: 'blob', params });
    const url = URL.createObjectURL(response.data);
    if (tab) {
      tab.location.href = url;
    } else {
      window.location.href = url; // popups blocked entirely: fall back to this tab
    }
  } catch (error) {
    tab?.close();
    throw error;
  }
}
