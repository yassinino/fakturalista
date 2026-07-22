<template>
  <div class="dt-shell">

    <!-- ── Toolbar ───────────────────────────────────────────── -->
    <div class="dt-toolbar">
      <div class="dt-toolbar-left">
        <div class="dt-search-wrap">
          <i class="fa fa-search dt-search-icon"></i>
          <input
            class="dt-search"
            type="search"
            :placeholder="searchPlaceholder"
            v-model="searchInput"
            autocomplete="off"
          />
        </div>
        <div v-if="statusOptions.length" class="dt-status-pills">
          <button
            class="dt-pill"
            :class="{ 'dt-pill--active': activeStatus === '' }"
            @click="setStatus('')"
          >All</button>
          <button
            v-for="opt in statusOptions"
            :key="opt.value"
            class="dt-pill"
            :class="{ 'dt-pill--active': activeStatus === opt.value }"
            @click="setStatus(opt.value)"
          >{{ opt.label }}</button>
        </div>
      </div>

      <div class="dt-toolbar-right">
        <template v-if="showDateFilter">
          <input class="dt-date" type="date" v-model="dateFrom" @change="onDateChange" title="From date" />
          <span class="dt-date-sep">–</span>
          <input class="dt-date" type="date" v-model="dateTo" @change="onDateChange" title="To date" />
        </template>
        <button v-if="hasActiveFilters" class="dt-clear-btn" @click="clearFilters">
          <i class="fa fa-times me-1"></i>Clear
        </button>
      </div>
    </div>

    <!-- ── Table ─────────────────────────────────────────────── -->
    <div class="dt-table-wrap">
      <table class="dt-table">
        <thead>
          <slot name="head" :sort-field="sortField" :sort-dir="sortDir" :set-sort="setSort" />
        </thead>
        <tbody>
          <slot name="body" :items="displayRows" />
          <tr v-if="!displayRows.length" class="dt-empty-row">
            <td colspan="20" class="dt-empty-cell">
              <i class="fa fa-inbox"></i>
              <span>No records found</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Footer: showing + pages + per-page ────────────────── -->
    <div v-if="totalCount > 0" class="dt-footer">
      <div class="dt-showing">
        Showing <strong>{{ from }}</strong>–<strong>{{ to }}</strong> of <strong>{{ totalCount }}</strong>
      </div>

      <div class="dt-pages">
        <button class="dt-pg-btn" :disabled="currentPage <= 1" @click="goPage(currentPage - 1)" aria-label="Previous">
          <i class="fa fa-chevron-left"></i>
        </button>
        <template v-for="n in pageWindow" :key="n + '-' + currentPage">
          <span v-if="n === '…'" class="dt-pg-ellipsis">…</span>
          <button
            v-else
            class="dt-pg-btn"
            :class="{ 'dt-pg-btn--active': n === currentPage }"
            @click="goPage(n)"
          >{{ n }}</button>
        </template>
        <button class="dt-pg-btn" :disabled="currentPage >= lastPage" @click="goPage(currentPage + 1)" aria-label="Next">
          <i class="fa fa-chevron-right"></i>
        </button>
      </div>

      <div class="dt-perpage-wrap">
        <select class="dt-perpage" :value="currentPerPage" @change="onPerPageChange(+$event.target.value)">
          <option :value="10">10 / page</option>
          <option :value="25">25 / page</option>
          <option :value="50">50 / page</option>
        </select>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
  rows:              { type: Array,   default: () => [] },
  serverSide:        { type: Boolean, default: false },
  meta:              { type: Object,  default: () => ({ current_page: 1, last_page: 1, per_page: 10, total: 0 }) },
  statusOptions:     { type: Array,   default: () => [] },
  searchPlaceholder: { type: String,  default: 'Search…' },
  searchFields:      { type: Array,   default: () => ['reference', 'customer'] },
  showDateFilter:    { type: Boolean, default: true },
});

const emit = defineEmits(['filter', 'page', 'perPage']);

// ── Filter state ──────────────────────────────────────────────
const searchInput  = ref('');
const searchValue  = ref('');   // debounced (server-side) or live (client-side)
const activeStatus = ref('');
const dateFrom     = ref('');
const dateTo       = ref('');

const hasActiveFilters = computed(() =>
  !!searchValue.value || !!activeStatus.value || !!dateFrom.value || !!dateTo.value
);

// Debounce search for server-side; instant for client-side
let searchTimer = null;
watch(searchInput, (val) => {
  if (!props.serverSide) {
    searchValue.value = val;
    clientPage.value  = 1;
    return;
  }
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    searchValue.value = val;
    clientPage.value  = 1;
    emitFilter();
  }, 320);
});

