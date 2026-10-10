import type { ReactNode } from 'react';
import { Button } from './Button';
import { Modal } from './Modal';

export function ConfirmDialog({ open, onClose, onConfirm, title, children, confirmLabel = 'Onayla', danger = true, loading = false }: {
    open: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    children: ReactNode;
    confirmLabel?: string;
    danger?: boolean;
    loading?: boolean;
}) {
    return (
        <Modal open={open} onClose={onClose} title={title} size="sm"
            footer={<><Button variant="secondary" onClick={onClose}>Vazgeç</Button><Button variant={danger ? 'danger' : 'primary'} onClick={onConfirm} loading={loading}>{confirmLabel}</Button></>}>
            <div className="text-sm text-slate-300">{children}</div>
        </Modal>
    );
}
