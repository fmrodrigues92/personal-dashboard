import { Head } from '@inertiajs/react';
import BillingManagement from '@/components/billing/billing-management';

type BillingInvoice = {
    id: number;
    billing_date: string;
    type: string;
    cnae: string | null;
    cnae_annex: number | null;
    cnae_calculation: number | null;
    customer_name: string | null;
    customer_external_id: string | null;
    amount_brl: number;
    amount_usd: number | null;
    usd_brl_exchange_rate: number | null;
    is_simulation: boolean;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
};

export default function BillingPage({
    initialInvoices,
}: {
    initialInvoices: BillingInvoice[];
}) {
    return (
        <>
            <Head title="Billing" />
            <BillingManagement initialInvoices={initialInvoices} />
        </>
    );
}

BillingPage.layout = {
    breadcrumbs: [
        {
            title: 'Billing',
            href: '/billing-invoices',
        },
    ],
};
