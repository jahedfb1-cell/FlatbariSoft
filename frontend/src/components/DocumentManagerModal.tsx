import { useEffect, useState } from 'react';
import Modal from './Modal';
import DropZone from './DropZone';
import * as docsApi from '../api/documents';
import type { OwnerDocument, TenantDocument } from '../api/documents';

const inputClass =
  'w-full rounded-lg border border-brand-200 px-3 py-2 text-navy-900 outline-none focus:border-brand-500';
const labelClass = 'mb-1 block text-sm font-medium text-navy-900';

type Props =
  | { kind: 'owner'; personId: number; flatId: number; onClose: () => void }
  | { kind: 'tenant'; tenantId: number; onClose: () => void };

const ownerTypeLabel: Record<string, string> = {
  NID: 'জাতীয় পরিচয়পত্র',
  NAMJARI: 'নামজারি',
  LAND_TAX_RECEIPT: 'জমির খাজনা রসিদ',
  OTHER: 'অন্যান্য',
};

const tenantTypeLabel: Record<string, string> = {
  NID: 'জাতীয় পরিচয়পত্র',
  PHOTO: 'ছবি',
  POLICE_VERIFICATION: 'পুলিশ ভেরিফিকেশন',
};

export default function DocumentManagerModal(props: Props) {
  const [documents, setDocuments] = useState<(OwnerDocument | TenantDocument)[]>([]);
  const [file, setFile] = useState<File | null>(null);
  const [documentType, setDocumentType] = useState(props.kind === 'owner' ? 'NID' : 'NID');
  const [documentYear, setDocumentYear] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  function load() {
    if (props.kind === 'owner') {
      docsApi.listOwnerDocuments(props.personId).then(setDocuments);
    } else {
      docsApi.listTenantDocuments(props.tenantId).then(setDocuments);
    }
  }

  useEffect(load, []); // eslint-disable-line react-hooks/exhaustive-deps

  async function handleUpload() {
    if (!file) return;
    setError(null);
    setSubmitting(true);
    try {
      if (props.kind === 'owner') {
        await docsApi.uploadOwnerDocument(props.personId, {
          flat_id: props.flatId,
          document_type: documentType,
          document_year: documentType === 'LAND_TAX_RECEIPT' ? documentYear : undefined,
          file,
        });
      } else {
        await docsApi.uploadTenantDocument(props.tenantId, { document_type: documentType, file });
      }
      setFile(null);
      setDocumentYear('');
      load();
    } catch (err: any) {
      const errors = err.response?.data?.errors;
      setError(errors ? Object.values(errors).flat().join(' ') : err.response?.data?.message || 'আপলোড ব্যর্থ হয়েছে।');
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(id: number) {
    if (!confirm('এই ডকুমেন্ট মুছে ফেলবেন?')) return;
    if (props.kind === 'owner') {
      await docsApi.deleteOwnerDocument(id);
    } else {
      await docsApi.deleteTenantDocument(id);
    }
    load();
  }

  const typeLabel = props.kind === 'owner' ? ownerTypeLabel : tenantTypeLabel;
  const typeOptions = Object.keys(typeLabel);

  return (
    <Modal title="ডকুমেন্ট" onClose={props.onClose}>
      <div className="space-y-4">
        <div className="max-h-48 space-y-2 overflow-y-auto">
          {documents.length === 0 && <p className="text-sm text-navy-700">কোনো ডকুমেন্ট আপলোড করা হয়নি।</p>}
          {documents.map((doc) => (
            <div key={doc.id} className="flex items-center justify-between rounded-lg border border-brand-100 px-3 py-2 text-sm">
              <a href={doc.file_url} target="_blank" rel="noreferrer" className="text-brand-700 hover:underline">
                {typeLabel[doc.document_type] ?? doc.document_type}
                {'document_year' in doc && doc.document_year ? ` (${doc.document_year})` : ''}
              </a>
              <button onClick={() => handleDelete(doc.id)} className="text-status-due hover:underline">
                মুছুন
              </button>
            </div>
          ))}
        </div>

        <div className="border-t border-brand-100 pt-4">
          <label className={labelClass}>ধরন</label>
          <select value={documentType} onChange={(e) => setDocumentType(e.target.value)} className={`${inputClass} mb-3`}>
            {typeOptions.map((type) => (
              <option key={type} value={type}>
                {typeLabel[type]}
              </option>
            ))}
          </select>

          {props.kind === 'owner' && documentType === 'LAND_TAX_RECEIPT' && (
            <div className="mb-3">
              <label className={labelClass}>সাল</label>
              <input
                type="number"
                required
                value={documentYear}
                onChange={(e) => setDocumentYear(e.target.value)}
                className={inputClass}
                placeholder="২০২৬"
              />
            </div>
          )}

          <DropZone file={file} onChange={setFile} />

          {error && <p className="mt-2 text-sm text-status-due">{error}</p>}

          <button
            onClick={handleUpload}
            disabled={!file || submitting}
            className="mt-3 w-full rounded-lg bg-brand-600 px-4 py-2 font-medium text-white transition hover:bg-brand-700 disabled:opacity-50"
          >
            {submitting ? 'আপলোড হচ্ছে...' : 'আপলোড করুন'}
          </button>
        </div>
      </div>
    </Modal>
  );
}
