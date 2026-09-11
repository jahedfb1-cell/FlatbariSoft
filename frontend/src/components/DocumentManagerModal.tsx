import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Modal from './Modal';
import DropZone from './DropZone';
import * as docsApi from '../api/documents';
import type { OwnerDocument, TenantDocument } from '../api/documents';
import { useLanguage } from '../context/LanguageContext';
import type { TranslationKey } from '../i18n/translations';

const inputClass =
  'w-full rounded-lg border border-brand-200 px-3 py-2 text-navy-900 outline-none focus:border-brand-500';
const labelClass = 'mb-1 block text-sm font-medium text-navy-900';

type Props =
  | { kind: 'owner'; personId: number; flatId: number; onClose: () => void }
  | { kind: 'tenant'; tenantId: number; onClose: () => void };

const ownerTypes = ['NID', 'NAMJARI', 'LAND_TAX_RECEIPT', 'OTHER'] as const;
const tenantTypes = ['NID', 'PHOTO', 'POLICE_VERIFICATION'] as const;

export default function DocumentManagerModal(props: Props) {
  const { t } = useLanguage();
  const navigate = useNavigate();
  const [documents, setDocuments] = useState<(OwnerDocument | TenantDocument)[]>([]);
  const [file, setFile] = useState<File | null>(null);
  const [documentType, setDocumentType] = useState('NID');
  const [side, setSide] = useState('FRONT');
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
          side: documentType === 'NID' ? side : undefined,
          document_year: documentType === 'LAND_TAX_RECEIPT' ? documentYear : undefined,
          file,
        });
      } else {
        await docsApi.uploadTenantDocument(props.tenantId, {
          document_type: documentType,
          side: documentType === 'NID' ? side : undefined,
          file,
        });
      }
      setFile(null);
      setDocumentYear('');
      load();
    } catch (err: any) {
      const errors = err.response?.data?.errors;
      setError(errors ? Object.values(errors).flat().join(' ') : err.response?.data?.message || t('documents.uploadFailed'));
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(id: number) {
    if (!confirm(t('documents.confirmDelete'))) return;
    if (props.kind === 'owner') {
      await docsApi.deleteOwnerDocument(id);
    } else {
      await docsApi.deleteTenantDocument(id);
    }
    load();
  }

  const typeOptions = props.kind === 'owner' ? ownerTypes : tenantTypes;
  const nidDocs = documents.filter((d) => d.document_type === 'NID');
  const nidFront = nidDocs.find((d) => d.side === 'FRONT');
  const nidBack = nidDocs.find((d) => d.side === 'BACK');

  function openNidPrint() {
    if (!nidFront || !nidBack) return;
    navigate('/print/nid', { state: { front: nidFront.file_url, back: nidBack.file_url } });
  }

  return (
    <Modal title={t('documents.title')} onClose={props.onClose}>
      <div className="space-y-4">
        <div className="max-h-48 space-y-2 overflow-y-auto">
          {documents.length === 0 && <p className="text-sm text-navy-700">{t('documents.none')}</p>}
          {documents.map((doc) => (
            <div key={doc.id} className="flex items-center justify-between rounded-lg border border-brand-100 px-3 py-2 text-sm">
              <a href={doc.file_url} target="_blank" rel="noreferrer" className="text-brand-700 hover:underline">
                {t(`docType.${doc.document_type}` as TranslationKey)}
                {doc.side ? ` — ${t(`side.${doc.side}` as TranslationKey)}` : ''}
                {'document_year' in doc && doc.document_year ? ` (${doc.document_year})` : ''}
              </a>
              <button onClick={() => handleDelete(doc.id)} className="text-status-due hover:underline">
                {t('documents.delete')}
              </button>
            </div>
          ))}
        </div>

        {nidFront && nidBack && (
          <button
            onClick={openNidPrint}
            className="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm font-medium text-brand-700 transition hover:bg-brand-100"
          >
            🖨️ {t('documents.printNid')}
          </button>
        )}

        <div className="border-t border-brand-100 pt-4">
          <label className={labelClass}>{t('documents.type')}</label>
          <select value={documentType} onChange={(e) => setDocumentType(e.target.value)} className={`${inputClass} mb-3`}>
            {typeOptions.map((type) => (
              <option key={type} value={type}>
                {t(`docType.${type}` as TranslationKey)}
              </option>
            ))}
          </select>

          {documentType === 'NID' && (
            <div className="mb-3">
              <label className={labelClass}>{t('documents.side')}</label>
              <select value={side} onChange={(e) => setSide(e.target.value)} className={inputClass}>
                <option value="FRONT" disabled={!!nidFront}>
                  {t('side.FRONT')}
                </option>
                <option value="BACK" disabled={!!nidBack}>
                  {t('side.BACK')}
                </option>
              </select>
            </div>
          )}

          {props.kind === 'owner' && documentType === 'LAND_TAX_RECEIPT' && (
            <div className="mb-3">
              <label className={labelClass}>{t('documents.year')}</label>
              <input
                type="number"
                required
                value={documentYear}
                onChange={(e) => setDocumentYear(e.target.value)}
                className={inputClass}
                placeholder="2026"
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
            {submitting ? t('documents.uploading') : t('documents.upload')}
          </button>
        </div>
      </div>
    </Modal>
  );
}
