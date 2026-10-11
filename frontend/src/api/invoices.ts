import client from './client';
import { openPdf } from '../utils/download';

export type InvoiceType = 'gas' | 'materials';

const base = (type: InvoiceType, id: number) =>
  type === 'gas' ? `/gas/purchases/${id}/invoice` : `/construction-materials/${id}/invoice`;

/** The stored photo as an object URL (the route is private, so <img src> cannot call it directly). */
export async function fetchInvoiceImageUrl(type: InvoiceType, id: number): Promise<string> {
  const response = await client.get(base(type, id), { responseType: 'blob' });
  return URL.createObjectURL(response.data);
}

/** Attach the photo to an existing purchase, or replace the current one. */
export function uploadInvoice(type: InvoiceType, id: number, file: File) {
  const form = new FormData();
  form.append('invoice_image', file);
  return client.post(base(type, id), form).then((r) => r.data);
}

/** One invoice as a black-and-white A4 PDF. */
export function printInvoice(type: InvoiceType, id: number) {
  return openPdf(`${base(type, id)}/print`);
}

/** Every purchase invoice in a date range as one black-and-white A4 PDF, for an audit. */
export function printAuditPack(from: string, to: string, type: 'all' | InvoiceType) {
  return openPdf('/invoices/audit-pack', { from, to, type });
}

/** Build multipart form data from a plain object, skipping empty values and passing files through. */
export function toFormData(data: Record<string, string | number | File | null | undefined>): FormData {
  const form = new FormData();
  Object.entries(data).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      form.append(key, value instanceof File ? value : String(value));
    }
  });
  return form;
}