function setStatus(val) {
  activeStatus.value = val;
  clientPage.value   = 1;
  if (props.serverSide) emitFilter();
}

function onDateChange() {
  clientPage.value = 1;
  if (props.serverSide) emitFilter();
}

function clearFilters() {
  searchInput.value  = '';
  searchValue.value  = '';
  activeStatus.value = '';
  dateFrom.value     = '';
  dateTo.value       = '';
  clientPage.value   = 1;
  if (props.serverSide) emitFilter();
}

// ── Sort state ────────────────────────────────────────────────
const sortField = ref('');
const sortDir   = ref('desc');

function setSort(field) {
  if (sortField.value === field) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortField.value = field;
    sortDir.value   = 'asc';
  }
  clientPage.value = 1;
  if (props.serverSide) emitFilter();
}

function emitFilter() {
  emit('filter', {
    search:   searchValue.value,
    status:   activeStatus.value,
    date_from: dateFrom.value,
    date_to:   dateTo.value,
    sort_by:  sortField.value,
    sort_dir: sortDir.value,
  });
}

// ── Pagination state ──────────────────────────────────────────
const clientPage    = ref(1);
const clientPerPage = ref(10);

const currentPage = computed({
  get: () => props.serverSide ? (props.meta?.current_page ?? 1) : clientPage.value,
  set: (v) => { if (!props.serverSide) clientPage.value = v; },
});

const currentPerPage = computed({
  get: () => props.serverSide ? (props.meta?.per_page ?? 10) : clientPerPage.value,
  set: (v) => { if (!props.serverSide) clientPerPage.value = v; },
});

// Sync clientPerPage from server meta when it changes
watch(() => props.meta, (m) => {
  if (props.serverSide && m) {
    clientPerPage.value = m.per_page ?? 10;
  }
}, { immediate: true, deep: true });

// ── Computed filtered + sorted rows (client-side only) ────────
const filteredRows = computed(() => {
  if (props.serverSide) return props.rows;

  let result = props.rows;

  if (searchValue.value) {
    const q = searchValue.value.toLowerCase();
    result = result.filter(row =>
      props.searchFields.some(f => String(row[f] ?? '').toLowerCase().includes(q))
    );
  }

  if (activeStatus.value) {
    result = result.filter(row => row.status === activeStatus.value);
  }

  if (dateFrom.value) {
    result = result.filter(row => (row.date ?? '') >= dateFrom.value);
  }

  if (dateTo.value) {
    result = result.filter(row => (row.date ?? '') <= dateTo.value);
  }

  if (sortField.value) {
    const f   = sortField.value;
    const dir = sortDir.value === 'asc' ? 1 : -1;
    result = [...result].sort((a, b) => {
      const av = a[f] ?? '';
      const bv = b[f] ?? '';
      return av < bv ? -dir : av > bv ? dir : 0;
    });
  }

  return result;
});

const totalCount = computed(() =>
  props.serverSide ? (props.meta?.total ?? 0) : filteredRows.value.length
);

const lastPage = computed(() =>
  props.serverSide
    ? (props.meta?.last_page ?? 1)
    : Math.max(1, Math.ceil(totalCount.value / clientPerPage.value))
);

const from = computed(() =>
  totalCount.value === 0
    ? 0
    : (currentPage.value - 1) * currentPerPage.value + 1
);

const to = computed(() =>
  Math.min(currentPage.value * currentPerPage.value, totalCount.value)
);

const displayRows = computed(() => {
  if (props.serverSide) return props.rows;
  const start = (clientPage.value - 1) * clientPerPage.value;
  return filteredRows.value.slice(start, start + clientPerPage.value);
});

// Ellipsis-aware page window
const pageWindow = computed(() => {
  const total = lastPage.value;
  const curr  = currentPage.value;
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);

  const core = new Set([1, total, curr]);
  if (curr > 1) core.add(curr - 1);
  if (curr < total) core.add(curr + 1);

  const sorted = [...core].sort((a, b) => a - b);
  const result = [];
  for (let i = 0; i < sorted.length; i++) {
    if (i > 0 && sorted[i] - sorted[i - 1] > 1) result.push('…');
    result.push(sorted[i]);
  }
  return result;
});

function goPage(n) {
  const p = Number(n);
  if (p < 1 || p > lastPage.value) return;
  if (props.serverSide) {
    emit('page', p);
  } else {
    clientPage.value = p;
  }
}

function onPerPageChange(val) {
  clientPage.value    = 1;
  clientPerPage.value = val;
  if (props.serverSide) emit('perPage', val);
}
</script>

