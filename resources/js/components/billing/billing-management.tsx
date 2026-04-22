import { useEffect, useState, useDeferredValue, startTransition } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Plus,
    Receipt,
    Search,
    Sparkles,
    Trash2,
} from 'lucide-react';

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

type BillingInvoicesResponse = {
    data: BillingInvoice[];
};

type SimulationCreateResponse = {
    data: BillingInvoice[];
    meta?: {
        created_count?: number;
    };
};

type ApiError = {
    message?: string;
    errors?: Record<string, string[]>;
};

type RealInvoiceForm = {
    billing_date: string;
    type: 'national' | 'international';
    cnae: string;
    cnae_annex: '3' | '5';
    customer_name: string;
    customer_external_id: string;
    amount_brl: string;
    amount_usd: string;
    usd_brl_exchange_rate: string;
};

type SimulationForm = {
    type: 'national' | 'international';
    start_date: string;
    end_date: string;
    amount_brl: string;
};

const currencyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

const dateFormatter = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

const pageSize = 10;

function defaultRealInvoiceForm(): RealInvoiceForm {
    return {
        billing_date: '',
        type: 'national',
        cnae: '',
        cnae_annex: '3',
        customer_name: '',
        customer_external_id: '',
        amount_brl: '',
        amount_usd: '',
        usd_brl_exchange_rate: '',
    };
}

function defaultSimulationForm(): SimulationForm {
    return {
        type: 'national',
        start_date: '',
        end_date: '',
        amount_brl: '',
    };
}

function readCookie(name: string): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const target = document.cookie
        .split('; ')
        .find((item) => item.startsWith(`${name}=`));

    return target ? decodeURIComponent(target.split('=').slice(1).join('=')) : null;
}

