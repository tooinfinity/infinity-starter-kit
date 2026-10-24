import { Calendar } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';

interface ReportDateRangeFilterProps {
    dateFrom: string;
    dateTo: string;
    onDateFromChange: (val: string) => void;
    onDateToChange: (val: string) => void;
}

function formatDate(d: Date): string {
    return d.toISOString().split('T')[0];
}

function computePresetRange(days: number): { from: string; to: string } {
    const today = new Date();
    const past = new Date();
    past.setDate(today.getDate() - (days - 1));
    return {
        from: formatDate(past),
        to: formatDate(today),
    };
}

export function ReportDateRangeFilter({
    dateFrom,
    dateTo,
    onDateFromChange,
    onDateToChange,
}: ReportDateRangeFilterProps) {
    const { __ } = useTranslation();

    const handlePresetChange = (preset: string) => {
        if (preset === 'custom') {
            return;
        }

        const daysMap: Record<string, number> = {
            '7d': 7,
            '30d': 30,
            '90d': 90,
            '365d': 365,
        };

        const days = daysMap[preset];
        if (days) {
            const range = computePresetRange(days);
            onDateFromChange(range.from);
            onDateToChange(range.to);
        }
    };

    return (
        <div className="flex flex-wrap items-end gap-3">
            <div className="w-36">
                <Label htmlFor="date-preset" className="text-xs">
                    {__('Range Preset')}
                </Label>
                <Select onValueChange={handlePresetChange} defaultValue="30d">
                    <SelectTrigger id="date-preset" className="h-9 text-xs">
                        <Calendar className="me-1.5 size-3.5 opacity-70" />
                        <SelectValue placeholder={__('Preset')} />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="7d">{__('Last 7 Days')}</SelectItem>
                        <SelectItem value="30d">
                            {__('Last 30 Days')}
                        </SelectItem>
                        <SelectItem value="90d">
                            {__('Last 90 Days')}
                        </SelectItem>
                        <SelectItem value="365d">
                            {__('Last 365 Days')}
                        </SelectItem>
                        <SelectItem value="custom">{__('Custom')}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div className="w-36">
                <Label htmlFor="report-date-from" className="text-xs">
                    {__('From')}
                </Label>
                <Input
                    id="report-date-from"
                    type="date"
                    value={dateFrom}
                    onChange={(e) => onDateFromChange(e.target.value)}
                    className="h-9 text-xs"
                />
            </div>

            <div className="w-36">
                <Label htmlFor="report-date-to" className="text-xs">
                    {__('To')}
                </Label>
                <Input
                    id="report-date-to"
                    type="date"
                    value={dateTo}
                    onChange={(e) => onDateToChange(e.target.value)}
                    className="h-9 text-xs"
                />
            </div>
        </div>
    );
}
