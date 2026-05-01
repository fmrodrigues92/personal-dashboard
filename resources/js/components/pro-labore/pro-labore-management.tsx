import { startTransition, useDeferredValue, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { ProLaboreEntry } from '@/pages/pro-labore';
import { cn } from '@/lib/utils';
import {
    Banknote,
    Calculator,
    ChevronLeft,
    ChevronRight,
    Plus,
    RefreshCw,
    Search,
    Sparkles,
    Trash2,
    WalletCards,
} from 'lucide-react';

type EntriesResponse = {
    data: ProLaboreEntry[];
};

type SimulationCreateResponse = {
    data: ProLaboreEntry[];
    meta?: {
        created_count?: number;
    };
};

type ApiError = {
    message?: string;
    errors?: Record<string, string[]>;
};

type ReceiptForm = {
    reference_month: string;
    gross_amount_brl: string;
    notes: string;
};

type SimulationForm = {
    start_month: string;
    end_month: string;
};

const pageSize = 10;

const currencyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

const monthFormatter = new Intl.DateTimeFormat('pt-BR', {
    month: 'short',
    year: 'numeric',
    timeZone: 'UTC',
});

function defaultReceiptForm(): ReceiptForm {
    return {
        reference_month: '',
        gross_amount_brl: '',
        notes: '',
    };
}

function defaultSimulationForm(): SimulationForm {
    return {
        start_month: '',
        end_month: '',
    };
}

function readCookie(name: string): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const target = document.cookie
        .split('; ')
        .find((item) => item.startsWith(`${name}=`));

    return target
        ? decodeURIComponent(target.split('=').slice(1).join('='))
        : null;
}

async function proLaboreApi<T>(path: string, init?: RequestInit): Promise<T> {
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
        const errorBody = (await response
            .json()
            .catch(() => null)) as ApiError | null;
        const error = new Error(
            errorBody?.message ?? 'Unable to complete the request.',
        );
        (error as Error & { details?: ApiError; status?: number }).details =
            errorBody ?? undefined;
        (error as Error & { details?: ApiError; status?: number }).status =
            response.status;
        throw error;
    }

    return (await response.json()) as T;
}

function monthInputToDate(value: string): string {
    return value ? `${value}-01` : '';
}

function entryMatchesMonthFrom(
    entry: ProLaboreEntry,
    monthFrom: string,
): boolean {
    if (!monthFrom) {
        return true;
    }

    return entry.reference_month >= monthInputToDate(monthFrom);
}

function entryMatchesMonthTo(entry: ProLaboreEntry, monthTo: string): boolean {
    if (!monthTo) {
        return true;
    }

    return entry.reference_month <= monthInputToDate(monthTo);
}

function formatMonth(referenceMonth: string): string {
    return monthFormatter.format(new Date(referenceMonth));
}

