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