async function billingApi<T>(path: string, init?: RequestInit): Promise<T> {
    const headers = new Headers(init?.headers);
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');

    if (init?.method && init.method !== 'GET') {
        headers.set('Content-Type', 'application/json');

        const csrfToken = readCookie('XSRF-TOKEN');
        if (csrfToken) {
            headers.set('X-XSRF-TOKEN', csrfToken);
        }
    }

    const response = await fetch(path, {
        credentials: 'same-origin',
        ...init,
        headers,
    });

    if (!response.ok) {
        const errorBody = (await response.json().catch(() => null)) as ApiError | null;
        const error = new Error(errorBody?.message ?? 'Unable to complete the request.');
        (error as Error & { details?: ApiError; status?: number }).details = errorBody ?? undefined;
        (error as Error & { details?: ApiError; status?: number }).status = response.status;
        throw error;
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return (await response.json()) as T;
}

function normalizeType(type: string): 'national' | 'international' {
    return type.trim().toLowerCase() === 'international' ? 'international' : 'national';
}

function invoiceMatchesDateFrom(invoice: BillingInvoice, dateFrom: string): boolean {
    if (!dateFrom) {
        return true;
    }

    return invoice.billing_date.slice(0, 10) >= dateFrom;
}

function invoiceMatchesDateTo(invoice: BillingInvoice, dateTo: string): boolean {
    if (!dateTo) {
        return true;
    }

    return invoice.billing_date.slice(0, 10) <= dateTo;
}

export default function BillingManagement({
    initialInvoices = [],
}: {
    initialInvoices?: BillingInvoice[];
}) {
    const [invoices, setInvoices] = useState<BillingInvoice[]>(initialInvoices);
    const [loading, setLoading] = useState(initialInvoices.length === 0);
    const [refreshing, setRefreshing] = useState(false);
    const [fetchError, setFetchError] = useState<string | null>(null);
    const [filtersOpen, setFiltersOpen] = useState(true);
    const [currentPage, setCurrentPage] = useState(1);
    const [search, setSearch] = useState('');
    const deferredSearch = useDeferredValue(search);
    const [typeFilter, setTypeFilter] = useState<'all' | 'national' | 'international'>('all');
    const [simulationFilter, setSimulationFilter] = useState<'all' | 'real' | 'simulation'>('all');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [realModalOpen, setRealModalOpen] = useState(false);
    const [simulationModalOpen, setSimulationModalOpen] = useState(false);
    const [deleteRealModalOpen, setDeleteRealModalOpen] = useState(false);
    const [deleteSimulationsModalOpen, setDeleteSimulationsModalOpen] = useState(false);
    const [selectedInvoice, setSelectedInvoice] = useState<BillingInvoice | null>(null);
    const [realForm, setRealForm] = useState<RealInvoiceForm>(defaultRealInvoiceForm);
    const [simulationForm, setSimulationForm] = useState<SimulationForm>(defaultSimulationForm);
    const [realErrors, setRealErrors] = useState<Record<string, string[]>>({});
    const [simulationErrors, setSimulationErrors] = useState<Record<string, string[]>>({});
    const [submittingReal, setSubmittingReal] = useState(false);
    const [submittingSimulation, setSubmittingSimulation] = useState(false);
    const [deletingReal, setDeletingReal] = useState(false);
    const [deletingSimulations, setDeletingSimulations] = useState(false);

    async function loadInvoices(silent = false) {
        if (silent) {
            setRefreshing(true);
        } else {
            setLoading(true);
        }

        setFetchError(null);

        try {
            const response = await billingApi<BillingInvoicesResponse>('/billing-invoices');

            startTransition(() => {
                setInvoices(response.data);
            });
        } catch (error) {
            setFetchError(
                error instanceof Error
                    ? error.message
                    : 'Unable to load billing invoices.',
            );
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }

    useEffect(() => {
        if (initialInvoices.length === 0) {
            return;
        }

        startTransition(() => {
            setInvoices(initialInvoices);
        });

        setLoading(false);
        setRefreshing(false);
    }, [initialInvoices, initialInvoices.length]);

    useEffect(() => {
        if (initialInvoices.length === 0) {
            void loadInvoices();
        }
    }, [initialInvoices.length]);

    useEffect(() => {
        setCurrentPage(1);
    }, [dateFrom, dateTo, typeFilter, simulationFilter, deferredSearch]);

    const normalizedSearch = deferredSearch.trim().toLowerCase();

    const filteredInvoices = invoices.filter((invoice) => {
        const invoiceType = normalizeType(invoice.type);
        const searchable = [
            invoice.customer_name ?? '',
            invoice.customer_external_id ?? '',
            invoice.cnae ?? '',
        ]
            .join(' ')
            .toLowerCase();

        if (!invoiceMatchesDateFrom(invoice, dateFrom)) {
            return false;
        }

        if (!invoiceMatchesDateTo(invoice, dateTo)) {
            return false;
        }

        if (typeFilter !== 'all' && invoiceType !== typeFilter) {
            return false;
        }

        if (simulationFilter === 'real' && invoice.is_simulation) {
            return false;
        }

        if (simulationFilter === 'simulation' && !invoice.is_simulation) {
            return false;
        }

        if (normalizedSearch && !searchable.includes(normalizedSearch)) {
            return false;
        }

        return true;
    });

    const totalPages = Math.max(1, Math.ceil(filteredInvoices.length / pageSize));
    const safeCurrentPage = Math.min(currentPage, totalPages);
    const pageStart = (safeCurrentPage - 1) * pageSize;
    const paginatedInvoices = filteredInvoices.slice(pageStart, pageStart + pageSize);
    const simulationCount = invoices.filter((invoice) => invoice.is_simulation).length;
    const realCount = invoices.length - simulationCount;

    function updateRealForm<K extends keyof RealInvoiceForm>(
        key: K,
        value: RealInvoiceForm[K],
    ) {
        setRealForm((current) => {
            const next = { ...current, [key]: value };

            if (key === 'type' && value === 'national') {
                next.amount_usd = '';
                next.usd_brl_exchange_rate = '';
            }

            return next;
        });
    }

    function resetRealModal() {
        setRealForm(defaultRealInvoiceForm());
        setRealErrors({});
        setRealModalOpen(false);
    }

    function resetSimulationModal() {
        setSimulationForm(defaultSimulationForm());
        setSimulationErrors({});
        setSimulationModalOpen(false);
    }

    async function submitRealInvoice() {
        setSubmittingReal(true);
        setRealErrors({});

        try {
            await billingApi('/billing-invoices', {
                method: 'POST',
                body: JSON.stringify({
                    ...realForm,
                    cnae_annex: Number(realForm.cnae_annex),
                    amount_brl: Number(realForm.amount_brl),
                    amount_usd:
                        realForm.type === 'international' && realForm.amount_usd
                            ? Number(realForm.amount_usd)
                            : null,
                    usd_brl_exchange_rate:
                        realForm.type === 'international' &&
                        realForm.usd_brl_exchange_rate
                            ? Number(realForm.usd_brl_exchange_rate)
                            : null,
                }),
            });

            toast.success('Billing invoice created.');
            resetRealModal();
            await loadInvoices(true);
        } catch (error) {
            const details = (error as Error & { details?: ApiError }).details;
            if (details?.errors) {
                setRealErrors(details.errors);
            }

            toast.error(details?.message ?? 'Unable to create billing invoice.');
        } finally {
            setSubmittingReal(false);
        }
    }

    async function submitSimulationInvoices() {
        setSubmittingSimulation(true);
        setSimulationErrors({});

        try {
            const response = await billingApi<SimulationCreateResponse>(
                '/billing-invoices/simulations',
                {
                    method: 'POST',
                    body: JSON.stringify({
                        ...simulationForm,
                        amount_brl: Number(simulationForm.amount_brl),
                    }),
                },
            );

            toast.success(
                response.meta?.created_count
                    ? `${response.meta.created_count} simulation invoices created.`
                    : 'Simulation invoices created.',
            );
            resetSimulationModal();
            await loadInvoices(true);
        } catch (error) {
            const details = (error as Error & { details?: ApiError }).details;
            if (details?.errors) {
                setSimulationErrors(details.errors);
            }

            toast.error(details?.message ?? 'Unable to create simulation invoices.');
        } finally {
            setSubmittingSimulation(false);
        }
    }

    async function deleteRealInvoice() {
        if (!selectedInvoice) {
            return;
        }

        setDeletingReal(true);

        try {
            await billingApi(`/billing-invoices/${selectedInvoice.id}`, {
                method: 'DELETE',
            });

            toast.success('Billing invoice deleted.');
            setDeleteRealModalOpen(false);
            setSelectedInvoice(null);
            await loadInvoices(true);
        } catch (error) {
            const details = (error as Error & { details?: ApiError }).details;
            toast.error(details?.message ?? 'Unable to delete billing invoice.');
        } finally {
            setDeletingReal(false);
        }
    }

    async function deleteSimulationInvoices() {
        setDeletingSimulations(true);

        try {
            await billingApi('/billing-invoices/simulations', {
                method: 'DELETE',
            });

            toast.success('Simulation invoices deleted.');
            setDeleteSimulationsModalOpen(false);
            await loadInvoices(true);
        } catch (error) {
            const details = (error as Error & { details?: ApiError }).details;
            toast.error(details?.message ?? 'Unable to delete simulations.');
        } finally {
            setDeletingSimulations(false);
        }
    }

    return (
        <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <section className="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 bg-card p-6 shadow-sm dark:border-sidebar-border">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-3">
                        <Badge variant="outline" className="gap-2 rounded-full px-3 py-1 text-xs">
                            <Receipt className="size-3.5" />
                            Billing management
                        </Badge>

                        <Heading
                            title="Billing invoices"
                            description="View, filter, create, and clean up billing records without leaving the current workspace."
                        />
                    </div>

                    <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setSimulationModalOpen(true)}
                        >
                            <Sparkles />
                            Bulk simulations
                        </Button>

                        <Button
                            type="button"
                            onClick={() => setRealModalOpen(true)}
                        >
                            <Plus />
                            New invoice
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        title="All invoices"
                        value={String(invoices.length)}
                        hint="Current active records"
                    />
                    <SummaryCard
                        title="Real invoices"
                        value={String(realCount)}
                        hint="One-by-one registered records"
                    />
                    <SummaryCard
                        title="Simulations"
                        value={String(simulationCount)}
                        hint="Future projections"
                    />
                    <SummaryCard
                        title="Filtered"
                        value={String(filteredInvoices.length)}
                        hint="Records matching current filters"
                    />
                </div>
            </section>

            <Card>
                <Collapsible open={filtersOpen} onOpenChange={setFiltersOpen}>
                    <CardHeader className="gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <CardTitle>Filters</CardTitle>
                            <CardDescription>
                                Narrow the invoice list by date range, type, simulation status, and text search.
                            </CardDescription>
                        </div>

                        <div className="flex items-center gap-2">
                            {simulationCount > 0 && (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={() => setDeleteSimulationsModalOpen(true)}
                                >
                                    <Trash2 />
                                    Delete all simulations
                                </Button>
                            )}

                            <CollapsibleTrigger asChild>
                                <Button type="button" variant="outline">
                                    <ChevronDown className={cn('transition-transform', filtersOpen && 'rotate-180')} />
                                    {filtersOpen ? 'Hide filters' : 'Show filters'}
                                </Button>
                            </CollapsibleTrigger>
                        </div>
                    </CardHeader>

                    <CollapsibleContent>
                        <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                            <div className="grid gap-2 xl:col-span-2">
                                <Label htmlFor="billing-search">Search</Label>
                                <div className="relative">
                                    <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                    <Input
                                        id="billing-search"
                                        value={search}
                                        onChange={(event) => setSearch(event.target.value)}
                                        placeholder="Customer name, external id, or CNAE"
                                        className="pl-9"
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="billing-date-from">Date from</Label>
                                <Input
                                    id="billing-date-from"
                                    type="date"
                                    value={dateFrom}
                                    onChange={(event) => setDateFrom(event.target.value)}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="billing-date-to">Date to</Label>
                                <Input
                                    id="billing-date-to"
                                    type="date"
                                    value={dateTo}
                                    onChange={(event) => setDateTo(event.target.value)}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label>Type</Label>
                                <Select
                                    value={typeFilter}
                                    onValueChange={(value) =>
                                        setTypeFilter(value as 'all' | 'national' | 'international')
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="All types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All types</SelectItem>
                                        <SelectItem value="national">National</SelectItem>
                                        <SelectItem value="international">International</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label>Simulation</Label>
                                <Select
                                    value={simulationFilter}
                                    onValueChange={(value) =>
                                        setSimulationFilter(value as 'all' | 'real' | 'simulation')
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="All records" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All records</SelectItem>
                                        <SelectItem value="real">Only real invoices</SelectItem>
                                        <SelectItem value="simulation">Only simulations</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </CardContent>
                    </CollapsibleContent>
                </Collapsible>
            </Card>

            <Card className="min-h-[28rem]">
                <CardHeader className="gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <CardTitle>Invoice list</CardTitle>
                        <CardDescription>
                            Manage invoices, inspect invoice metadata, and remove records when needed.
                        </CardDescription>
                    </div>

                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        {refreshing && (
                            <>
                                <Spinner className="size-4" />
                                Syncing
                            </>
                        )}
                    </div>
                </CardHeader>

                <CardContent className="space-y-4">
                    {loading ? (
                        <div className="flex min-h-[18rem] items-center justify-center rounded-xl border border-dashed">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Spinner />
                                Loading billing invoices...
                            </div>
                        </div>
                    ) : fetchError ? (
                        <div className="flex min-h-[18rem] flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-destructive/40 px-6 text-center">
                            <div className="space-y-1">
                                <p className="font-medium text-destructive">Unable to load invoices</p>
                                <p className="text-sm text-muted-foreground">{fetchError}</p>
                            </div>

                            <Button type="button" variant="outline" onClick={() => void loadInvoices()}>
                                Try again
                            </Button>
                        </div>
                    ) : filteredInvoices.length === 0 ? (
                        <div className="flex min-h-[18rem] flex-col items-center justify-center gap-3 rounded-xl border border-dashed px-6 text-center">
                            <p className="font-medium">
                                {invoices.length === 0 ? 'No billing invoices yet.' : 'No invoices match the current filters.'}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {invoices.length === 0
                                    ? 'Create a real invoice or generate simulation invoices to start populating the list.'
                                    : 'Adjust your filters or search terms to widen the results.'}
                            </p>
                        </div>
                    ) : (
                        <>
                            <div className="hidden overflow-hidden rounded-xl border md:block">
                                <div className="grid grid-cols-[1.2fr_0.8fr_0.9fr_1fr_1fr_1fr_0.9fr] border-b bg-muted/40 px-4 py-3 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    <span>Date</span>
                                    <span>Type</span>
                                    <span>BRL</span>
                                    <span>Customer</span>
                                    <span>External ID</span>
                                    <span>CNAE</span>
                                    <span className="text-right">Actions</span>
                                </div>

                                {paginatedInvoices.map((invoice) => (
                                    <div
                                        key={invoice.id}
                                        className="grid grid-cols-[1.2fr_0.8fr_0.9fr_1fr_1fr_1fr_0.9fr] items-center gap-3 border-b px-4 py-4 text-sm last:border-b-0"
                                    >
                                        <div className="space-y-1">
                                            <p className="font-medium">
                                                {dateFormatter.format(new Date(invoice.billing_date))}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {new Date(invoice.billing_date).toLocaleTimeString('pt-BR', {
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })}
                                            </p>
                                        </div>

                                        <div className="flex flex-col gap-1">
                                            <Badge
                                                variant={normalizeType(invoice.type) === 'international' ? 'secondary' : 'outline'}
                                            >
                                                {normalizeType(invoice.type)}
                                            </Badge>
                                            <Badge variant={invoice.is_simulation ? 'secondary' : 'outline'}>
                                                {invoice.is_simulation ? 'simulation' : 'real'}
                                            </Badge>
                                        </div>

                                        <div className="space-y-1">
                                            <p className="font-medium">
                                                {currencyFormatter.format(invoice.amount_brl)}
                                            </p>
                                            {invoice.amount_usd !== null && (
                                                <p className="text-xs text-muted-foreground">
                                                    USD {invoice.amount_usd.toFixed(2)}
                                                </p>
                                            )}
                                        </div>

                                        <div className="space-y-1">
                                            <p className="font-medium">
                                                {invoice.customer_name ?? '—'}
                                            </p>
                                            {invoice.usd_brl_exchange_rate !== null && (
                                                <p className="text-xs text-muted-foreground">
                                                    FX {invoice.usd_brl_exchange_rate.toFixed(4)}
                                                </p>
                                            )}
                                        </div>

                                        <p className="truncate text-muted-foreground">
                                            {invoice.customer_external_id ?? '—'}
                                        </p>

                                        <div className="space-y-1">
                                            <p>{invoice.cnae ?? '—'}</p>
                                            <p className="text-xs text-muted-foreground">
                                                Annex {invoice.cnae_annex ?? '—'} / Calc {invoice.cnae_calculation ?? '—'}
                                            </p>
                                        </div>

                                        <div className="flex justify-end">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                disabled={invoice.is_simulation}
                                                onClick={() => {
                                                    setSelectedInvoice(invoice);
                                                    setDeleteRealModalOpen(true);
                                                }}
                                            >
                                                <Trash2 />
                                                Delete
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="grid gap-3 md:hidden">
                                {paginatedInvoices.map((invoice) => (
                                    <div
                                        key={invoice.id}
                                        className="rounded-xl border bg-background p-4 shadow-xs"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="space-y-1">
                                                <p className="font-medium">
                                                    {dateFormatter.format(new Date(invoice.billing_date))}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {currencyFormatter.format(invoice.amount_brl)}
                                                </p>
                                            </div>

                                            <div className="flex flex-col items-end gap-1">
                                                <Badge
                                                    variant={normalizeType(invoice.type) === 'international' ? 'secondary' : 'outline'}
                                                >
                                                    {normalizeType(invoice.type)}
                                                </Badge>
                                                <Badge variant={invoice.is_simulation ? 'secondary' : 'outline'}>
                                                    {invoice.is_simulation ? 'simulation' : 'real'}
                                                </Badge>
                                            </div>
                                        </div>

                                        <div className="mt-4 grid gap-2 text-sm">
                                            <InvoiceDetail label="Customer" value={invoice.customer_name ?? '—'} />
                                            <InvoiceDetail label="External ID" value={invoice.customer_external_id ?? '—'} />
                                            <InvoiceDetail label="CNAE" value={invoice.cnae ?? '—'} />
                                            <InvoiceDetail
                                                label="Annex / Calc"
                                                value={`${invoice.cnae_annex ?? '—'} / ${invoice.cnae_calculation ?? '—'}`}
                                            />
                                            {invoice.amount_usd !== null && (
                                                <InvoiceDetail
                                                    label="USD / FX"
                                                    value={`USD ${invoice.amount_usd.toFixed(2)} • FX ${invoice.usd_brl_exchange_rate?.toFixed(4) ?? '—'}`}
                                                />
                                            )}
                                        </div>

                                        <div className="mt-4 flex justify-end">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                disabled={invoice.is_simulation}
                                                onClick={() => {
                                                    setSelectedInvoice(invoice);
                                                    setDeleteRealModalOpen(true);
                                                }}
                                            >
                                                <Trash2 />
                                                Delete
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="flex flex-col gap-3 border-t pt-4 text-sm md:flex-row md:items-center md:justify-between">
                                <p className="text-muted-foreground">
                                    Showing{' '}
                                    <span className="font-medium text-foreground">
                                        {pageStart + 1}
                                    </span>{' '}
                                    to{' '}
                                    <span className="font-medium text-foreground">
                                        {Math.min(pageStart + pageSize, filteredInvoices.length)}
                                    </span>{' '}
                                    of{' '}
                                    <span className="font-medium text-foreground">
                                        {filteredInvoices.length}
                                    </span>{' '}
                                    invoices
                                </p>

                                <div className="flex items-center gap-2 self-end md:self-auto">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={safeCurrentPage === 1}
                                        onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
                                    >
                                        <ChevronLeft />
                                        Previous
                                    </Button>

                                    <span className="min-w-24 text-center text-muted-foreground">
                                        Page {safeCurrentPage} of {totalPages}
                                    </span>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={safeCurrentPage === totalPages}
                                        onClick={() =>
                                            setCurrentPage((page) => Math.min(totalPages, page + 1))
                                        }
                                    >
                                        Next
                                        <ChevronRight />
                                    </Button>
                                </div>
                            </div>
                        </>
                    )}
                </CardContent>
            </Card>

            <Dialog open={realModalOpen} onOpenChange={(open) => (!open ? resetRealModal() : setRealModalOpen(true))}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Create real invoice</DialogTitle>
                        <DialogDescription>
                            Register one non-simulation billing invoice using the existing backend contract.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 md:grid-cols-2">
                        <FormField label="Billing date" error={realErrors.billing_date?.[0]}>
                            <Input
                                type="datetime-local"
                                value={realForm.billing_date}
                                onChange={(event) => updateRealForm('billing_date', event.target.value)}
                            />
                        </FormField>

                        <FormField label="Type" error={realErrors.type?.[0]}>
                            <Select
                                value={realForm.type}
                                onValueChange={(value) => updateRealForm('type', value as 'national' | 'international')}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="national">National</SelectItem>
                                    <SelectItem value="international">International</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField label="CNAE" error={realErrors.cnae?.[0]}>
                            <Input
                                value={realForm.cnae}
                                onChange={(event) => updateRealForm('cnae', event.target.value)}
                                placeholder="6201500"
                            />
                        </FormField>

                        <FormField label="CNAE annex" error={realErrors.cnae_annex?.[0]}>
                            <Select
                                value={realForm.cnae_annex}
                                onValueChange={(value) => updateRealForm('cnae_annex', value as '3' | '5')}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="3">Annex 3</SelectItem>
                                    <SelectItem value="5">Annex 5</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField label="Customer name" error={realErrors.customer_name?.[0]}>
                            <Input
                                value={realForm.customer_name}
                                onChange={(event) => updateRealForm('customer_name', event.target.value)}
                                placeholder="ACME Corp"
                            />
                        </FormField>

                        <FormField label="Customer external ID" error={realErrors.customer_external_id?.[0]}>
                            <Input
                                value={realForm.customer_external_id}
                                onChange={(event) => updateRealForm('customer_external_id', event.target.value)}
                                placeholder="cust_001"
                            />
                        </FormField>

                        <FormField label="Amount BRL" error={realErrors.amount_brl?.[0]}>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={realForm.amount_brl}
                                onChange={(event) => updateRealForm('amount_brl', event.target.value)}
                                placeholder="0.00"
                            />
                        </FormField>

                        {realForm.type === 'international' && (
                            <>
                                <FormField label="Amount USD" error={realErrors.amount_usd?.[0]}>
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={realForm.amount_usd}
                                        onChange={(event) => updateRealForm('amount_usd', event.target.value)}
                                        placeholder="0.00"
                                    />
                                </FormField>

                                <FormField
                                    label="USD/BRL exchange rate"
                                    error={realErrors.usd_brl_exchange_rate?.[0]}
                                >
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.0001"
                                        value={realForm.usd_brl_exchange_rate}
                                        onChange={(event) =>
                                            updateRealForm('usd_brl_exchange_rate', event.target.value)
                                        }
                                        placeholder="0.0000"
                                    />
                                </FormField>
                            </>
                        )}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={resetRealModal}>
                            Cancel
                        </Button>
                        <Button type="button" onClick={() => void submitRealInvoice()} disabled={submittingReal}>
                            {submittingReal && <Spinner />}
                            Save invoice
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={simulationModalOpen}
                onOpenChange={(open) => (!open ? resetSimulationModal() : setSimulationModalOpen(true))}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create simulation invoices</DialogTitle>
                        <DialogDescription>
                            Generate one simulation invoice per month between the selected dates.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4">
                        <FormField label="Type" error={simulationErrors.type?.[0]}>
                            <Select
                                value={simulationForm.type}
                                onValueChange={(value) =>
                                    setSimulationForm((current) => ({
                                        ...current,
                                        type: value as 'national' | 'international',
                                    }))
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="national">National</SelectItem>
                                    <SelectItem value="international">International</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField label="Start date" error={simulationErrors.start_date?.[0]}>
                                <Input
                                    type="date"
                                    value={simulationForm.start_date}
                                    onChange={(event) =>
                                        setSimulationForm((current) => ({
                                            ...current,
                                            start_date: event.target.value,
                                        }))
                                    }
                                />
                            </FormField>

                            <FormField label="End date" error={simulationErrors.end_date?.[0]}>
                                <Input
                                    type="date"
                                    value={simulationForm.end_date}
                                    onChange={(event) =>
                                        setSimulationForm((current) => ({
                                            ...current,
                                            end_date: event.target.value,
                                        }))
                                    }
                                />
                            </FormField>
                        </div>

                        <FormField label="Amount BRL" error={simulationErrors.amount_brl?.[0]}>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={simulationForm.amount_brl}
                                onChange={(event) =>
                                    setSimulationForm((current) => ({
                                        ...current,
                                        amount_brl: event.target.value,
                                    }))
                                }
                                placeholder="0.00"
                            />
                        </FormField>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={resetSimulationModal}>
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            onClick={() => void submitSimulationInvoices()}
                            disabled={submittingSimulation}
                        >
                            {submittingSimulation && <Spinner />}
                            Generate simulations
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={deleteRealModalOpen} onOpenChange={setDeleteRealModalOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete real invoice</DialogTitle>
                        <DialogDescription>
                            This removes the selected real invoice. Simulation invoices cannot be deleted individually from this flow.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="rounded-xl border bg-muted/30 p-4 text-sm">
                        <p className="font-medium">
                            {selectedInvoice?.customer_name ?? 'Unnamed invoice'}
                        </p>
                        <p className="mt-1 text-muted-foreground">
                            {selectedInvoice
                                ? `${dateFormatter.format(new Date(selectedInvoice.billing_date))} • ${currencyFormatter.format(selectedInvoice.amount_brl)}`
                                : 'No invoice selected.'}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setDeleteRealModalOpen(false);
                                setSelectedInvoice(null);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => void deleteRealInvoice()}
                            disabled={deletingReal}
                        >
                            {deletingReal && <Spinner />}
                            Delete invoice
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={deleteSimulationsModalOpen}
                onOpenChange={setDeleteSimulationsModalOpen}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete all simulations</DialogTitle>
                        <DialogDescription>
                            This action permanently removes every simulation invoice currently available in the list.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="rounded-xl border bg-muted/30 p-4 text-sm">
                        <p className="font-medium">
                            {simulationCount} simulation invoice{simulationCount === 1 ? '' : 's'} will be removed.
                        </p>
                        <p className="mt-1 text-muted-foreground">
                            Real invoices stay untouched.
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeleteSimulationsModalOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => void deleteSimulationInvoices()}
                            disabled={deletingSimulations}
                        >
                            {deletingSimulations && <Spinner />}
                            Delete simulations
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function SummaryCard({
    title,
    value,
    hint,
}: {
    title: string;
    value: string;
    hint: string;
}) {
    return (
        <div className="rounded-xl border bg-background/80 p-4 shadow-xs">
            <p className="text-sm text-muted-foreground">{title}</p>
            <p className="mt-2 text-3xl font-semibold tracking-tight">{value}</p>
            <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
        </div>
    );
}

function FormField({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function InvoiceDetail({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="text-muted-foreground">{label}</span>
            <span className="max-w-[60%] truncate text-right font-medium">{value}</span>
        </div>
    );
}
