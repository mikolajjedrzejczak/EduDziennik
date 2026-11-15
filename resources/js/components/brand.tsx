import { cn } from '@/lib/utils';

export function BrandMark({ className }: { className?: string }) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                'grid size-7 shrink-0 grid-cols-2 gap-0.5',
                className,
            )}
        >
            <i className="rounded-[3px] bg-current" />
            <i className="rounded-[3px] bg-current opacity-40" />
            <i className="rounded-[3px] bg-current opacity-40" />
            <i className="rounded-[3px] bg-form-focus" />
        </span>
    );
}

export function Brand() {
    return (
        <div className="flex items-center gap-2.5 font-semibold">
            <BrandMark />
            <span className="text-[15px] leading-[22px]">EduDziennik</span>
        </div>
    );
}
