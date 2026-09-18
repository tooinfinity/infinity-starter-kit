/* @chisel-localization */

import { router, usePage } from '@inertiajs/react';
import { Check, Globe, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { update as updateLocale } from '@/routes/locale';
import type { LocaleOption } from '@/types';

export function LanguageSelector() {
    const { locale, supportedLocales } = usePage().props;
    const [isLoading, setIsLoading] = useState(false);

    const currentLocale = supportedLocales.find(
        (l: LocaleOption) => l.code === locale,
    );

    const handleLocaleChange = (localeCode: string) => {
        if (localeCode === locale || isLoading) {
            return;
        }

        setIsLoading(true);

        router.post(
            updateLocale(),
            { locale: localeCode },
            {
                preserveScroll: true,
                onFinish: () => {
                    setIsLoading(false);
                },
            },
        );
    };

    return (
        <DropdownMenu>
            <Tooltip>
                <TooltipTrigger asChild>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="h-9 w-9"
                            disabled={isLoading}
                            aria-label={
                                currentLocale
                                    ? `${currentLocale.nativeName}`
                                    : 'Language'
                            }
                            data-test="language-selector"
                        >
                            {isLoading ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <Globe className="h-4 w-4" />
                            )}
                        </Button>
                    </DropdownMenuTrigger>
                </TooltipTrigger>
                <TooltipContent>
                    <p>{currentLocale?.nativeName ?? 'Language'}</p>
                </TooltipContent>
            </Tooltip>
            <DropdownMenuContent align="end">
                {supportedLocales.map((option: LocaleOption) => (
                    <DropdownMenuItem
                        key={option.code}
                        onClick={() => handleLocaleChange(option.code)}
                        className="cursor-pointer"
                        dir={option.direction}
                        data-test={`locale-option-${option.code}`}
                    >
                        <span className="flex-1">{option.nativeName}</span>
                        {option.code === locale && (
                            <Check className="ms-2 h-4 w-4" />
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/* @end-chisel-localization */