export default function ProLaboreManagement({
    initialEntries = [],
}: {
    initialEntries?: ProLaboreEntry[];
}) {
    const [entries, setEntries] = useState<ProLaboreEntry[]>(initialEntries);
    const [loading, setLoading] = useState(initialEntries.length === 0);
    const [refreshing, setRefreshing] = useState(false);
    const [fetchError, setFetchError] = useState<string | null>(null);
    const [currentPage, setCurrentPage] = useState(1);
    const [search, setSearch] = useState('');
    const deferredSearch = useDeferredValue(search);
    const [monthFrom, setMonthFrom] = useState('');
    const [monthTo, setMonthTo] = useState('');
    const [statusFilter, setStatusFilter] = useState<
        'all' | 'real' | 'simulation'
    >('all');
    const [receiptModalOpen, setReceiptModalOpen] = useState(false);
    const [simulationModalOpen, setSimulationModalOpen] = useState(false);
    const [deleteReceiptModalOpen, setDeleteReceiptModalOpen] = useState(false);
    const [deleteSimulationsModalOpen, setDeleteSimulationsModalOpen] =
        useState(false);
    const [selectedEntry, setSelectedEntry] = useState<ProLaboreEntry | null>(
        null,
    );
    const [receiptForm, setReceiptForm] =
        useState<ReceiptForm>(defaultReceiptForm);
    const [simulationForm, setSimulationForm] = useState<SimulationForm>(
        defaultSimulationForm,
    );
    const [receiptErrors, setReceiptErrors] = useState<
        Record<string, string[]>
    >({});
    const [simulationErrors, setSimulationErrors] = useState<
        Record<string, string[]>
    >({});
    const [submittingReceipt, setSubmittingReceipt] = useState(false);
    const [submittingSimulation, setSubmittingSimulation] = useState(false);
    const [deletingReceipt, setDeletingReceipt] = useState(false);
    const [deletingSimulations, setDeletingSimulations] = useState(false);

    async function loadEntries(silent = false) {
        if (silent) {
            setRefreshing(true);
        } else {
            setLoading(true);
        }

        setFetchError(null);

        try {
            const response = await proLaboreApi<EntriesResponse>('/pro-labore');

            startTransition(() => {
                setEntries(response.data);
            });
        } catch (error) {
            setFetchError(
                error instanceof Error
                    ? error.message
                    : 'Unable to load pro-labore entries.',
            );
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }

    useEffect(() => {
        if (initialEntries.length === 0) {
            return;
        }

        startTransition(() => {
            setEntries(initialEntries);
        });

        setLoading(false);
        setRefreshing(false);
    }, [initialEntries, initialEntries.length]);

    useEffect(() => {
        if (initialEntries.length === 0) {
            void loadEntries();
        }
    }, [initialEntries.length]);

    useEffect(() => {
        setCurrentPage(1);
    }, [deferredSearch, monthFrom, monthTo, statusFilter]);

    const normalizedSearch = deferredSearch.trim().toLowerCase();
    const filteredEntries = entries.filter((entry) => {
        if (!entryMatchesMonthFrom(entry, monthFrom)) {
            return false;
        }

        if (!entryMatchesMonthTo(entry, monthTo)) {
            return false;
        }

        if (statusFilter === 'real' && entry.is_simulation) {
            return false;
        }

        if (statusFilter === 'simulation' && !entry.is_simulation) {
            return false;
        }

        if (normalizedSearch) {
            const searchable = [
                entry.reference_month,
                entry.notes ?? '',
                entry.source ?? '',
            ]
                .join(' ')
                .toLowerCase();

            return searchable.includes(normalizedSearch);
        }

        return true;
    });

    const totalPages = Math.max(
        1,
        Math.ceil(filteredEntries.length / pageSize),
    );
    const safeCurrentPage = Math.min(currentPage, totalPages);
    const pageStart = (safeCurrentPage - 1) * pageSize;
    const paginatedEntries = filteredEntries.slice(
        pageStart,
        pageStart + pageSize,
    );
    const simulationCount = entries.filter(
        (entry) => entry.is_simulation,
    ).length;
    const realCount = entries.length - simulationCount;
    const totalGross = filteredEntries.reduce(
        (total, entry) => total + entry.gross_amount_brl,
        0,
    );
    const projectedGross = filteredEntries
        .filter((entry) => entry.is_simulation)
        .reduce((total, entry) => total + entry.gross_amount_brl, 0);

    function resetReceiptModal() {
        setReceiptForm(defaultReceiptForm());
        setReceiptErrors({});
        setReceiptModalOpen(false);
    }

    function resetSimulationModal() {
        setSimulationForm(defaultSimulationForm());
        setSimulationErrors({});
        setSimulationModalOpen(false);
    }

    async function submitReceipt() {
        setSubmittingReceipt(true);
        setReceiptErrors({});

        try {
            await proLaboreApi('/pro-labore', {
                method: 'POST',
                body: JSON.stringify({
                    reference_month: monthInputToDate(
                        receiptForm.reference_month,
                    ),
                    gross_amount_brl: Number(receiptForm.gross_amount_brl),
                    notes: receiptForm.notes || null,
                }),
            });

            toast.success('Pro-labore receipt created.');
            resetReceiptModal();
            await loadEntries(true);
        } catch (error) {
            const details = (error as Error & { details?: ApiError }).details;
            if (details?.errors) {
                setReceiptErrors(details.errors);
            }

            toast.error(
                details?.message ?? 'Unable to create pro-labore receipt.',
            );
        } finally {
            setSubmittingReceipt(false);
        }
    }

    async function submitSimulations() {
        setSubmittingSimulation(true);
        setSimulationErrors({});

        try {
            const response = await proLaboreApi<SimulationCreateResponse>(
                '/pro-labore/simulations',
                {
                    method: 'POST',
                    body: JSON.stringify({
                        start_month: monthInputToDate(
                            simulationForm.start_month,
                        ),
                        end_month: monthInputToDate(simulationForm.end_month),
                    }),
                },
            );

            toast.success(
                response.meta?.created_count
                    ? `${response.meta.created_count} pro-labore simulation${response.meta.created_count === 1 ? '' : 's'} generated.`
                    : 'No billing simulations found for the selected period.',
            );
            resetSimulationModal();
            await loadEntries(true);
        } catch (error) {
            const details = (error as Error & { details?: ApiError }).details;
            if (details?.errors) {
                setSimulationErrors(details.errors);
            }

            toast.error(
                details?.message ??
                    'Unable to generate pro-labore simulations.',
            );
        } finally {
            setSubmittingSimulation(false);
        }
    }

    async function deleteReceipt() {
        if (!selectedEntry || selectedEntry.is_simulation) {
            return;
        }

        setDeletingReceipt(true);

        try {
            await proLaboreApi(`/pro-labore/${selectedEntry.id}`, {
                method: 'DELETE',
            });

            toast.success('Pro-labore receipt deleted.');
            setSelectedEntry(null);
            setDeleteReceiptModalOpen(false);
            await loadEntries(true);
        } catch (error) {
            const details = (error as Error & { details?: ApiError }).details;
            toast.error(
                details?.message ?? 'Unable to delete pro-labore receipt.',
            );
        } finally {
            setDeletingReceipt(false);
        }
    }

    async function deleteSimulations() {
        setDeletingSimulations(true);

        try {
            const response = await proLaboreApi<{
                meta?: { deleted_count?: number };
            }>('/pro-labore/simulations', {
                method: 'DELETE',
            });

            toast.success(
                response.meta?.deleted_count
                    ? `${response.meta.deleted_count} pro-labore simulation${response.meta.deleted_count === 1 ? '' : 's'} deleted.`
                    : 'Pro-labore simulations deleted.',
            );
            setDeleteSimulationsModalOpen(false);
            await loadEntries(true);
        } catch (error) {
            const details = (error as Error & { details?: ApiError }).details;
            toast.error(
                details?.message ?? 'Unable to delete pro-labore simulations.',
            );
        } finally {
            setDeletingSimulations(false);
        }
    }

    return (
        <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <section className="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 bg-card p-6 shadow-sm dark:border-sidebar-border">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-3">
                        <Badge
                            variant="outline"
                            className="gap-2 rounded-full px-3 py-1 text-xs"
                        >
                            <WalletCards className="size-3.5" />
                            Pro-labore
                        </Badge>

                        <Heading
                            title="Pro-labore"
                            description="Register past receipts and project future gross pro-labore from billing simulations."
                        />
                    </div>

                    <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setSimulationModalOpen(true)}
                        >
                            <Sparkles />
                            Generate simulations
                        </Button>

                        <Button
                            type="button"
                            onClick={() => setReceiptModalOpen(true)}
                        >
                            <Plus />
                            New receipt
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        title="Filtered gross"
                        value={currencyFormatter.format(totalGross)}
                        hint="Real and simulated entries"
                    />
                    <SummaryCard
                        title="Projected gross"
                        value={currencyFormatter.format(projectedGross)}
                        hint="Redis simulations in current view"
                    />
                    <SummaryCard
                        title="Real receipts"
                        value={String(realCount)}
                        hint="Stored in PostgreSQL"
                    />
                    <SummaryCard
                        title="Simulations"
                        value={String(simulationCount)}
                        hint="Stored in Redis"
                    />
                </div>
            </section>

            <Card>
                <CardHeader className="gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>
                            Narrow entries by reference month, status, and
                            notes.
                        </CardDescription>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => void loadEntries(true)}
                        >
                            <RefreshCw
                                className={cn(refreshing && 'animate-spin')}
                            />
                            Refresh
                        </Button>

                        {simulationCount > 0 && (
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() =>
                                    setDeleteSimulationsModalOpen(true)
                                }
                            >
                                <Trash2 />
                                Delete simulations
                            </Button>
                        )}
                    </div>
                </CardHeader>

                <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <div className="grid gap-2 xl:col-span-2">
                        <Label htmlFor="pro-labore-search">Search</Label>
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="pro-labore-search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Month, note, or source"
                                className="pl-9"
                            />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="pro-labore-month-from">
                            Month from
                        </Label>
                        <Input
                            id="pro-labore-month-from"
                            type="month"
                            value={monthFrom}
                            onChange={(event) =>
                                setMonthFrom(event.target.value)
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="pro-labore-month-to">Month to</Label>
                        <Input
                            id="pro-labore-month-to"
                            type="month"
                            value={monthTo}
                            onChange={(event) => setMonthTo(event.target.value)}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="pro-labore-status">Status</Label>
                        <select
                            id="pro-labore-status"
                            value={statusFilter}
                            onChange={(event) =>
                                setStatusFilter(
                                    event.target.value as
                                        | 'all'
                                        | 'real'
                                        | 'simulation',
                                )
                            }
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="all">All entries</option>
                            <option value="real">Only real receipts</option>
                            <option value="simulation">Only simulations</option>
                        </select>
                    </div>
                </CardContent>
            </Card>

            <Card className="min-h-[28rem]">
                <CardHeader className="gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <CardTitle>Entries</CardTitle>
                        <CardDescription>
                            Review gross pro-labore receipts and simulation
                            output.
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
                                Loading pro-labore entries...
                            </div>
                        </div>
                    ) : fetchError ? (
                        <div className="flex min-h-[18rem] flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-destructive/40 px-6 text-center">
                            <div className="space-y-1">
                                <p className="font-medium text-destructive">
                                    Unable to load pro-labore
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {fetchError}
                                </p>
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => void loadEntries()}
                            >
                                Try again
                            </Button>
                        </div>
                    ) : filteredEntries.length === 0 ? (
                        <div className="flex min-h-[18rem] flex-col items-center justify-center gap-3 rounded-xl border border-dashed px-6 text-center">
                            <Banknote className="size-8 text-muted-foreground" />
                            <p className="font-medium">
                                {entries.length === 0
                                    ? 'No pro-labore entries yet.'
                                    : 'No entries match the current filters.'}
                            </p>
                            <p className="max-w-md text-sm text-muted-foreground">
                                {entries.length === 0
                                    ? 'Create a receipt or generate simulations from billing projections.'
                                    : 'Adjust month filters, search, or status to widen the results.'}
                            </p>
                        </div>
                    ) : (
                        <>
                            <div className="hidden overflow-hidden rounded-xl border md:block">
                                <div className="grid grid-cols-[1fr_1fr_1fr_1.2fr_0.8fr] border-b bg-muted/40 px-4 py-3 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    <span>Month</span>
                                    <span>Gross amount</span>
                                    <span>Source revenue</span>
                                    <span>Notes / Source</span>
                                    <span className="text-right">Actions</span>
                                </div>

                                {paginatedEntries.map((entry) => (
                                    <div
                                        key={`${entry.is_simulation ? 'simulation' : 'real'}-${entry.id}`}
                                        className="grid grid-cols-[1fr_1fr_1fr_1.2fr_0.8fr] items-center gap-3 border-b px-4 py-4 text-sm last:border-b-0"
                                    >
                                        <div className="space-y-1">
                                            <p className="font-medium">
                                                {formatMonth(
                                                    entry.reference_month,
                                                )}
                                            </p>
                                            <Badge
                                                variant={
                                                    entry.is_simulation
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {entry.is_simulation
                                                    ? 'simulation'
                                                    : 'real'}
                                            </Badge>
                                        </div>

                                        <p className="font-medium">
                                            {currencyFormatter.format(
                                                entry.gross_amount_brl,
                                            )}
                                        </p>

                                        <p className="text-muted-foreground">
                                            {entry.source_revenue_brl === null
                                                ? '—'
                                                : currencyFormatter.format(
                                                      entry.source_revenue_brl,
                                                  )}
                                        </p>

                                        <div className="space-y-1">
                                            <p>{entry.notes ?? '—'}</p>
                                            {entry.source && (
                                                <p className="text-xs text-muted-foreground">
                                                    {entry.source.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </p>
                                            )}
                                        </div>

                                        <div className="flex justify-end">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                disabled={entry.is_simulation}
                                                onClick={() => {
                                                    setSelectedEntry(entry);
                                                    setDeleteReceiptModalOpen(
                                                        true,
                                                    );
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
                                {paginatedEntries.map((entry) => (
                                    <div
                                        key={`${entry.is_simulation ? 'simulation' : 'real'}-${entry.id}`}
                                        className="rounded-xl border bg-background p-4 shadow-xs"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-medium">
                                                    {formatMonth(
                                                        entry.reference_month,
                                                    )}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {currencyFormatter.format(
                                                        entry.gross_amount_brl,
                                                    )}
                                                </p>
                                            </div>

                                            <Badge
                                                variant={
                                                    entry.is_simulation
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {entry.is_simulation
                                                    ? 'simulation'
                                                    : 'real'}
                                            </Badge>
                                        </div>

                                        <div className="mt-4 grid gap-2 text-sm">
                                            <EntryDetail
                                                label="Source revenue"
                                                value={
                                                    entry.source_revenue_brl ===
                                                    null
                                                        ? '—'
                                                        : currencyFormatter.format(
                                                              entry.source_revenue_brl,
                                                          )
                                                }
                                            />
                                            <EntryDetail
                                                label="Notes"
                                                value={entry.notes ?? '—'}
                                            />
                                            <EntryDetail
                                                label="Source"
                                                value={
                                                    entry.source?.replaceAll(
                                                        '_',
                                                        ' ',
                                                    ) ?? '—'
                                                }
                                            />
                                        </div>

                                        <div className="mt-4 flex justify-end">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                disabled={entry.is_simulation}
                                                onClick={() => {
                                                    setSelectedEntry(entry);
                                                    setDeleteReceiptModalOpen(
                                                        true,
                                                    );
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
                                        {Math.min(
                                            pageStart + pageSize,
                                            filteredEntries.length,
                                        )}
                                    </span>{' '}
                                    of{' '}
                                    <span className="font-medium text-foreground">
                                        {filteredEntries.length}
                                    </span>{' '}
                                    entries
                                </p>

                                <div className="flex items-center gap-2 self-end md:self-auto">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={safeCurrentPage === 1}
                                        onClick={() =>
                                            setCurrentPage((page) =>
                                                Math.max(1, page - 1),
                                            )
                                        }
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
                                        disabled={
                                            safeCurrentPage === totalPages
                                        }
                                        onClick={() =>
                                            setCurrentPage((page) =>
                                                Math.min(totalPages, page + 1),
                                            )
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

            <Dialog
                open={receiptModalOpen}
                onOpenChange={(open) =>
                    !open ? resetReceiptModal() : setReceiptModalOpen(true)
                }
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create real receipt</DialogTitle>
                        <DialogDescription>
                            Register a past gross pro-labore receipt without
                            INSS or IRPF calculations.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4">
                        <FormField
                            label="Reference month"
                            error={receiptErrors.reference_month?.[0]}
                        >
                            <Input
                                type="month"
                                value={receiptForm.reference_month}
                                onChange={(event) =>
                                    setReceiptForm((current) => ({
                                        ...current,
                                        reference_month: event.target.value,
                                    }))
                                }
                            />
                        </FormField>

                        <FormField
                            label="Gross amount BRL"
                            error={receiptErrors.gross_amount_brl?.[0]}
                        >
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={receiptForm.gross_amount_brl}
                                onChange={(event) =>
                                    setReceiptForm((current) => ({
                                        ...current,
                                        gross_amount_brl: event.target.value,
                                    }))
                                }
                                placeholder="0.00"
                            />
                        </FormField>

                        <FormField
                            label="Notes"
                            error={receiptErrors.notes?.[0]}
                        >
                            <Input
                                value={receiptForm.notes}
                                onChange={(event) =>
                                    setReceiptForm((current) => ({
                                        ...current,
                                        notes: event.target.value,
                                    }))
                                }
                                placeholder="Optional note"
                            />
                        </FormField>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={resetReceiptModal}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            onClick={() => void submitReceipt()}
                            disabled={submittingReceipt}
                        >
                            {submittingReceipt && <Spinner />}
                            Save receipt
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={simulationModalOpen}
                onOpenChange={(open) =>
                    !open
                        ? resetSimulationModal()
                        : setSimulationModalOpen(true)
                }
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Generate simulations</DialogTitle>
                        <DialogDescription>
                            Estimate gross pro-labore as 28% of simulated
                            billing revenue, rounded up to the next higher ten.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Start month"
                            error={simulationErrors.start_month?.[0]}
                        >
                            <Input
                                type="month"
                                value={simulationForm.start_month}
                                onChange={(event) =>
                                    setSimulationForm((current) => ({
                                        ...current,
                                        start_month: event.target.value,
                                    }))
                                }
                            />
                        </FormField>

                        <FormField
                            label="End month"
                            error={simulationErrors.end_month?.[0]}
                        >
                            <Input
                                type="month"
                                value={simulationForm.end_month}
                                onChange={(event) =>
                                    setSimulationForm((current) => ({
                                        ...current,
                                        end_month: event.target.value,
                                    }))
                                }
                            />
                        </FormField>
                    </div>

                    <div className="rounded-xl border bg-muted/30 p-4 text-sm">
                        <div className="flex gap-3">
                            <Calculator className="mt-0.5 size-4 text-muted-foreground" />
                            <p className="text-muted-foreground">
                                Simulations are read from billing projections in
                                Redis and saved back to Redis as pro-labore
                                projections.
                            </p>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={resetSimulationModal}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            onClick={() => void submitSimulations()}
                            disabled={submittingSimulation}
                        >
                            {submittingSimulation && <Spinner />}
                            Generate
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={deleteReceiptModalOpen}
                onOpenChange={setDeleteReceiptModalOpen}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete receipt</DialogTitle>
                        <DialogDescription>
                            This soft deletes the selected real pro-labore
                            receipt.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="rounded-xl border bg-muted/30 p-4 text-sm">
                        <p className="font-medium">
                            {selectedEntry
                                ? formatMonth(selectedEntry.reference_month)
                                : 'No receipt selected'}
                        </p>
                        <p className="mt-1 text-muted-foreground">
                            {selectedEntry
                                ? currencyFormatter.format(
                                      selectedEntry.gross_amount_brl,
                                  )
                                : 'Select a receipt to continue.'}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setDeleteReceiptModalOpen(false);
                                setSelectedEntry(null);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => void deleteReceipt()}
                            disabled={deletingReceipt}
                        >
                            {deletingReceipt && <Spinner />}
                            Delete receipt
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
                        <DialogTitle>Delete simulations</DialogTitle>
                        <DialogDescription>
                            This clears generated pro-labore simulations from
                            Redis. Real receipts stay untouched.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="rounded-xl border bg-muted/30 p-4 text-sm">
                        <p className="font-medium">
                            {simulationCount} simulation
                            {simulationCount === 1 ? '' : 's'} will be removed.
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
                            onClick={() => void deleteSimulations()}
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
            <p className="mt-2 text-2xl font-semibold tracking-tight">
                {value}
            </p>
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
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function EntryDetail({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-start justify-between gap-3">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{value}</span>
        </div>
    );
}
