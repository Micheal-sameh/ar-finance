import { FileSpreadsheet, UploadCloud, X } from 'lucide-react';
import { DragEvent, useRef, useState } from 'react';

interface FileDropzoneProps {
    file: File | null;
    onChange: (file: File | null) => void;
    accept?: string;
    hint?: string;
    error?: boolean;
}

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * Click-or-drop file picker. Shows the chosen file (name and size) with a
 * remove button instead of the browser's bare "No file chosen" input.
 */
export function FileDropzone({ file, onChange, accept, hint, error = false }: FileDropzoneProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);

    function handleDrop(e: DragEvent<HTMLDivElement>) {
        e.preventDefault();
        setDragging(false);
        onChange(e.dataTransfer.files?.[0] ?? null);
    }

    function clear() {
        onChange(null);
        if (inputRef.current) inputRef.current.value = '';
    }

    return (
        <div
            role="button"
            tabIndex={0}
            onClick={() => !file && inputRef.current?.click()}
            onKeyDown={(e) => {
                if (!file && (e.key === 'Enter' || e.key === ' ')) {
                    e.preventDefault();
                    inputRef.current?.click();
                }
            }}
            onDragOver={(e) => {
                e.preventDefault();
                setDragging(true);
            }}
            onDragLeave={() => setDragging(false)}
            onDrop={handleDrop}
            className="d-flex align-items-center justify-content-center text-center"
            style={{
                minHeight: '120px',
                padding: '20px',
                cursor: file ? 'default' : 'pointer',
                borderRadius: 'var(--af-radius-sm)',
                border: `2px dashed ${error ? 'var(--af-danger)' : dragging ? 'var(--af-primary)' : 'var(--af-border)'}`,
                backgroundColor: dragging ? '#EFF4FE' : 'var(--af-surface)',
                transition: 'border-color 0.15s, background-color 0.15s',
            }}
        >
            <input
                ref={inputRef}
                type="file"
                accept={accept}
                className="d-none"
                onChange={(e) => onChange(e.target.files?.[0] ?? null)}
            />

            {file ? (
                <div className="d-flex align-items-center gap-3 w-100 text-start">
                    <FileSpreadsheet size={28} style={{ color: 'var(--af-primary)', flexShrink: 0 }} />
                    <div className="flex-grow-1" style={{ minWidth: 0 }}>
                        <div className="text-truncate" style={{ fontSize: '14px', fontWeight: 500, color: 'var(--af-text)' }}>
                            {file.name}
                        </div>
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>{formatSize(file.size)}</div>
                    </div>
                    <button type="button" className="btn btn-sm p-1" style={{ color: 'var(--af-label)' }} onClick={clear} aria-label="Remove file">
                        <X size={18} />
                    </button>
                </div>
            ) : (
                <div className="d-flex flex-column align-items-center gap-1">
                    <UploadCloud size={28} style={{ color: 'var(--af-primary)' }} />
                    <div style={{ fontSize: '14px', color: 'var(--af-text)' }}>
                        <span style={{ color: 'var(--af-primary)', fontWeight: 500 }}>Click to choose a file</span> or drag it here
                    </div>
                    {hint && <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>{hint}</div>}
                </div>
            )}
        </div>
    );
}
