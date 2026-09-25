import { BarChart2, Table as TableIcon } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { ReportTimeSeriesPoint } from '@/types/reports';

interface ReportChartProps {
    title: string;
    description: string;
    series: ReportTimeSeriesPoint[];
    metricLabel?: string;
}

export function ReportChart({
    title,
    description,
    series,
    metricLabel = 'Count',
}: ReportChartProps) {
    const [viewMode, setViewMode] = useState<'chart' | 'table'>('chart');

    const totalValue = series.reduce((acc, point) => acc + point.value, 0);
    const maxValue = Math.max(...series.map((point) => point.value), 1);

    return (
        <Card className="border-sidebar-border/70 dark:border-sidebar-border">
            <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <CardTitle className="text-base font-semibold">
                        {title}
                    </CardTitle>
                    <CardDescription>{description}</CardDescription>
                </div>
                <div className="flex items-center gap-1">
                    <Button
                        variant={viewMode === 'chart' ? 'secondary' : 'ghost'}
                        size="sm"
                        onClick={() => setViewMode('chart')}
                        className="h-8 gap-1.5 text-xs"
                        aria-label="Chart view"
                    >
                        <BarChart2 className="size-3.5" />
                        <span className="hidden sm:inline">Chart</span>
                    </Button>
                    <Button
                        variant={viewMode === 'table' ? 'secondary' : 'ghost'}
                        size="sm"
                        onClick={() => setViewMode('table')}
                        className="h-8 gap-1.5 text-xs"
                        aria-label="Table view"
                    >
                        <TableIcon className="size-3.5" />
                        <span className="hidden sm:inline">Table</span>
                    </Button>
                </div>
            </CardHeader>

            <CardContent>
                {series.length === 0 ? (
                    <div className="flex h-48 items-center justify-center text-sm text-muted-foreground">
                        No time-series data available for the selected period.
                    </div>
                ) : viewMode === 'chart' ? (
                    <div className="space-y-3">
                        <div
                            className="flex h-52 items-end gap-1 overflow-x-auto pt-6 pb-2 sm:gap-1.5"
                            role="region"
                            aria-label={`${title} chart`}
                        >
                            {series.map((point) => {
                                const heightPercent = Math.max(
                                    Math.round((point.value / maxValue) * 100),
                                    point.value > 0 ? 8 : 2,
                                );

                                return (
                                    <Tooltip key={point.date}>
                                        <TooltipTrigger asChild>
                                            <div
                                                className="group flex h-full min-w-[14px] flex-1 cursor-pointer flex-col items-center justify-end"
                                                tabIndex={0}
                                                role="button"
                                                aria-label={`${point.date}: ${point.value} ${metricLabel}`}
                                            >
                                                <div
                                                    style={{
                                                        height: `${heightPercent}%`,
                                                    }}
                                                    className={`w-full rounded-t-sm transition-all duration-200 group-hover:opacity-80 group-focus:ring-2 group-focus:ring-primary ${
                                                        point.value > 0
                                                            ? 'bg-primary'
                                                            : 'bg-muted-foreground/20'
                                                    }`}
                                                />
                                            </div>
                                        </TooltipTrigger>
                                        <TooltipContent side="top">
                                            <div className="text-xs font-medium">
                                                <p className="font-semibold text-foreground">
                                                    {point.date}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {point.value.toLocaleString()}{' '}
                                                    {metricLabel}
                                                </p>
                                            </div>
                                        </TooltipContent>
                                    </Tooltip>
                                );
                            })}
                        </div>

                        {/* Date axis preview */}
                        <div className="flex justify-between border-t pt-2 text-xs text-muted-foreground">
                            <span>{series[0]?.date}</span>
                            <span className="font-medium text-foreground">
                                Total: {totalValue.toLocaleString()}
                            </span>
                            <span>{series[series.length - 1]?.date}</span>
                        </div>
                    </div>
                ) : (
                    <div className="max-h-64 overflow-y-auto rounded-md border">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b bg-muted/40 text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-2.5 font-medium">
                                        Date
                                    </th>
                                    <th className="px-4 py-2.5 text-end font-medium">
                                        {metricLabel}
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {series.map((point) => (
                                    <tr
                                        key={point.date}
                                        className="transition-colors hover:bg-muted/50"
                                    >
                                        <td className="px-4 py-2 font-mono text-xs text-foreground">
                                            {point.date}
                                        </td>
                                        <td className="px-4 py-2 text-end font-semibold text-foreground">
                                            {point.value.toLocaleString()}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