<style scoped>
/* ══════════════════════════════════════════════════════════════
   SHELL ROOT
══════════════════════════════════════════════════════════════ */
.dt-shell {
  --dt-brand:        #E91E63;
  --dt-brand-light:  rgba(233, 30, 99, 0.08);
  --dt-border:       rgba(0, 0, 0, 0.06);
  --dt-thead-bg:     #f8f9fb;
  --dt-row-hover:    #f8fafc;
  --dt-cell-color:   #374151;
  --dt-muted:        #9ca3af;
  --dt-text:         #1f2937;
  --dt-input-border: #e5e7eb;
  --dt-input-bg:     #f9fafb;
  --dt-radius:       9px;
}

/* ══════════════════════════════════════════════════════════════
   TOOLBAR
══════════════════════════════════════════════════════════════ */
.dt-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  padding: 14px 20px 12px;
  border-bottom: 1px solid var(--dt-border);
}

.dt-toolbar-left,
.dt-toolbar-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

/* Search */
.dt-search-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.dt-search-icon {
  position: absolute;
  left: 10px;
  color: var(--dt-muted);
  font-size: 12px;
  pointer-events: none;
}

.dt-search {
  height: 34px;
  padding: 0 12px 0 30px;
  border: 1.5px solid var(--dt-input-border);
  border-radius: var(--dt-radius);
  background: var(--dt-input-bg);
  color: var(--dt-text);
  font-size: 13px;
  font-family: inherit;
  outline: none;
  width: 200px;
  transition: border-color 0.18s, box-shadow 0.18s;
}

.dt-search:focus {
  border-color: var(--dt-brand);
  box-shadow: 0 0 0 3px var(--dt-brand-light);
  background: #fff;
}

.dt-search::placeholder {
  color: var(--dt-muted);
}

/* Status pill filters */
.dt-status-pills {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}

.dt-pill {
  height: 30px;
  padding: 0 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  border: 1.5px solid var(--dt-input-border);
  background: transparent;
  color: var(--dt-muted);
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s;
  font-family: inherit;
  line-height: 1;
}

.dt-pill:hover:not(.dt-pill--active) {
  border-color: rgba(31, 41, 55, 0.25);
  color: var(--dt-text);
  background: var(--dt-row-hover);
}

.dt-pill--active {
  background: #1e293b;
  border-color: #1e293b;
  color: #ffffff;
}

/* Date inputs */
.dt-date {
  height: 34px;
  padding: 0 10px;
  border: 1.5px solid var(--dt-input-border);
  border-radius: var(--dt-radius);
  background: var(--dt-input-bg);
  color: var(--dt-text);
  font-size: 12.5px;
  font-family: inherit;
  outline: none;
  cursor: pointer;
  transition: border-color 0.18s;
}

.dt-date:focus {
  border-color: var(--dt-brand);
  box-shadow: 0 0 0 3px var(--dt-brand-light);
  background: #fff;
}

.dt-date-sep {
  color: var(--dt-muted);
  font-size: 13px;
  user-select: none;
}

/* Clear filters button */
.dt-clear-btn {
  height: 30px;
  padding: 0 12px;
  border-radius: var(--dt-radius);
  border: 1.5px solid var(--dt-input-border);
  background: transparent;
  color: var(--dt-muted);
  font-size: 12px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s;
}

.dt-clear-btn:hover {
  border-color: #ef4444;
  color: #ef4444;
  background: rgba(239, 68, 68, 0.06);
}

/* ══════════════════════════════════════════════════════════════
   TABLE
══════════════════════════════════════════════════════════════ */
.dt-table-wrap {
  overflow-x: auto;
}

.dt-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: auto;
}

/* ── Thead ── */
:deep(.dt-table thead) {
  background: var(--dt-thead-bg);
}

:deep(.dt-table thead tr) {
  border-top: 1px solid var(--dt-border);
  border-bottom: 1px solid var(--dt-border);
}

:deep(.dt-table thead th) {
  padding: 10px 16px;
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--dt-muted);
  white-space: nowrap;
  border: none;
  vertical-align: middle;
}

/* Sortable column */
:deep(.dt-table th.dt-sortable) {
  cursor: pointer;
  user-select: none;
  transition: color 0.15s;
}

:deep(.dt-table th.dt-sortable:hover) {
  color: #6b7280;
}

/* ── Tbody ── */
:deep(.dt-table tbody tr) {
  border-bottom: 1px solid var(--dt-border);
  transition: background 0.1s;
}

:deep(.dt-table tbody tr:last-child) {
  border-bottom: none;
}

:deep(.dt-table tbody tr:hover) {
  background: var(--dt-row-hover);
}

:deep(.dt-table tbody td),
:deep(.dt-table tbody th) {
  padding: 14px 16px;
  vertical-align: middle;
  font-size: 13.5px;
  color: var(--dt-cell-color);
  border: none;
}

