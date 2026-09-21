/* @chisel-reporting */

import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BarChart3, Shield, Users } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { index as reportsIndex } from '@/routes/reports';
import type { BreadcrumbItem } from '@/types';
import type { ReportMetadata } from '@/types/reports';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Reports',
        href: reportsIndex(),
    },
];

interface ReportsIndexProps {
    reports: ReportMetadata[];
}

export default function ReportsIndex({ reports }: ReportsIndexProps) {
    const getIcon = (identifier: string) => {
        switch (identifier) {
            case 'user_activity':
                return <Users className="size-5 text-primary" />;
            case 'audit_activity':
                return <Shield className="size-5 text-primary" />;
            default:
                return <BarChart3 className="size-5 text-primary" />;
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <Heading
                        title="Reports & Analytics"
                        description="Access pre-defined system and administrative reports."
                    />
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {reports.map((report) => (
                        <Card
                            key={report.identifier}
                            className="flex flex-col justify-between border-sidebar-border/70 transition-all hover:border-sidebar-border hover:shadow-xs dark:border-sidebar-border"
                        >
                            <CardHeader>
                                <div className="flex items-center justify-between gap-2">
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10">
                                        {getIcon(report.identifier)}
                                    </div>
                                    <Badge
                                        variant="secondary"
                                        className="text-xs"
                                    >
                                        {report.categoryLabel}
                                    </Badge>
                                </div>
                                <CardTitle className="mt-3 text-lg font-semibold">
                                    {report.title}
                                </CardTitle>
                                <CardDescription className="text-sm">
                                    {report.description}
                                </CardDescription>
                            </CardHeader>
                            <CardFooter className="pt-2">
                                <Button asChild className="w-full gap-2">
                                    <Link href={report.route}>
                                        <span>View Report</span>
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            </CardFooter>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}

/* @end-chisel-reporting */
