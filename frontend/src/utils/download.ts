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
 * The name the server gave the file (Content-Disposition), e.g. "10A-Nazrul Islam-Service Charge Sep 2026.pdf".
 * The server is the one that knows the flat, the owner and what the money was for, so its name wins; the name the
 * screen passed in is only the fallback (a cross-origin dev setup does not expose the header).
 */
export function serverFileName(response: { headers?: unknown }, fallback: string): string {
  const headers = (response.headers ?? {}) as Record<string, unknown> & { get?: (k: string) => unknown };
  const raw = String((typeof headers.get === 'function' ? headers.get('content-disposition') : headers['content-disposition']) ?? '');
  const star = /filename\*\s*=\s*UTF-8''([^;]+)/i.exec(raw);
  if (star) {
    try {
      return decodeURIComponent(star[1].trim());
    } catch {
      /* fall through to the plain name */
    }
  }
  const plain = /filename\s*=\s*"?([^";]+)"?/i.exec(raw);
  return plain ? plain[1].trim() : fallback;
}

/**
 * PDF/Excel export routes require the Sanctum bearer token, which a plain
 * <a href> download link can't send. Fetch the file through the same
 * authenticated axios client instead, then hand the browser the bytes via
 * a throwaway object URL.
 */
export async function downloadFile(path: string, filename: string) {
  const response = await client.get(path, { responseType: 'blob' });
  filename = serverFileName(response, filename);

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
    await saveInApp(bridge, response.data, serverFileName(response, filename));
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

/**
 * Runs `action` (taking a payment, approving one) and then shows the PDF it produced, e.g. the money receipt.
 * The tab is opened at once, while the click still counts for the browser's popup blocker, and filled in when the PDF
 * is ready. If the action fails the tab is closed again and the error goes to the caller. If only the PDF fails, the
 * action still stands (the receipt can be printed again from its list) and the result is returned as normal.
 */
export async function openPdfAfter<T>(action: () => Promise<T>, pdf: (result: T) => { path: string; filename: string } | null): Promise<T> {
  const bridge = nativeBridge();
  const tab = bridge ? null : window.open('', '_blank');
  let result: T;
  try {
    result = await action();
  } catch (error) {
    tab?.close();
    throw error;
  }
  try {
    const target = pdf(result);
    if (!target) {
      tab?.close();
      return result;
    }
    const response = await client.get(target.path, { responseType: 'blob' });
    const saveAs = serverFileName(response, target.filename);
    if (bridge) {
      await saveInApp(bridge, response.data, saveAs);
    } else {
      const url = URL.createObjectURL(response.data);
      if (tab) {
        tab.location.href = url;
      } else {
        const link = document.createElement('a'); // popups blocked: save the file instead of leaving this page
        link.href = url;
        link.download = saveAs;
        document.body.appendChild(link);
        link.click();
        link.remove();
      }
    }
  } catch {
    tab?.close();
  }
  return result;
}