/* Checkbox column (narrow) */
:deep(.dt-table .dt-cb-col) {
  width: 44px;
  text-align: center;
  padding-left: 12px;
  padding-right: 8px;
}

/* Actions column (narrow) */
:deep(.dt-table .dt-ac-col) {
  width: 56px;
  text-align: center;
  padding-left: 8px;
  padding-right: 12px;
}

/* ── Checkboxes — remove pink border at rest ── */
:deep(.dt-table .form-check-input[type="checkbox"]) {
  border-color: #d1d5db;
  cursor: pointer;
  margin: 0;
}

:deep(.dt-table .form-check-input[type="checkbox"]:hover) {
  border-color: #9ca3af;
}

:deep(.dt-table .form-check-input[type="checkbox"]:checked) {
  background-color: var(--dt-brand);
  border-color: var(--dt-brand);
}

:deep(.dt-table .form-check-input[type="checkbox"]:focus) {
  box-shadow: 0 0 0 3px var(--dt-brand-light);
  border-color: var(--dt-brand);
}

/* ── Action button (replaces btn-outline-primary) ── */
:deep(.dt-table .dt-action-btn) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: #9ca3af;
  cursor: pointer;
  font-size: 14px;
  padding: 0;
  line-height: 1;
  transition: background 0.15s, color 0.15s;
}

:deep(.dt-table .dt-action-btn:hover) {
  background: #f1f5f9;
  color: #374151;
}

/* Hide Bootstrap's dropdown caret on the action button */
:deep(.dt-table .dt-action-btn.dropdown-toggle::after) {
  display: none;
}

/* ── Empty state ── */
.dt-empty-row { border: none !important; }

.dt-empty-cell {
  text-align: center;
  padding: 48px 16px !important;
  color: var(--dt-muted);
  font-size: 13.5px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

:deep(.dt-table .dt-empty-cell) {
  padding: 48px 16px;
}

.dt-empty-cell i {
  font-size: 28px;
  opacity: 0.25;
  display: block;
  margin-bottom: 4px;
}

/* ══════════════════════════════════════════════════════════════
   FOOTER
══════════════════════════════════════════════════════════════ */
.dt-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 20px;
  border-top: 1px solid var(--dt-border);
  flex-wrap: wrap;
  gap: 10px;
}

.dt-showing {
  font-size: 12.5px;
  color: var(--dt-muted);
  white-space: nowrap;
}

.dt-showing strong {
  color: var(--dt-text);
}

/* Page buttons */
.dt-pages {
  display: flex;
  align-items: center;
  gap: 3px;
}

.dt-pg-btn {
  min-width: 32px;
  height: 32px;
  padding: 0 8px;
  border: 1.5px solid var(--dt-input-border);
  border-radius: 8px;
  background: #fff;
  color: #374151;
  font-size: 13px;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
  line-height: 1;
}

.dt-pg-btn:hover:not(:disabled):not(.dt-pg-btn--active) {
  border-color: rgba(233, 30, 99, 0.4);
  color: var(--dt-brand);
}

.dt-pg-btn--active {
  background: var(--dt-brand);
  border-color: var(--dt-brand);
  color: #fff;
  font-weight: 700;
}

.dt-pg-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.dt-pg-ellipsis {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  color: var(--dt-muted);
  font-size: 14px;
  letter-spacing: 2px;
}

/* Per-page select */
.dt-perpage-wrap {
  display: flex;
  align-items: center;
}

.dt-perpage {
  height: 32px;
  padding: 0 28px 0 10px;
  border: 1.5px solid var(--dt-input-border);
  border-radius: 8px;
  background: var(--dt-input-bg);
  color: var(--dt-text);
  font-size: 12.5px;
  font-family: inherit;
  cursor: pointer;
  outline: none;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%239ca3af' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 9px center;
  transition: border-color 0.18s;
}

.dt-perpage:focus {
  border-color: var(--dt-brand);
  box-shadow: 0 0 0 3px var(--dt-brand-light);
}

/* ── Top accent stripe on the containing BaseBlock card ─────────
   Targets only .block cards that contain a DataTableShell (.dt-shell).
   Matches the 3px solid pink treatment used on settings .sc-head.
   Applied directly to .block (not .block-header) so it works on
   both titled blocks (invoices, quotes, customers, items) AND
   untitled blocks (payments — which renders no .block-header).
   The .block already has border-radius, so border-top follows
   the curve; overflow:hidden clips any child overflow cleanly.  */
:global(.block:has(.dt-shell)) {
  overflow: hidden;
  border-top: 3px solid #E91E63 !important;
}
</style>
