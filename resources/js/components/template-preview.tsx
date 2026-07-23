import { CornerDownLeft, ExternalLink, Phone } from 'lucide-react';
import { cn } from '@/lib/utils';

interface TemplatePreviewProps {
    name: string;
    language?: string;
    category?: string;
    headerType?: 'TEXT' | 'NONE';
    headerText?: string;
    bodyText?: string;
    footerText?: string;
    buttons?: Array<{
        type: 'QUICK_REPLY' | 'PHONE_NUMBER' | 'URL';
        text: string;
        phone_number?: string;
        url?: string;
    }>;
    variables?: Record<string, string>;
    className?: string;
}

export function TemplatePreview({
    language,
    category,
    headerType = 'NONE',
    headerText = '',
    bodyText = '',
    footerText = '',
    buttons = [],
    variables = {},
    className,
}: TemplatePreviewProps) {
    // Helper to replace variables in text
    const replaceVariables = (text: string) => {
        if (!text) {
            return '';
        }

        let result = text;
        Object.entries(variables).forEach(([key, val]) => {
            result = result.replaceAll(`{{${key}}}`, val || `[${key}]`);
        });

        // For unfilled variables, highlight them
        return result.split(/(\{\{\d+\}\})/).map((part, index) => {
            if (part.startsWith('{{') && part.endsWith('}}')) {
                const varNum = part.slice(2, -2);
                const customVal = variables[varNum];

                return (
                    <span
                        key={index}
                        className="inline-block rounded border border-emerald-300/30 bg-emerald-100 px-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                    >
                        {customVal || `{{${varNum}}}`}
                    </span>
                );
            }

            return part;
        });
    };

    return (
        <div
            className={cn(
                'relative mx-auto flex w-full max-w-sm flex-col items-center justify-center overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 p-6 dark:border-zinc-800 dark:bg-zinc-950',
                className,
            )}
        >
            {/* Header info */}
            <div className="absolute top-2 left-3 flex gap-2">
                {category && (
                    <span className="rounded bg-zinc-200 px-1.5 py-0.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase dark:bg-zinc-800">
                        {category}
                    </span>
                )}
                {language && (
                    <span className="rounded bg-zinc-200 px-1.5 py-0.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase dark:bg-zinc-800">
                        {language}
                    </span>
                )}
            </div>

            {/* Chat background simulation */}
            <div className="mt-4 flex w-full flex-col space-y-1.5 rounded-lg border border-[#e1d9cd] bg-[#efeae2] p-4 shadow-inner dark:border-zinc-900 dark:bg-[#0b141a]">
                {/* Chat Bubble */}
                <div className="relative max-w-[90%] self-start rounded-lg rounded-tl-none border border-t-0 border-zinc-200/50 bg-white p-3 text-left text-foreground shadow-sm dark:border-zinc-800/20 dark:bg-[#1f2c34]">
                    {/* Header */}
                    {headerType === 'TEXT' && headerText && (
                        <div className="mb-1 text-sm font-bold text-foreground">
                            {replaceVariables(headerText)}
                        </div>
                    )}

                    {/* Body */}
                    <div className="text-sm leading-relaxed whitespace-pre-wrap text-foreground">
                        {bodyText ? (
                            replaceVariables(bodyText)
                        ) : (
                            <span className="text-zinc-400 italic">
                                Enter template body text...
                            </span>
                        )}
                    </div>

                    {/* Footer */}
                    {footerText && (
                        <div className="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400">
                            {replaceVariables(footerText)}
                        </div>
                    )}

                    {/* Time Stamp */}
                    <div className="mt-1 text-right font-mono text-[9px] text-zinc-400 dark:text-zinc-500">
                        {new Date().toLocaleTimeString([], {
                            hour: '2-digit',
                            minute: '2-digit',
                        })}
                    </div>
                </div>

                {/* Buttons (drawn as separate elements below/attached to the bubble, like standard WhatsApp Templates) */}
                {buttons && buttons.length > 0 && (
                    <div className="flex w-full max-w-[90%] flex-col gap-1 self-start">
                        {buttons.map((btn, index) => (
                            <div
                                key={index}
                                className="flex cursor-pointer items-center justify-center rounded-lg border border-zinc-200/50 bg-white px-3 py-2 text-center text-xs font-semibold text-sky-600 shadow-sm transition-colors hover:bg-zinc-50 dark:border-zinc-800/20 dark:bg-[#1f2c34] dark:text-sky-400 dark:hover:bg-zinc-800/50"
                            >
                                {btn.type === 'QUICK_REPLY' && (
                                    <CornerDownLeft className="mr-1.5 h-3 w-3 shrink-0" />
                                )}
                                {btn.type === 'PHONE_NUMBER' && (
                                    <Phone className="mr-1.5 h-3 w-3 shrink-0" />
                                )}
                                {btn.type === 'URL' && (
                                    <ExternalLink className="mr-1.5 h-3 w-3 shrink-0" />
                                )}
                                {btn.text || 'Button'}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
