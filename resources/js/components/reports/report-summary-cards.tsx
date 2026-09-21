/* @chisel-reporting */

import {
    Activity,
    BarChart3,
    Layers,
    Shield,
    UserCheck,
    UserPlus,
    Users,
    UserX,
    Zap,
} from 'lucide-react';
import type { ComponentType } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ReportSummaryCard } from '@/types/reports';

const iconMap: Record<string, ComponentType<{ className?: string }>> = {
    Users,
    UserCheck,
    UserX,
    UserPlus,
    Activity,
    Shield,
    Zap,
    Layers,
};

interface ReportSummaryCardsProps {
    cards: ReportSummaryCard[];
}

export function ReportSummaryCards({ cards }: ReportSummaryCardsProps) {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {cards.map((card) => {
                const IconComponent = iconMap[card.icon] ?? BarChart3;
                const displayValue =
                    typeof card.value === 'number'
                        ? card.value.toLocaleString()
                        : card.value;

                return (
                    <Card
                        key={card.id}
                        className="border-sidebar-border/70 transition-shadow hover:shadow-xs dark:border-sidebar-border"
                    >
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {card.title}
                            </CardTitle>
                            <div className="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <IconComponent className="size-4" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold tracking-tight text-foreground">
                                {displayValue}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {card.description}
                            </p>
                        </CardContent>
                    </Card>
                );
            })}
        </div>
    );
}

/* @end-chisel-reporting */
