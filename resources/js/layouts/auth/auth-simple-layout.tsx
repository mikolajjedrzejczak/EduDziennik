import { GraduationCap, Sheet, ShieldCheck } from 'lucide-react';
import type { AuthLayoutProps } from '@/types';

function Brand() {
    return (
        <div className="flex items-center gap-2.5 font-semibold">
            <span
                aria-hidden="true"
                className="grid size-7 shrink-0 grid-cols-2 gap-0.5"
            >
                <i className="rounded-[3px] bg-current" />
                <i className="rounded-[3px] bg-current opacity-40" />
                <i className="rounded-[3px] bg-current opacity-40" />
                <i className="rounded-[3px] bg-form-focus" />
            </span>
            <span className="text-[15px] leading-[22px]">EduDziennik</span>
        </div>
    );
}

const roles = [
    {
        icon: GraduationCap,
        name: 'Uczeń',
        description: 'sprawdza oceny, średnie i historię zmian',
    },
    {
        icon: Sheet,
        name: 'Nauczyciel',
        description: 'prowadzi dziennik swoich klas i przedmiotów',
    },
    {
        icon: ShieldCheck,
        name: 'Administrator',
        description: 'zarządza kontami, klasami i przydziałami',
    },
];

export default function AuthSimpleLayout({ children }: AuthLayoutProps) {
    return (
        <div className="login-shell flex min-h-svh bg-form-surface font-form text-form-text">
            <aside className="hidden w-[39%] max-w-[560px] shrink-0 flex-col bg-auth-nav px-8 py-12 text-white md:flex xl:px-14">
                <Brand />
                <div className="flex-1" />
                <h2 className="mt-12 text-[32px] leading-10 font-semibold tracking-[-0.192px]">
                    Elektroniczny dziennik lekcyjny
                </h2>
                <p className="mt-4 text-base leading-6 text-auth-muted">
                    Oceny, średnie i pełna historia zmian w jednym miejscu.
                    Każda zmiana oceny jest zapisywana z autorem i datą.
                </p>
                <div className="py-10">
                    {roles.map(({ icon: Icon, name, description }) => (
                        <div
                            key={name}
                            className="flex items-center gap-3.5 border-t border-auth-divider py-3.5"
                        >
                            <Icon
                                aria-hidden="true"
                                className="size-5 shrink-0 text-auth-muted"
                            />
                            <div>
                                <p className="text-sm leading-5 font-medium">
                                    {name}
                                </p>
                                <p className="text-[13px] leading-[18px] text-auth-muted">
                                    {description}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
                <p className="text-xs leading-4 text-auth-muted">
                    LO nr 4 im. Mikołaja Kopernika w Krakowie
                </p>
            </aside>
            <main className="flex min-w-0 flex-1 flex-col px-5 pt-8 pb-8 md:items-center md:justify-center md:p-10">
                <div className="mb-5 space-y-1 pt-2 pb-4 md:hidden">
                    <Brand />
                    <p className="text-xs leading-4 text-form-muted">
                        LO nr 4 im. M. Kopernika w Krakowie
                    </p>
                </div>
                <div className="flex w-full flex-1 flex-col md:max-w-[400px] md:flex-none">
                    {children}
                </div>
            </main>
        </div>
    );
}
