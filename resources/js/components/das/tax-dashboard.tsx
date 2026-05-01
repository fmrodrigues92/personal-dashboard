import { type ComponentType, startTransition, useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import {
    ArrowRightLeft,
    BadgePercent,
    BriefcaseBusiness,
    Calculator,
    CreditCard,
    Coins,
    Globe,
    Landmark,
    PiggyBank,
    RefreshCw,
    Scale,
    ShieldCheck,
    TrendingUp,
    Wallet,
} from 'lucide-react';

type TaxBreakdown = {
    tax_component_code: string;
    annex_used: number | null;
    invoice_type: string | null;
    calculated_amount_brl: number;
    adjusted_amount_brl?: number | null;
    rate_percentage: number | null;
};

type DasScenario = {
    rbt12_brl: number;
    monthly_revenue_brl: number;
    das_total_brl: number;
    tax_breakdowns: TaxBreakdown[];
};

export type DasTimelineItem = {
    reference_month: string;
    das_total_brl: number;
    monthly_revenue_brl: number;
    is_projection: boolean;
    rule_version: string;
    das_calculation_id: number | null;
    rbt12_national_brl: number;
    rbt12_international_brl: number;
    rbt12_total_brl: number;
    das_real: DasScenario;
    das_contabilidade: DasScenario;
};

export type DasTimelineFilters = {
    reference_month: string;
    months_before: number;
    months_after: number;
    rule_version: string | null;
};

type DasTimelineResponse = {
    data: DasTimelineItem[];
};

type ApiError = {
    message?: string;
};

const currencyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

const percentFormatter = new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 3,
});

const monthFormatter = new Intl.DateTimeFormat('en-US', {
    month: 'short',
    year: 'numeric',
});

function currentMonthInputValue(): string {
    return new Date().toISOString().slice(0, 7);
}

function referenceMonthToInputValue(referenceMonth: string): string {
    return referenceMonth.slice(0, 7);
}

function toReferenceMonthDate(monthInput: string): string {
    return `${monthInput}-01`;
}

function currentReferenceMonthDate(): string {
    return toReferenceMonthDate(currentMonthInputValue());
}

function formatCurrency(value: number): string {
    return currencyFormatter.format(value);
}

function formatPercent(value: number | null): string {
    if (value === null) {
        return '-';
    }

    return `${percentFormatter.format(value)}%`;
}

function formatMonth(referenceMonth: string): string {
    return monthFormatter.format(new Date(`${referenceMonth}T00:00:00`));
}

function normalizeTaxLabel(code: string): string {
    return code.replace(/_/g, '/').toUpperCase();
}

function effectiveTaxAmount(taxBreakdown: TaxBreakdown): number {
    return taxBreakdown.adjusted_amount_brl ?? taxBreakdown.calculated_amount_brl;
}

async function fetchTimeline(filters: DasTimelineFilters): Promise<DasTimelineResponse> {
    const params = new URLSearchParams();
    params.set('reference_month', filters.reference_month);
    params.set('months_before', String(filters.months_before));
    params.set('months_after', String(filters.months_after));

    if (filters.rule_version) {
        params.set('rule_version', filters.rule_version);
    }

    const response = await fetch(`/das-calculations/timeline?${params.toString()}`, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        const errorBody = (await response.json().catch(() => null)) as ApiError | null;
        throw new Error(errorBody?.message ?? 'Unable to load the tax timeline.');
    }

    return (await response.json()) as DasTimelineResponse;
}

