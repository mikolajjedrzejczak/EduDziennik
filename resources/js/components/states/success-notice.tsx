import { CircleCheck } from 'lucide-react';
import type { ReactNode } from 'react';

export function SuccessNotice({ children }: { children: ReactNode }) {
    return (
        <div
            role="status"
            className="flex items-start gap-2 rounded-md border border-success bg-success-bg p-3 text-sm text-success"
        >
            <CircleCheck
                aria-hidden="true"
                className="mt-0.5 size-4 shrink-0"
            />
            <span>{children}</span>
        </div>
    );
}
