import { useForm } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { FormEvent, useEffect } from 'react';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { FileDropzone } from '@/Components/ui/FileDropzone';
import { Modal } from '@/Components/ui/Modal';
import { InfoTip } from '@/Components/ui/InfoTip';

interface ImportModalProps {
    open: boolean;
    onClose: () => void;
    /** URL the file is POSTed to (as `file`). */
    action: string;
    /** URL of the downloadable starter workbook. */
    templateHref: string;
    requiredColumns: string[];
    optionalColumns: string[];
    /** Short rules shown under the column badges. */
    notes: string[];
    title?: string;
}

/**
 * Upload-a-spreadsheet dialog shared by every Excel import: template link,
 * drag-and-drop file box, the expected columns, and the server's row errors.
 */
export function ImportModal({
    open,
    onClose,
    action,
    templateHref,
    requiredColumns,
    optionalColumns,
    notes,
    title = 'Import from Excel',
}: ImportModalProps) {
    const form = useForm<{ file: File | null }>({ file: null });

    useEffect(() => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
        // Reset only when the dialog is (re)opened.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(action, { forceFormData: true, onSuccess: onClose });
    }

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={title}
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={submit} loading={form.processing} disabled={!form.data.file}>
                        Import
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="d-flex flex-column gap-3">
                <a
                    href={templateHref}
                    className="d-flex align-items-center gap-3 text-decoration-none"
                    style={{
                        padding: '12px 14px',
                        borderRadius: 'var(--af-radius-sm)',
                        border: '1px solid var(--af-border)',
                        color: 'var(--af-text)',
                    }}
                >
                    <Download size={18} style={{ color: 'var(--af-primary)', flexShrink: 0 }} />
                    <div>
                        <div style={{ fontSize: '14px', fontWeight: 500 }}>Download template</div>
                        <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>Start from a ready-made sheet with the right columns.</div>
                    </div>
                </a>

                <div>
                    <label className="d-block mb-1" style={{ fontSize: '13px', color: 'var(--af-label)' }}>
                        Spreadsheet <InfoTip>Upload an Excel or CSV file. Use the template link to get the right columns.</InfoTip>
                    </label>
                    <FileDropzone
                        file={form.data.file}
                        onChange={(file) => form.setData('file', file)}
                        accept=".xlsx,.xls,.csv"
                        hint=".xlsx, .xls or .csv"
                        error={Boolean(form.errors.file)}
                    />
                </div>

                {form.errors.file && (
                    <div style={{ color: 'var(--af-danger)', fontSize: '12px', whiteSpace: 'pre-line', maxHeight: '220px', overflowY: 'auto' }}>
                        {form.errors.file}
                    </div>
                )}

                <div style={{ fontSize: '12px', color: 'var(--af-label)' }}>
                    <div className="d-flex flex-wrap align-items-center gap-1 mb-1">
                        <span className="me-1">Required:</span>
                        {requiredColumns.map((column) => (
                            <Badge key={column} variant="info">
                                {column}
                            </Badge>
                        ))}
                    </div>
                    <div className="d-flex flex-wrap align-items-center gap-1 mb-2">
                        <span className="me-1">Optional:</span>
                        {optionalColumns.map((column) => (
                            <Badge key={column} variant="neutral">
                                {column}
                            </Badge>
                        ))}
                    </div>
                    <ul className="mb-0 ps-3">
                        {notes.map((note) => (
                            <li key={note}>{note}</li>
                        ))}
                    </ul>
                </div>
            </form>
        </Modal>
    );
}
