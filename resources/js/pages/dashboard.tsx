import { Head } from '@inertiajs/react';
import TaxDashboard, {
    type DasTimelineFilters,
    type DasTimelineItem,
} from '@/components/das/tax-dashboard';
import { dashboard } from '@/routes';

export default function Dashboard({
    initialTimeline,
    initialTimelineFilters,
}: {
    initialTimeline: DasTimelineItem[];
    initialTimelineFilters: DasTimelineFilters;
}) {
    return (
        <>
            <Head title="Dashboard" />
            <TaxDashboard
                initialTimeline={initialTimeline}
                initialFilters={initialTimelineFilters}
            />
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
