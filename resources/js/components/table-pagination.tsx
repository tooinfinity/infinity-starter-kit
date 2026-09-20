import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

interface TablePaginationProps {
    links?: PaginationLink[];
    from?: number | null;
    to?: number | null;
    total?: number;
    itemName?: string;
    className?: string;
}

export function TablePagination({
    links = [],
    from = 0,
    to = 0,
    total = 0,
    itemName = 'records',
    className,
}: TablePaginationProps) {
    if (!links || links.length <= 3) {
        return null;
    }

    const previousLink = links[0];
    const nextLink = links[links.length - 1];
    const pageLinks = links.slice(1, -1);

    return (
        <div
            className={cn(
                'flex flex-col gap-3 border-t bg-muted/20 px-4 py-3 sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            <div className="text-xs text-muted-foreground">
                Showing{' '}
                <span className="font-medium text-foreground">{from ?? 0}</span>{' '}
                to{' '}
                <span className="font-medium text-foreground">{to ?? 0}</span>{' '}
                of{' '}
                <span className="font-medium text-foreground">
                    {total ?? 0}
                </span>{' '}
                {itemName}
            </div>

            <div className="flex items-center gap-1 self-center sm:self-auto">
                {/* Previous Button */}
                {previousLink && (
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!previousLink.url}
                        asChild={Boolean(previousLink.url)}
                        className="h-8 gap-1 px-2.5 text-xs"
                        aria-label="Go to previous page"
                    >
                        {previousLink.url ? (
                            <Link
                                href={previousLink.url}
                                preserveScroll
                                preserveState
                            >
                                <ChevronLeft className="size-3.5" />
                                <span className="hidden sm:inline">
                                    Previous
                                </span>
                            </Link>
                        ) : (
                            <span>
                                <ChevronLeft className="size-3.5" />
                                <span className="hidden sm:inline">
                                    Previous
                                </span>
                            </span>
                        )}
                    </Button>
                )}

                {/* Page Number Buttons */}
                <div className="flex items-center gap-1">
                    {pageLinks.map((link, index) => {
                        const isEllipsis =
                            link.label === '...' || link.label === '&hellip;';

                        if (isEllipsis) {
                            return (
                                <span
                                    key={index}
                                    className="px-2 text-xs text-muted-foreground"
                                >
                                    …
                                </span>
                            );
                        }

                        return (
                            <Button
                                key={index}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={!link.url}
                                asChild={Boolean(link.url)}
                                className="h-8 min-w-8 px-2.5 text-xs"
                                aria-current={link.active ? 'page' : undefined}
                            >
                                {link.url ? (
                                    <Link
                                        href={link.url}
                                        preserveScroll
                                        preserveState
                                    >
                                        {link.label}
                                    </Link>
                                ) : (
                                    <span>{link.label}</span>
                                )}
                            </Button>
                        );
                    })}
                </div>

                {/* Next Button */}
                {nextLink && (
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!nextLink.url}
                        asChild={Boolean(nextLink.url)}
                        className="h-8 gap-1 px-2.5 text-xs"
                        aria-label="Go to next page"
                    >
                        {nextLink.url ? (
                            <Link
                                href={nextLink.url}
                                preserveScroll
                                preserveState
                            >
                                <span className="hidden sm:inline">Next</span>
                                <ChevronRight className="size-3.5" />
                            </Link>
                        ) : (
                            <span>
                                <span className="hidden sm:inline">Next</span>
                                <ChevronRight className="size-3.5" />
                            </span>
                        )}
                    </Button>
                )}
            </div>
        </div>
    );
}
