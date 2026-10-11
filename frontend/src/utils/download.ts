import client from '../api/client';

/**
 * PDF/Excel export routes require the Sanctum bearer token, which a plain
 * <a href> download link can't send. Fetch the file through the same
 * authenticated axios client instead, then hand the browser the bytes via
 * a throwaway object URL.
 */
export async function downloadFile(path: string, filename: string) {
  const response = await client.get(path, { responseType: 'blob' });
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
 */
export async function openPdf(path: string, params?: Record<string, string>) {
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
