import { useRef, useState, type DragEvent } from 'react';

/**
 * Drag-drop file picker with an image/PDF thumbnail preview.
 * Accepts the same file types the backend allows: jpg, jpeg, png, pdf (max 5MB).
 */
export default function DropZone({ file, onChange }: { file: File | null; onChange: (file: File | null) => void }) {
  const [dragOver, setDragOver] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  const previewUrl = file && file.type.startsWith('image/') ? URL.createObjectURL(file) : null;

  function handleDrop(e: DragEvent) {
    e.preventDefault();
    setDragOver(false);
    const dropped = e.dataTransfer.files[0];
    if (dropped) onChange(dropped);
  }

  return (
    <div
      onDragOver={(e) => {
        e.preventDefault();
        setDragOver(true);
      }}
      onDragLeave={() => setDragOver(false)}
      onDrop={handleDrop}
      onClick={() => inputRef.current?.click()}
      className={`flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-4 py-6 text-center transition ${
        dragOver ? 'border-brand-500 bg-brand-100' : 'border-brand-200 bg-brand-50 hover:bg-brand-100'
      }`}
    >
      <input
        ref={inputRef}
        type="file"
        accept=".jpg,.jpeg,.png,.pdf"
        className="hidden"
        onChange={(e) => onChange(e.target.files?.[0] ?? null)}
      />
      {previewUrl && <img src={previewUrl} alt="প্রিভিউ" className="mb-2 h-24 w-24 rounded-lg object-cover" />}
      {file && !previewUrl && (
        <div className="mb-2 flex h-24 w-24 items-center justify-center rounded-lg bg-brand-200 text-3xl">📄</div>
      )}
      {file ? (
        <p className="text-sm text-navy-900">{file.name}</p>
      ) : (
        <>
          <p className="text-sm font-medium text-navy-900">ফাইল টেনে আনুন অথবা ক্লিক করুন</p>
          <p className="mt-1 text-xs text-navy-700">jpg, png, pdf (সর্বোচ্চ 5MB)</p>
        </>
      )}
    </div>
  );
}
