import { Head } from '@inertiajs/react';
import ProLaboreManagement from '@/components/pro-labore/pro-labore-management';

export type ProLaboreEntry = {
    id: number;
    reference_month: string;
    gross_amount_brl: number;
    source_revenue_brl: number | null;
    source: string | null;
    notes: string | null;
    is_simulation: boolean;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
};

export default function ProLaborePage({
    initialEntries,
}: {
    initialEntries: ProLaboreEntry[];
}) {
    return (
        <>
            <Head title="Pro-labore" />
            <ProLaboreManagement initialEntries={initialEntries} />
        </>
    );
}

ProLaborePage.layout = {
    breadcrumbs: [
        {
            title: 'Pro-labore',
            href: '/pro-labore',
        },
    ],
};
