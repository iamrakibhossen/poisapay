<x-layouts.admin :title="'Compliance'">
    @php
        $canSar = auth('admin')->user()?->can('file-sar') || auth('admin')->user()?->hasRole('super-admin');
    @endphp

    <div
        class="space-y-6"
        x-data="{
            clearingId: null,
            sarId: null,
            closingId: null,
            viewingAlert: @js(request()->query('viewAlert')),
            viewingCase: @js(request()->query('viewCase')),
        }"
    >
        <x-ui.page-header title="Compliance" subtitle="AML alerts, cases and sanctions screening." />

        {{-- Stat cards --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat-card label="Open alerts" :value="number_format($stats['openAlerts'])" icon="bell-alert" accent="amber" />
            <x-ui.stat-card label="Escalated alerts" :value="number_format($stats['escalatedAlerts'])" icon="exclamation-triangle" accent="rose" />
            <x-ui.stat-card label="Open cases" :value="number_format($stats['openCases'])" icon="folder-open" accent="brand" />
            <x-ui.stat-card label="SARs filed" :value="number_format($stats['sarsFiled'])" icon="document-text" accent="emerald" />
        </div>

        {{-- DollarHub shell: sticky vertical section nav + content panel. --}}
        <div class="grid gap-6 lg:grid-cols-5">
            {{-- Vertical section navigation --}}
            <nav class="flex gap-1 overflow-x-auto rounded-xl border border-gray-200 bg-white p-2 lg:sticky lg:top-6 lg:col-span-1 lg:flex-col lg:self-start lg:overflow-visible">
                @foreach (['alerts' => ['label' => 'Alerts', 'icon' => 'bell-alert'], 'cases' => ['label' => 'Cases', 'icon' => 'folder-open'], 'screening' => ['label' => 'Screening', 'icon' => 'shield-check']] as $key => $meta)
                    <a
                        href="{{ route('admin.compliance', ['tab' => $key]) }}"
                        @class([
                            'flex shrink-0 items-center gap-2.5 rounded-lg px-3.5 py-2.5 text-sm font-medium transition lg:w-full',
                            'bg-brand-500 text-ink-900' => $tab === $key,
                            'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900' => $tab !== $key,
                        ])
                    >
                        <x-dynamic-component :component="'heroicon-o-'.$meta['icon']" class="h-5 w-5 shrink-0" />
                        <span>{{ $meta['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            {{-- Active section content --}}
            <div class="space-y-6 lg:col-span-4">

                {{-- Filters (GET form; selects auto-submit) --}}
                <form method="GET" action="{{ route('admin.compliance') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="flex flex-wrap gap-2">
                        @if ($tab === 'alerts')
                            <x-ui.select name="alertStatus" class="w-auto" x-on:change="$el.form.submit()">
                                <option value="all" @selected($alertStatus === 'all')>All statuses</option>
                                <option value="open" @selected($alertStatus === 'open')>Open</option>
                                <option value="escalated" @selected($alertStatus === 'escalated')>Escalated</option>
                                <option value="cleared" @selected($alertStatus === 'cleared')>Cleared</option>
                            </x-ui.select>
                            <x-ui.select name="severity" class="w-auto" x-on:change="$el.form.submit()">
                                <option value="all" @selected($severity === 'all')>All severities</option>
                                @foreach ($severities as $level)
                                    <option value="{{ $level->value }}" @selected($severity === $level->value)>{{ $level->label() }}</option>
                                @endforeach
                            </x-ui.select>
                        @elseif ($tab === 'cases')
                            <x-ui.select name="caseStatus" class="w-auto" x-on:change="$el.form.submit()">
                                <option value="all" @selected($caseStatus === 'all')>All statuses</option>
                                <option value="open" @selected($caseStatus === 'open')>Open</option>
                                <option value="investigating" @selected($caseStatus === 'investigating')>Investigating</option>
                                <option value="closed" @selected($caseStatus === 'closed')>Closed</option>
                            </x-ui.select>
                            <x-ui.select name="riskLevel" class="w-auto" x-on:change="$el.form.submit()">
                                <option value="all" @selected($riskLevel === 'all')>All risk levels</option>
                                @foreach ($severities as $level)
                                    <option value="{{ $level->value }}" @selected($riskLevel === $level->value)>{{ $level->label() }}</option>
                                @endforeach
                            </x-ui.select>
                        @endif
                    </div>

                    <x-ui.input name="search" :value="$search" icon="magnifying-glass" placeholder="Search user name or email…" class="w-full sm:w-72" />
                </form>

                {{-- ALERTS TAB --}}
                @if ($tab === 'alerts')
                    <x-ui.table :headers="['User', 'Type', 'Severity', 'Context', 'Score', 'Status', 'Created', '']">
                        @forelse ($alerts as $alert)
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-ui.avatar :name="$alert->user?->name ?? '?'" size="sm" />
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-neutral-900">{{ $alert->user?->name ?? '—' }}</p>
                                            <p class="truncate text-xs text-neutral-500">{{ $alert->user?->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-sm capitalize text-neutral-700">{{ str_replace('_', ' ', $alert->type) }}</td>
                                <td class="px-3 py-3"><x-ui.badge :color="$alert->severity->color()" dot>{{ $alert->severity->label() }}</x-ui.badge></td>
                                <td class="px-3 py-3 text-sm capitalize text-neutral-600">{{ $alert->context ?? '—' }}</td>
                                <td class="px-3 py-3 tabular text-sm font-semibold text-neutral-900">{{ $alert->score }}</td>
                                <td class="px-3 py-3"><x-ui.badge :color="$alert->status->color()" dot>{{ $alert->status->label() }}</x-ui.badge></td>
                                <td class="px-3 py-3 text-xs text-neutral-500">{{ $alert->created_at?->diffForHumans() }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if ($alert->status !== \App\Enums\AlertStatus::Cleared)
                                            <x-ui.button type="button" x-on:click="clearingId = '{{ $alert->id }}'" variant="success" size="sm" icon="check">Clear</x-ui.button>
                                            @if ($alert->status !== \App\Enums\AlertStatus::Escalated)
                                                <form method="POST" action="{{ route('admin.compliance.alert.escalate', $alert->id) }}" onsubmit="return confirm('Escalate this alert?')">
                                                    @csrf
                                                    <x-ui.button type="submit" variant="danger" size="sm" icon="arrow-trending-up">Escalate</x-ui.button>
                                                </form>
                                            @endif
                                        @endif
                                        <x-ui.button type="button" x-on:click="viewingAlert = '{{ $alert->id }}'" variant="secondary" size="sm" icon="eye">View</x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><x-ui.empty-state icon="bell-alert" title="No alerts" description="No AML alerts match your filters." /></td></tr>
                        @endforelse
                    </x-ui.table>

                    {{ $alerts->links() }}
                @endif

                {{-- CASES TAB --}}
                @if ($tab === 'cases')
                    <x-ui.table :headers="['User', 'Reason', 'Risk', 'Status', 'Alerts', 'SAR', 'Assignee', 'Opened', '']">
                        @forelse ($cases as $case)
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-ui.avatar :name="$case->user?->name ?? '?'" size="sm" />
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-neutral-900">{{ $case->user?->name ?? '—' }}</p>
                                            <p class="truncate text-xs text-neutral-500">{{ $case->user?->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-sm text-neutral-700">{{ $case->reason ?? '—' }}</td>
                                <td class="px-3 py-3"><x-ui.badge :color="$case->risk_level->color()" dot>{{ $case->risk_level->label() }}</x-ui.badge></td>
                                <td class="px-3 py-3"><x-ui.badge :color="$case->status->color()" dot>{{ $case->status->label() }}</x-ui.badge></td>
                                <td class="px-3 py-3 tabular text-sm text-neutral-600">{{ number_format($case->alerts_count) }}</td>
                                <td class="px-3 py-3">
                                    @if ($case->sar_filed)
                                        <x-ui.badge color="indigo" icon="document-text">Filed</x-ui.badge>
                                    @else
                                        <span class="text-xs text-neutral-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-sm text-neutral-600">{{ $case->assignee?->name ?? '—' }}</td>
                                <td class="px-3 py-3 text-xs text-neutral-500">{{ $case->created_at?->diffForHumans() }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if ($case->status !== \App\Enums\CaseStatus::Closed)
                                            <form method="POST" action="{{ route('admin.compliance.alert.assign', $case->id) }}" onsubmit="return confirm('Assign this case to yourself?')">
                                                @csrf
                                                <x-ui.button type="submit" variant="ghost" size="sm" icon="user-plus">Assign to me</x-ui.button>
                                            </form>
                                            @if ($canSar && ! $case->sar_filed)
                                                <x-ui.button type="button" x-on:click="sarId = '{{ $case->id }}'" variant="secondary" size="sm" icon="document-text">File SAR</x-ui.button>
                                            @endif
                                            <x-ui.button type="button" x-on:click="closingId = '{{ $case->id }}'" variant="danger" size="sm" icon="lock-closed">Close</x-ui.button>
                                        @endif
                                        <x-ui.button type="button" x-on:click="viewingCase = '{{ $case->id }}'" variant="secondary" size="sm" icon="eye">View</x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9"><x-ui.empty-state icon="folder-open" title="No cases" description="No compliance cases match your filters." /></td></tr>
                        @endforelse
                    </x-ui.table>

                    {{ $cases->links() }}
                @endif

                {{-- SCREENING TAB --}}
                @if ($tab === 'screening')
                    <x-ui.table :headers="['User', 'Context', 'Result', 'Score', 'Provider', 'Matches', 'Created']">
                        @forelse ($screenings as $screening)
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-ui.avatar :name="$screening->user?->name ?? '?'" size="sm" />
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-neutral-900">{{ $screening->user?->name ?? '—' }}</p>
                                            <p class="truncate text-xs text-neutral-500">{{ $screening->user?->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-sm capitalize text-neutral-600">{{ $screening->context ?? '—' }}</td>
                                <td class="px-3 py-3"><x-ui.badge :color="$screening->result->color()" dot>{{ $screening->result->label() }}</x-ui.badge></td>
                                <td class="px-3 py-3 tabular text-sm font-semibold text-neutral-900">{{ $screening->score }}</td>
                                <td class="px-3 py-3 text-sm text-neutral-600">{{ $screening->provider ?? '—' }}</td>
                                <td class="px-3 py-3 tabular text-sm text-neutral-600">{{ number_format(count($screening->matches ?? [])) }}</td>
                                <td class="px-3 py-3 text-xs text-neutral-500">{{ $screening->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-ui.empty-state icon="shield-check" title="No screenings" description="No sanctions screening results found." /></td></tr>
                        @endforelse
                    </x-ui.table>

                    {{ $screenings->links() }}
                @endif

            </div> {{-- /content panel --}}
        </div> {{-- /compliance grid --}}

        {{-- Clear-alert note modals (Alpine-driven; POST the note to the clear route) --}}
        @if ($tab === 'alerts')
            @foreach ($alerts as $alert)
                @if ($alert->status !== \App\Enums\AlertStatus::Cleared)
                    <div x-show="clearingId === '{{ $alert->id }}'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                        <div class="fixed inset-0 bg-gray-500/60" x-on:click="clearingId = null"></div>
                        <div class="relative w-full max-w-md pp-card p-6" role="dialog" aria-modal="true">
                            <div class="mb-4 flex items-start justify-between">
                                <h3 class="text-lg font-semibold text-neutral-900">Clear alert</h3>
                                <button type="button" x-on:click="clearingId = null" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                            </div>
                            <p class="mb-4 text-sm text-neutral-500">Clearing marks this alert as resolved. Add an optional note explaining the decision.</p>
                            <form method="POST" action="{{ route('admin.compliance.alert.clear', $alert->id) }}" class="space-y-4">
                                @csrf
                                <x-ui.textarea label="Resolution note (optional)" name="clearNote" :rows="3" placeholder="e.g. Reviewed, false positive" :error="$errors->first('clearNote')" />
                                <div class="flex justify-end gap-2">
                                    <x-ui.button type="button" variant="secondary" x-on:click="clearingId = null">Cancel</x-ui.button>
                                    <x-ui.button type="submit" variant="success" icon="check">Clear alert</x-ui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            @endforeach
        @endif

        {{-- File SAR + Close case modals --}}
        @if ($tab === 'cases')
            @foreach ($cases as $case)
                @if ($case->status !== \App\Enums\CaseStatus::Closed)
                    @if ($canSar && ! $case->sar_filed)
                        <div x-show="sarId === '{{ $case->id }}'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                            <div class="fixed inset-0 bg-gray-500/60" x-on:click="sarId = null"></div>
                            <div class="relative w-full max-w-md pp-card p-6" role="dialog" aria-modal="true">
                                <div class="mb-4 flex items-start justify-between">
                                    <h3 class="text-lg font-semibold text-neutral-900">File SAR</h3>
                                    <button type="button" x-on:click="sarId = null" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                                </div>
                                <p class="mb-4 text-sm text-neutral-500">Record a Suspicious Activity Report reference against this case.</p>
                                <form method="POST" action="{{ route('admin.compliance.case.sar', $case->id) }}" class="space-y-4">
                                    @csrf
                                    <x-ui.input label="Reference" name="sarReference" type="text" placeholder="e.g. SAR-2026-0042" :error="$errors->first('sarReference')" />
                                    <x-ui.textarea label="Summary (optional)" name="sarSummary" :rows="3" placeholder="Brief narrative for the filing" :error="$errors->first('sarSummary')" />
                                    <div class="flex justify-end gap-2">
                                        <x-ui.button type="button" variant="secondary" x-on:click="sarId = null">Cancel</x-ui.button>
                                        <x-ui.button type="submit" variant="primary" icon="document-text">File SAR</x-ui.button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                    <div x-show="closingId === '{{ $case->id }}'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                        <div class="fixed inset-0 bg-gray-500/60" x-on:click="closingId = null"></div>
                        <div class="relative w-full max-w-md pp-card p-6" role="dialog" aria-modal="true">
                            <div class="mb-4 flex items-start justify-between">
                                <h3 class="text-lg font-semibold text-neutral-900">Close case</h3>
                                <button type="button" x-on:click="closingId = null" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                            </div>
                            <p class="mb-4 text-sm text-neutral-500">Closing clears any still-open alerts under the same decision. Provide a resolution.</p>
                            <form method="POST" action="{{ route('admin.compliance.case.close', $case->id) }}" class="space-y-4">
                                @csrf
                                <x-ui.textarea label="Resolution" name="closeResolution" :rows="3" placeholder="e.g. No suspicious activity confirmed" :error="$errors->first('closeResolution')" />
                                <div class="flex justify-end gap-2">
                                    <x-ui.button type="button" variant="secondary" x-on:click="closingId = null">Cancel</x-ui.button>
                                    <x-ui.button type="submit" variant="danger" icon="lock-closed">Close case</x-ui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            @endforeach
        @endif

        {{-- Alert detail modals --}}
        @if ($tab === 'alerts')
            @foreach ($alerts as $alert)
                <div x-show="viewingAlert === '{{ $alert->id }}'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-500/60" x-on:click="viewingAlert = null"></div>
                    <div class="relative flex max-h-[85vh] w-full max-w-lg flex-col pp-card p-6" role="dialog" aria-modal="true">
                        <div class="mb-4 flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-semibold capitalize text-neutral-900">{{ str_replace('_', ' ', $alert->type) }}</h3>
                                <p class="text-sm text-neutral-500">{{ $alert->user?->name }} · {{ $alert->user?->email }}</p>
                            </div>
                            <button type="button" x-on:click="viewingAlert = null" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                        </div>

                        <div class="mb-4 flex flex-wrap items-center gap-2">
                            <x-ui.badge :color="$alert->severity->color()" dot>{{ $alert->severity->label() }}</x-ui.badge>
                            <x-ui.badge :color="$alert->status->color()" dot>{{ $alert->status->label() }}</x-ui.badge>
                            <span class="tabular text-xs text-neutral-500">Score {{ $alert->score }}</span>
                            <span class="text-xs text-neutral-400">{{ $alert->created_at?->diffForHumans() }}</span>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto">
                            <dl class="mb-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Context</dt>
                                    <dd class="mt-0.5 capitalize text-neutral-800">{{ $alert->context ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Subject type</dt>
                                    <dd class="mt-0.5 truncate text-neutral-800">{{ $alert->subject_type ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Subject ID</dt>
                                    <dd class="mt-0.5 truncate font-mono text-xs text-neutral-800">{{ $alert->subject_id ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Resolved by</dt>
                                    <dd class="mt-0.5 truncate text-neutral-800">{{ $alert->resolver?->name ?? '—' }}</dd>
                                </div>
                            </dl>

                            @if (! empty($alert->reasons))
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-neutral-500">Reasons</p>
                                <ul class="mb-4 list-inside list-disc space-y-1 text-sm text-neutral-700">
                                    @foreach ($alert->reasons as $reason)
                                        <li>{{ is_array($reason) ? json_encode($reason) : $reason }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            @if ($alert->resolution_note)
                                <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-neutral-700">
                                    <span class="font-semibold">Resolution note:</span> {{ $alert->resolution_note }}
                                </div>
                            @endif

                            @if ($alert->case_id)
                                <div class="mb-2 flex items-center justify-between rounded-lg border border-brand-200 bg-brand-50 px-3 py-2">
                                    <span class="text-sm text-neutral-700">Linked to a compliance case.</span>
                                    <x-ui.button href="{{ route('admin.compliance', ['tab' => 'cases', 'viewCase' => $alert->case_id]) }}" variant="ghost" size="sm" icon="arrow-right">Open case</x-ui.button>
                                </div>
                            @endif
                        </div>

                        <div class="mt-4 flex justify-end gap-2">
                            @if ($alert->status !== \App\Enums\AlertStatus::Cleared)
                                <x-ui.button type="button" x-on:click="viewingAlert = null; clearingId = '{{ $alert->id }}'" variant="success" size="sm" icon="check">Clear</x-ui.button>
                                @if ($alert->status !== \App\Enums\AlertStatus::Escalated)
                                    <form method="POST" action="{{ route('admin.compliance.alert.escalate', $alert->id) }}" onsubmit="return confirm('Escalate this alert?')">
                                        @csrf
                                        <x-ui.button type="submit" variant="danger" size="sm" icon="arrow-trending-up">Escalate</x-ui.button>
                                    </form>
                                @endif
                            @endif
                            <x-ui.button type="button" variant="secondary" x-on:click="viewingAlert = null">Close</x-ui.button>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif

        {{-- Case detail modals --}}
        @if ($tab === 'cases')
            @foreach ($cases as $case)
                <div x-show="viewingCase === '{{ $case->id }}'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-500/60" x-on:click="viewingCase = null"></div>
                    <div class="relative flex max-h-[85vh] w-full max-w-2xl flex-col pp-card p-6" role="dialog" aria-modal="true">
                        <div class="mb-4 flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-neutral-900">{{ $case->reason ?? 'Compliance case' }}</h3>
                                <p class="text-sm text-neutral-500">{{ $case->user?->name }} · {{ $case->user?->email }}</p>
                            </div>
                            <button type="button" x-on:click="viewingCase = null" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                        </div>

                        <div class="mb-4 flex flex-wrap items-center gap-2">
                            <x-ui.badge :color="$case->risk_level->color()" dot>{{ $case->risk_level->label() }}</x-ui.badge>
                            <x-ui.badge :color="$case->status->color()" dot>{{ $case->status->label() }}</x-ui.badge>
                            @if ($case->sar_filed)<x-ui.badge color="indigo" icon="document-text">SAR filed</x-ui.badge>@endif
                            <span class="text-xs text-neutral-400">Opened {{ $case->created_at?->diffForHumans() }}</span>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto">
                            <dl class="mb-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Assignee</dt>
                                    <dd class="mt-0.5 text-neutral-800">{{ $case->assignee?->name ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Opened by</dt>
                                    <dd class="mt-0.5 text-neutral-800">{{ $case->opener?->name ?? '—' }}</dd>
                                </div>
                                @if ($case->sar_reference)
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">SAR reference</dt>
                                        <dd class="mt-0.5 font-mono text-xs text-neutral-800">{{ $case->sar_reference }}</dd>
                                    </div>
                                @endif
                                @if ($case->closed_at)
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Closed</dt>
                                        <dd class="mt-0.5 text-neutral-800">{{ $case->closed_at?->diffForHumans() }}</dd>
                                    </div>
                                @endif
                            </dl>

                            @if ($case->summary)
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-neutral-500">Summary</p>
                                <p class="mb-4 text-sm text-neutral-700">{{ $case->summary }}</p>
                            @endif

                            @if ($case->resolution)
                                <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-neutral-700">
                                    <span class="font-semibold">Resolution:</span> {{ $case->resolution }}
                                </div>
                            @endif

                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-neutral-500">Alerts</p>
                            <x-ui.table :headers="['Type', 'Severity', 'Status', 'Date']">
                                @forelse ($case->alerts()->latest()->get() as $a)
                                    <tr class="border-b border-gray-200 hover:bg-gray-100">
                                        <td class="px-3 py-2.5 text-sm capitalize text-neutral-700">{{ str_replace('_', ' ', $a->type) }}</td>
                                        <td class="px-3 py-2.5"><x-ui.badge :color="$a->severity->color()">{{ $a->severity->label() }}</x-ui.badge></td>
                                        <td class="px-3 py-2.5"><x-ui.badge :color="$a->status->color()">{{ $a->status->label() }}</x-ui.badge></td>
                                        <td class="px-3 py-2.5 text-xs text-neutral-500">{{ $a->created_at?->diffForHumans() }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4"><x-ui.empty-state icon="bell-alert" title="No alerts" description="No alerts linked to this case." /></td></tr>
                                @endforelse
                            </x-ui.table>
                        </div>

                        <div class="mt-4 flex flex-wrap justify-end gap-2">
                            @if ($case->status !== \App\Enums\CaseStatus::Closed)
                                <form method="POST" action="{{ route('admin.compliance.alert.assign', $case->id) }}" onsubmit="return confirm('Assign this case to yourself?')">
                                    @csrf
                                    <x-ui.button type="submit" variant="ghost" size="sm" icon="user-plus">Assign to me</x-ui.button>
                                </form>
                                @if ($canSar && ! $case->sar_filed)
                                    <x-ui.button type="button" x-on:click="viewingCase = null; sarId = '{{ $case->id }}'" variant="secondary" size="sm" icon="document-text">File SAR</x-ui.button>
                                @endif
                                <x-ui.button type="button" x-on:click="viewingCase = null; closingId = '{{ $case->id }}'" variant="danger" size="sm" icon="lock-closed">Close</x-ui.button>
                            @endif
                            <x-ui.button type="button" variant="secondary" x-on:click="viewingCase = null">Close</x-ui.button>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</x-layouts.admin>