export default function TaxDashboard({
    initialTimeline,
    initialFilters,
}: {
    initialTimeline: DasTimelineItem[];
    initialFilters: DasTimelineFilters;
}) {
    const [timeline, setTimeline] = useState<DasTimelineItem[]>(initialTimeline);
    const [filters, setFilters] = useState<DasTimelineFilters>(initialFilters);
    const [selectedReferenceMonth, setSelectedReferenceMonth] = useState(initialFilters.reference_month);
    const [referenceMonthInput, setReferenceMonthInput] = useState(
        referenceMonthToInputValue(initialFilters.reference_month),
    );
    const [windowMonths, setWindowMonths] = useState(
        String(Math.max(initialFilters.months_before, initialFilters.months_after)),
    );
    const [loading, setLoading] = useState(initialTimeline.length === 0);
    const [refreshing, setRefreshing] = useState(false);
    const [fetchError, setFetchError] = useState<string | null>(null);
    const currentReferenceMonth = currentReferenceMonthDate();
    const currentMonthCardRef = useRef<HTMLButtonElement | null>(null);

    useEffect(() => {
        setTimeline(initialTimeline);
        setFilters(initialFilters);
        setReferenceMonthInput(referenceMonthToInputValue(initialFilters.reference_month));
        setWindowMonths(String(Math.max(initialFilters.months_before, initialFilters.months_after)));
        setSelectedReferenceMonth(initialFilters.reference_month);
        setLoading(false);
        setRefreshing(false);
    }, [initialFilters, initialTimeline]);

    useEffect(() => {
        if (timeline.some((item) => item.reference_month === selectedReferenceMonth)) {
            return;
        }

        const fallbackMonth =
            timeline.find((item) => item.reference_month === filters.reference_month)?.reference_month ??
            timeline[0]?.reference_month ??
            '';

        if (fallbackMonth) {
            setSelectedReferenceMonth(fallbackMonth);
        }
    }, [filters.reference_month, selectedReferenceMonth, timeline]);

    useEffect(() => {
        currentMonthCardRef.current?.scrollIntoView({
            behavior: 'smooth',
            inline: 'center',
            block: 'nearest',
        });
    }, [timeline]);

    const selectedMonth =
        timeline.find((item) => item.reference_month === selectedReferenceMonth) ??
        timeline.find((item) => item.reference_month === filters.reference_month) ??
        timeline[0] ??
        null;

    const differenceBrl = selectedMonth
        ? selectedMonth.das_real.das_total_brl - selectedMonth.das_contabilidade.das_total_brl
        : 0;

    async function loadTimeline(nextFilters: DasTimelineFilters) {
        if (timeline.length === 0) {
            setLoading(true);
        } else {
            setRefreshing(true);
        }

        setFetchError(null);

        try {
            const response = await fetchTimeline(nextFilters);

            startTransition(() => {
                setTimeline(response.data);
            });

            setFilters(nextFilters);
            setSelectedReferenceMonth(nextFilters.reference_month);
        } catch (error) {
            setFetchError(
                error instanceof Error ? error.message : 'Unable to load the tax timeline.',
            );
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }

    async function applyFilters() {
        const nextWindowMonths = Number(windowMonths);
        const nextReferenceMonth = toReferenceMonthDate(referenceMonthInput);

        await loadTimeline({
            reference_month: nextReferenceMonth,
            months_before: nextWindowMonths,
            months_after: nextWindowMonths,
            rule_version: filters.rule_version,
        });
    }

    async function resetToCurrentMonth() {
        const currentMonth = currentMonthInputValue();
        setReferenceMonthInput(currentMonth);
        setWindowMonths('12');

        await loadTimeline({
            reference_month: toReferenceMonthDate(currentMonth),
            months_before: 12,
            months_after: 12,
            rule_version: filters.rule_version,
        });
    }

    return (
        <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <section className="rounded-xl border border-sidebar-border/70 bg-card p-6 shadow-sm dark:border-sidebar-border">
                <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div className="space-y-3">
                        <Badge variant="outline" className="gap-2 rounded-full px-3 py-1 text-xs">
                            <Scale className="size-3.5" />
                            Tax dashboard
                        </Badge>

                        <Heading
                            title="DAS overview"
                            description="Track the legal DAS scenario, compare it with the accounting interpretation, and keep the dashboard ready for future pro-labore modules."
                        />
                    </div>

                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => void resetToCurrentMonth()}
                            disabled={refreshing}
                        >
                            <RefreshCw className={cn(refreshing && 'animate-spin')} />
                            Current month
                        </Button>

                        <Button
                            type="button"
                            onClick={() => void applyFilters()}
                            disabled={refreshing}
                        >
                            <Calculator />
                            Refresh timeline
                        </Button>
                    </div>
                </div>

                <div className="mt-6 grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto]">
                    <Card className="border-dashed">
                        <CardHeader>
                            <CardTitle>Timeline window</CardTitle>
                            <CardDescription>
                                Select the reference month and how many months before and after should be loaded.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
                            <div className="space-y-2">
                                <Label htmlFor="reference-month">Reference month</Label>
                                <Input
                                    id="reference-month"
                                    type="month"
                                    value={referenceMonthInput}
                                    onChange={(event) => setReferenceMonthInput(event.target.value)}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label>Window size</Label>
                                <ToggleGroup
                                    type="single"
                                    value={windowMonths}
                                    onValueChange={(value) => {
                                        if (value) {
                                            setWindowMonths(value);
                                        }
                                    }}
                                    variant="outline"
                                    className="flex flex-wrap justify-start"
                                >
                                    <ToggleGroupItem value="0">Single month</ToggleGroupItem>
                                    <ToggleGroupItem value="3">3 months</ToggleGroupItem>
                                    <ToggleGroupItem value="6">6 months</ToggleGroupItem>
                                    <ToggleGroupItem value="12">12 months</ToggleGroupItem>
                                </ToggleGroup>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="min-w-0 border-dashed xl:w-80">
                        <CardHeader>
                            <CardTitle>Future scope</CardTitle>
                            <CardDescription>
                                This dashboard is DAS-first now and intentionally reserved for pro-labore visibility next.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                </div>
            </section>

            {fetchError ? (
                <Card className="border-destructive/40">
                    <CardHeader>
                        <CardTitle>Unable to load the tax dashboard</CardTitle>
                        <CardDescription>{fetchError}</CardDescription>
                    </CardHeader>
                </Card>
            ) : null}

            {loading ? (
                <DashboardSkeleton />
            ) : selectedMonth ? (
                <>
                    <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <SummaryCard
                            title="DAS real"
                            value={formatCurrency(selectedMonth.das_real.das_total_brl)}
                            hint="Legal scenario currently implemented"
                            icon={ShieldCheck}
                        />
                        <SummaryCard
                            title="DAS contabilidade"
                            value={formatCurrency(selectedMonth.das_contabilidade.das_total_brl)}
                            hint="Comparison scenario for accounting interpretation"
                            icon={ArrowRightLeft}
                        />
                        <SummaryCard
                            title="Monthly revenue"
                            value={formatCurrency(selectedMonth.monthly_revenue_brl)}
                            hint="Revenue considered in the selected month"
                            icon={Coins}
                        />
                        <SummaryCard
                            title="Scenario delta"
                            value={formatCurrency(differenceBrl)}
                            hint="Real scenario minus accounting scenario"
                            icon={TrendingUp}
                        />
                    </section>

                    <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <SummaryCard
                            title="RBT12 national"
                            value={formatCurrency(selectedMonth.rbt12_national_brl)}
                            hint="Rolling national revenue base"
                            icon={Landmark}
                        />
                        <SummaryCard
                            title="RBT12 international"
                            value={formatCurrency(selectedMonth.rbt12_international_brl)}
                            hint="Rolling international revenue base"
                            icon={Globe}
                        />
                        <SummaryCard
                            title="RBT12 total"
                            value={formatCurrency(selectedMonth.rbt12_total_brl)}
                            hint="Combined 12-month revenue base"
                            icon={Scale}
                        />
                        <SummaryCard
                            title="Rule version"
                            value={selectedMonth.rule_version}
                            hint={selectedMonth.is_projection ? 'Projection month' : 'Persisted or preview real month'}
                            icon={Calculator}
                        />
                    </section>

                    <Card>
                        <CardHeader>
                            <CardTitle>Timeline</CardTitle>
                            <CardDescription>
                                Select a month to inspect the detailed DAS composition for both scenarios.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {timeline.length === 0 ? (
                                <div className="rounded-lg border border-dashed p-8 text-sm text-muted-foreground">
                                    No timeline items were returned for the selected period.
                                </div>
                            ) : (
                                <div className="flex gap-3 overflow-x-auto pb-6">
                                    {timeline.map((item) => {
                                        const isActive = item.reference_month === selectedMonth.reference_month;
                                        const isCurrentMonth = item.reference_month === currentReferenceMonth;

                                        return (
                                            <button
                                                key={item.reference_month}
                                                ref={isCurrentMonth ? currentMonthCardRef : null}
                                                type="button"
                                                onClick={() => setSelectedReferenceMonth(item.reference_month)}
                                                className={cn(
                                                    'min-w-56 rounded-xl border p-4 text-left transition-colors',
                                                    isActive
                                                        ? 'border-primary bg-primary/5 shadow-sm'
                                                        : 'border-border bg-background hover:bg-accent/40',
                                                    isCurrentMonth &&
                                                        'ring-1 ring-amber-400/70 ring-offset-2 ring-offset-background',
                                                )}
                                            >
                                                <div className="flex items-start justify-between gap-3">
                                                    <div>
                                                        <p className="text-sm font-semibold">
                                                            {formatMonth(item.reference_month)}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {item.reference_month}
                                                        </p>
                                                    </div>

                                                    <div className="flex flex-col items-end gap-2">
                                                        <Badge
                                                            variant={item.is_projection ? 'secondary' : 'outline'}
                                                        >
                                                            {item.is_projection ? 'Projection' : 'Real'}
                                                        </Badge>

                                                        {isCurrentMonth ? (
                                                            <Badge className="bg-amber-500 text-black hover:bg-amber-500">
                                                                Current month
                                                            </Badge>
                                                        ) : null}
                                                    </div>
                                                </div>

                                                <div className="mt-4 space-y-2">
                                                    <div>
                                                        <p className="text-xs text-muted-foreground">DAS real</p>
                                                        <p className="text-lg font-semibold">
                                                            {formatCurrency(item.das_real.das_total_brl)}
                                                        </p>
                                                    </div>

                                                    <div>
                                                        <p className="text-xs text-muted-foreground">
                                                            DAS contabilidade
                                                        </p>
                                                        <p className="text-sm font-medium">
                                                            {formatCurrency(item.das_contabilidade.das_total_brl)}
                                                        </p>
                                                    </div>
                                                </div>
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <section className="grid gap-4 xl:grid-cols-2">
                        <ScenarioCard
                            title="DAS real"
                            description="Legal rule scenario used by the current system implementation."
                            scenario={selectedMonth.das_real}
                        />
                        <ScenarioCard
                            title="DAS contabilidade"
                            description="Comparison scenario matching the accounting interpretation used for reference."
                            scenario={selectedMonth.das_contabilidade}
                        />
                    </section>

                    <section className="grid gap-4 xl:grid-cols-2">
                        <FinancialPreviewCard
                            title="Pro-labore placeholder"
                            description="Future single-person workflow with fake values only for layout direction."
                            icon={BadgePercent}
                            items={[
                                { label: 'Pro-labore base', value: 6200 },
                                { label: 'INSS discount', value: -682 },
                                { label: 'IRPF discount', value: -418 },
                                { label: 'Net pro-labore', value: 5100, emphasize: true },
                            ]}
                        />
                        <FinancialPreviewCard
                            title="Costs and profit placeholder"
                            description="Operational costs and the remaining profit with fake values only."
                            icon={PiggyBank}
                            items={[
                                { label: 'Card costs', value: -340 },
                                { label: 'Other debit purchases', value: -275 },
                                { label: 'Accounting subscription', value: -690 },
                                { label: 'Assessed profit', value: 11890, emphasize: true },
                            ]}
                        />
                    </section>

                    <Card className="border-dashed">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BriefcaseBusiness className="size-4" />
                                Profit planning placeholder
                            </CardTitle>
                            <CardDescription>
                                The selected month already anchors the future profit flow. Once pro-labore and
                                operating expenses are implemented, this section can connect revenue, DAS,
                                personal withdrawals, extra costs, and transferable profit in one place.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                </>
            ) : (
                <Card>
                    <CardHeader>
                        <CardTitle>No tax data yet</CardTitle>
                        <CardDescription>
                            The dashboard is ready, but there is no timeline data available for the selected period.
                        </CardDescription>
                    </CardHeader>
                </Card>
            )}
        </div>
    );
}

function FinancialPreviewCard({
    title,
    description,
    items,
    icon: Icon,
}: {
    title: string;
    description: string;
    items: Array<{ label: string; value: number; emphasize?: boolean }>;
    icon: ComponentType<{ className?: string }>;
}) {
    return (
        <Card className="border-dashed">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Icon className="size-4 text-primary" />
                    {title}
                </CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {items.map((item) => (
                    <div
                        key={item.label}
                        className={cn(
                            'flex items-center justify-between rounded-lg border border-dashed bg-muted/20 px-4 py-3 text-sm',
                            item.emphasize ? 'border-primary/40 bg-primary/5' : '',
                        )}
                    >
                        <span className="text-muted-foreground">{item.label}</span>
                        <span className={cn('font-medium', item.emphasize ? 'text-primary' : '')}>
                            {formatCurrency(item.value)}
                        </span>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function SummaryCard({
    title,
    value,
    hint,
    icon: Icon,
}: {
    title: string;
    value: string;
    hint: string;
    icon: ComponentType<{ className?: string }>;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between space-y-0">
                <div className="space-y-1">
                    <CardDescription>{title}</CardDescription>
                    <CardTitle className="text-xl leading-tight">{value}</CardTitle>
                </div>
                <div className="rounded-full bg-primary/10 p-2 text-primary">
                    <Icon className="size-4" />
                </div>
            </CardHeader>
            <CardContent>
                <p className="text-sm text-muted-foreground">{hint}</p>
            </CardContent>
        </Card>
    );
}

function ScenarioCard({
    title,
    description,
    scenario,
}: {
    title: string;
    description: string;
    scenario: DasScenario;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="rounded-lg border border-dashed p-4">
                        <p className="text-xs uppercase tracking-wide text-muted-foreground">
                            DAS total
                        </p>
                        <p className="mt-2 text-2xl font-semibold">
                            {formatCurrency(scenario.das_total_brl)}
                        </p>
                    </div>

                    <div className="rounded-lg border border-dashed p-4">
                        <p className="text-xs uppercase tracking-wide text-muted-foreground">
                            RBT12
                        </p>
                        <p className="mt-2 text-2xl font-semibold">
                            {formatCurrency(scenario.rbt12_brl)}
                        </p>
                    </div>
                </div>

                <div className="rounded-lg border">
                    <div className="grid grid-cols-[minmax(0,1.4fr)_minmax(0,0.8fr)_minmax(0,0.8fr)] gap-3 border-b bg-muted/30 px-4 py-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        <span>Tax component</span>
                        <span>Rate</span>
                        <span>Amount</span>
                    </div>

                    {scenario.tax_breakdowns.length === 0 ? (
                        <div className="px-4 py-8 text-sm text-muted-foreground">
                            No tax breakdowns are available for this scenario and month.
                        </div>
                    ) : (
                        <div className="divide-y">
                            {scenario.tax_breakdowns.map((taxBreakdown) => (
                                <div
                                    key={`${title}-${taxBreakdown.tax_component_code}-${taxBreakdown.invoice_type ?? 'all'}-${taxBreakdown.annex_used ?? 'na'}`}
                                    className="grid grid-cols-[minmax(0,1.4fr)_minmax(0,0.8fr)_minmax(0,0.8fr)] gap-3 px-4 py-3 text-sm"
                                >
                                    <div className="space-y-1">
                                        <p className="font-medium">
                                            {normalizeTaxLabel(taxBreakdown.tax_component_code)}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Annex {taxBreakdown.annex_used ?? '-'} /{' '}
                                            {taxBreakdown.invoice_type ?? 'mixed'}
                                        </p>
                                    </div>
                                    <span>{formatPercent(taxBreakdown.rate_percentage)}</span>
                                    <span>{formatCurrency(effectiveTaxAmount(taxBreakdown))}</span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function DashboardSkeleton() {
    return (
        <>
            <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                {[...Array.from({ length: 4 })].map((_, index) => (
                    <Skeleton key={`summary-${index}`} className="h-32 rounded-xl" />
                ))}
            </section>

            <Skeleton className="h-64 rounded-xl" />

            <section className="grid gap-4 xl:grid-cols-2">
                <Skeleton className="h-96 rounded-xl" />
                <Skeleton className="h-96 rounded-xl" />
            </section>
        </>
    );
}
