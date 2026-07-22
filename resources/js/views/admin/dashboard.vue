<template>
  <div class="db-wrap content">

    <!-- ── HEADER ──────────────────────────────────────────────── -->
    <div class="db-header">
      <div class="db-header-left">
        <h1 class="db-greeting">
          {{ greetingText }}<span v-if="userName">, {{ firstNameOnly }}</span> 👋
        </h1>
        <p class="db-subtitle">{{ $t('reports.subtitle') }}</p>
      </div>
      <div class="db-header-right">
        <router-link :to="{ name: 'backend-create-invoice' }" class="db-btn db-btn--primary">
          <i class="fa fa-plus db-btn-icon"></i>
          {{ $t('invoices.newTitle') }}
        </router-link>
        <router-link :to="{ name: 'backend-create-quote' }" class="db-btn db-btn--ghost">
          <i class="fa fa-plus db-btn-icon"></i>
          {{ $t('quotes.newTitle') }}
        </router-link>
      </div>
    </div>

    <!-- ── KPI CARDS ────────────────────────────────────────────── -->
    <div class="db-kpi-grid">

      <div class="db-kpi">
        <div class="db-kpi-icon-wrap db-kpi-icon-wrap--pink">
          <i class="fa fa-chart-line"></i>
        </div>
        <div class="db-kpi-body">
          <p class="db-kpi-label">{{ $t('reports.cards.totalEarnings') }}</p>
          <p class="db-kpi-value" :class="{ 'db-sk-text': isLoadingStats }">
            {{ isLoadingStats ? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' : formatCurrency(stats.totalEarnings) }}
          </p>
          <p class="db-kpi-meta db-kpi-meta--neutral">{{ $t('reports.allTime') }}</p>
        </div>
      </div>

      <div class="db-kpi">
        <div class="db-kpi-icon-wrap db-kpi-icon-wrap--green">
          <i class="fa fa-check-circle"></i>
        </div>
        <div class="db-kpi-body">
          <p class="db-kpi-label">{{ $t('reports.cash.receivedThisMonth') }}</p>
          <p class="db-kpi-value" :class="{ 'db-sk-text': isLoadingCash }">
            {{ isLoadingCash ? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' : formatCurrency(cash.received.amount) }}
          </p>
          <p class="db-kpi-meta db-kpi-meta--green" v-if="!isLoadingCash">{{ countLabel(cash.received.count) }}</p>
          <p class="db-kpi-meta db-kpi-meta--neutral" v-else>&nbsp;</p>
        </div>
      </div>

      <div class="db-kpi">
        <div class="db-kpi-icon-wrap db-kpi-icon-wrap--blue">
          <i class="fa fa-clock"></i>
        </div>
        <div class="db-kpi-body">
          <p class="db-kpi-label">{{ $t('reports.cash.expectedThisMonth') }}</p>
          <p class="db-kpi-value" :class="{ 'db-sk-text': isLoadingCash }">
            {{ isLoadingCash ? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' : formatCurrency(cash.expected.amount) }}
          </p>
          <p class="db-kpi-meta db-kpi-meta--blue" v-if="!isLoadingCash">{{ countLabel(cash.expected.count) }}</p>
          <p class="db-kpi-meta db-kpi-meta--neutral" v-else>&nbsp;</p>
        </div>
      </div>

      <div class="db-kpi" :class="{ 'db-kpi--alert': !isLoadingCash && cash.overdue.amount > 0 }">
        <div class="db-kpi-icon-wrap"
          :class="!isLoadingCash && cash.overdue.amount > 0 ? 'db-kpi-icon-wrap--orange' : 'db-kpi-icon-wrap--muted'">
          <i class="fa fa-exclamation-circle"></i>
        </div>
        <div class="db-kpi-body">
          <p class="db-kpi-label">{{ $t('reports.cash.overdueTitle') }}</p>
          <p class="db-kpi-value"
            :class="[{ 'db-sk-text': isLoadingCash }, !isLoadingCash && cash.overdue.amount > 0 ? 'db-kpi-value--warn' : '']">
            {{ isLoadingCash ? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' : formatCurrency(cash.overdue.amount) }}
          </p>
          <p class="db-kpi-meta db-kpi-meta--orange" v-if="!isLoadingCash && cash.overdue.count > 0">{{ countLabel(cash.overdue.count) }}</p>
          <p class="db-kpi-meta db-kpi-meta--green" v-else-if="!isLoadingCash">{{ $t('reports.cash.allClear') }}</p>
          <p class="db-kpi-meta db-kpi-meta--neutral" v-else>&nbsp;</p>
        </div>
      </div>

    </div>

    <!-- ── MAIN GRID ─────────────────────────────────────────────── -->
    <div class="db-main-grid">

      <!-- ── LEFT COLUMN ──────────────────────────────────────── -->
      <div class="db-col-left">

        <!-- Recent Invoices -->
        <div class="db-card">
          <div class="db-card-head">
            <h3 class="db-card-title">{{ $t('reports.latestInvoicesTitle') }}</h3>
            <router-link :to="{ name: 'backend-invoices' }" class="db-card-link">
              {{ $t('reports.actions.viewInvoices') }} <i class="fa fa-arrow-right ms-1"></i>
            </router-link>
          </div>

          <div v-if="invoicesError" class="db-inline-err">{{ invoicesError }}</div>

          <div class="db-table-wrap" v-if="!isLoadingInvoices">
            <table class="db-table" v-if="lastInvoices.length">
              <thead>
                <tr>
                  <th>{{ $t('invoices.table.number') }}</th>
                  <th>{{ $t('invoices.table.customer') }}</th>
                  <th>{{ $t('invoices.table.date') }}</th>
                  <th class="db-th-right">{{ $t('invoices.table.total') }}</th>
                  <th class="db-th-center">{{ $t('invoices.table.status') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="inv in lastInvoices"
                  :key="inv.uuid"
                  class="db-tr-link"
                  @click="$router.push({ name: 'backend-edit-invoice', params: { id: inv.uuid } })"
                >
                  <td class="db-td-ref">{{ inv.reference }}</td>
                  <td>
                    <span class="db-client-wrap">
                      <span class="db-avatar">{{ avatarInitials(inv.customer) }}</span>
                      <span class="db-client-name">{{ inv.customer }}</span>
                    </span>
                  </td>
                  <td class="db-td-date">{{ formatDate(inv.date) }}</td>
                  <td class="db-td-right db-td-amount">{{ formatCurrency(inv.total) }}</td>
                  <td class="db-td-center">
                    <span class="db-pill" :class="pillClass(inv.status)">{{ statusLabel(inv.status) }}</span>
                  </td>
                </tr>
              </tbody>
            </table>

            <div class="db-empty-state" v-else>
              <div class="db-empty-icon-wrap">
                <i class="fa fa-file-invoice"></i>
              </div>
              <p class="db-empty-title">{{ $t('reports.emptyInvoices') }}</p>
              <p class="db-empty-sub">{{ $t('reports.emptyInvoicesSub') }}</p>
              <router-link :to="{ name: 'backend-create-invoice' }" class="db-btn db-btn--primary db-btn--sm">
                <i class="fa fa-plus db-btn-icon"></i> {{ $t('reports.createInvoice') }}
              </router-link>
            </div>
          </div>

          <!-- Skeleton -->
          <div class="db-tbl-skeleton" v-else>
            <div class="db-sk-row" v-for="n in 6" :key="n">
              <div class="db-sk-cell db-sk-cell--ref"></div>
              <div class="db-sk-cell db-sk-cell--name"></div>
              <div class="db-sk-cell db-sk-cell--date"></div>
              <div class="db-sk-cell db-sk-cell--amt"></div>
              <div class="db-sk-cell db-sk-cell--pill"></div>
            </div>
          </div>
        </div>

        <!-- Activity Timeline -->
        <div class="db-card db-card--timeline" v-if="!isLoadingInvoices && lastInvoices.length">
          <div class="db-card-head">
            <h3 class="db-card-title">{{ $t('reports.recentActivity') }}</h3>
          </div>
          <div class="db-timeline">
            <div
              class="db-tl-item"
              v-for="(inv, i) in lastInvoices.slice(0, 6)"
              :key="'tl-' + inv.uuid"
            >
              <div class="db-tl-spine" v-if="i < Math.min(lastInvoices.length, 6) - 1"></div>
              <div class="db-tl-dot" :class="'db-tl-dot--' + tlColor(inv.status)">
                <i class="fa" :class="tlIcon(inv.status)"></i>
              </div>
              <div class="db-tl-body">
                <p class="db-tl-text">{{ tlText(inv) }}</p>
                <span class="db-tl-date">{{ formatDate(inv.date) }}</span>
              </div>
            </div>
          </div>
        </div>

      </div>
      <!-- END LEFT -->

      <!-- ── RIGHT COLUMN ─────────────────────────────────────── -->
      <div class="db-col-right">

        <!-- Cash Overview -->
        <div class="db-card">
          <div class="db-card-head">
            <h3 class="db-card-title">{{ $t('reports.cash.title') }}</h3>
          </div>
          <div v-if="cashError" class="db-inline-err">{{ cashError }}</div>

          <div class="db-cash-body" v-if="!isLoadingCash">
            <div class="db-cash-row">
              <div class="db-cash-labels">
                <span class="db-cash-dot db-cash-dot--green"></span>
                <span class="db-cash-name">{{ $t('reports.cash.receivedThisMonth') }}</span>
                <span class="db-cash-amt">{{ formatCurrency(cash.received.amount) }}</span>
              </div>
              <div class="db-progress-track">
                <div class="db-progress-fill db-progress-fill--green"
                  :style="{ width: cashPct('received') + '%' }"></div>
              </div>
            </div>
            <div class="db-cash-row">
              <div class="db-cash-labels">
                <span class="db-cash-dot db-cash-dot--blue"></span>
                <span class="db-cash-name">{{ $t('reports.cash.expectedThisMonth') }}</span>
                <span class="db-cash-amt">{{ formatCurrency(cash.expected.amount) }}</span>
              </div>
              <div class="db-progress-track">
                <div class="db-progress-fill db-progress-fill--blue"
                  :style="{ width: cashPct('expected') + '%' }"></div>
              </div>
            </div>
            <div class="db-cash-row">
              <div class="db-cash-labels">
                <span class="db-cash-dot db-cash-dot--orange"></span>
                <span class="db-cash-name">{{ $t('reports.cash.overdueTitle') }}</span>
                <span class="db-cash-amt">{{ formatCurrency(cash.overdue.amount) }}</span>
              </div>
              <div class="db-progress-track">
                <div class="db-progress-fill db-progress-fill--orange"
                  :style="{ width: cashPct('overdue') + '%' }"></div>
              </div>
            </div>
          </div>

          <div class="db-cash-body" v-else>
            <div class="db-sk-cash-row" v-for="n in 3" :key="n">
              <div class="db-sk-cash-label"></div>
              <div class="db-sk-cash-track"></div>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="db-card">
          <div class="db-card-head">
            <h3 class="db-card-title">{{ $t('reports.quickActions') }}</h3>
          </div>
          <div class="db-qa-grid">
            <router-link :to="{ name: 'backend-create-invoice' }" class="db-qa">
              <div class="db-qa-icon db-qa-icon--pink"><i class="fa fa-file-invoice"></i></div>
              <span class="db-qa-label">{{ $t('invoices.newTitle') }}</span>
            </router-link>
            <router-link :to="{ name: 'backend-create-quote' }" class="db-qa">
              <div class="db-qa-icon db-qa-icon--blue"><i class="fa fa-file-alt"></i></div>
              <span class="db-qa-label">{{ $t('quotes.newTitle') }}</span>
            </router-link>
            <router-link :to="{ name: 'backend-create-customer' }" class="db-qa">
              <div class="db-qa-icon db-qa-icon--green"><i class="fa fa-user-plus"></i></div>
              <span class="db-qa-label">{{ $t('customers.newTitle') }}</span>
            </router-link>
            <router-link :to="{ name: 'backend-create-item' }" class="db-qa">
              <div class="db-qa-icon db-qa-icon--purple"><i class="fa fa-box"></i></div>
              <span class="db-qa-label">{{ $t('items.newTitle') }}</span>
            </router-link>
          </div>
        </div>

        <!-- Stats Strip -->
        <div class="db-stats-strip">
          <router-link :to="{ name: 'backend-invoices' }" class="db-stat">
            <span class="db-stat-val" :class="{ 'db-sk-text': isLoadingStats }">
              {{ isLoadingStats ? '&nbsp;&nbsp;' : stats.invoices }}
            </span>
            <span class="db-stat-lbl">{{ $t('reports.cards.invoices') }}</span>
          </router-link>
          <router-link :to="{ name: 'backend-customers' }" class="db-stat">
            <span class="db-stat-val" :class="{ 'db-sk-text': isLoadingStats }">
              {{ isLoadingStats ? '&nbsp;&nbsp;' : stats.customers }}
            </span>
            <span class="db-stat-lbl">{{ $t('reports.cards.customers') }}</span>
          </router-link>
          <router-link :to="{ name: 'backend-items' }" class="db-stat">
            <span class="db-stat-val" :class="{ 'db-sk-text': isLoadingStats }">
              {{ isLoadingStats ? '&nbsp;&nbsp;' : stats.items }}
            </span>
            <span class="db-stat-lbl">{{ $t('reports.cards.items') }}</span>
          </router-link>
        </div>

        <!-- Urgent Overdue -->
        <div class="db-card db-card--warn" v-if="!isLoadingCash && cash.urgent_overdue.length">
          <div class="db-card-head">
            <h3 class="db-card-title">{{ $t('reports.cash.urgentTitle') }}</h3>
            <span class="db-warn-badge">{{ cash.urgent_overdue.length }}</span>
          </div>
          <div class="db-urgent-list">
            <router-link
              v-for="inv in cash.urgent_overdue"
              :key="inv.uuid"
              :to="{ name: 'backend-edit-invoice', params: { id: inv.uuid } }"
              class="db-urgent-row"
            >
              <span class="db-avatar db-avatar--sm">{{ avatarInitials(inv.customer) }}</span>
              <div class="db-urgent-info">
                <span class="db-urgent-client">{{ inv.customer }}</span>
                <span class="db-urgent-ref">{{ inv.reference }}</span>
              </div>
              <div class="db-urgent-right">
                <span class="db-urgent-amount">{{ formatCurrency(inv.total) }}</span>
                <span class="db-urgent-days">{{ $t('reports.cash.daysOverdueBadge', { count: inv.days_overdue }) }}</span>
              </div>
              <i class="fa fa-chevron-right db-urgent-arrow"></i>
            </router-link>
          </div>
        </div>

        <!-- All Good -->
        <div
          class="db-all-good"
          v-else-if="!isLoadingCash && !cash.urgent_overdue.length && !cashError"
        >
          <i class="fa fa-check-circle db-all-good-icon"></i>
          <span>{{ $t('reports.cash.noOverdue') }}</span>
        </div>

      </div>
      <!-- END RIGHT -->

    </div>

  </div>
</template>


<script setup>
import { reactive, ref, computed, onMounted } from "vue";
import axios from "axios";
import { useI18n } from "vue-i18n";

const { locale, t } = useI18n();

const localeMap = { es: "es-ES", en: "en-US", fr: "fr-FR" };

const formatCurrency = (value) =>
  new Intl.NumberFormat(localeMap[locale.value] || "es-ES", {
    style: "currency",
    currency: "EUR",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value ?? 0));

const formatDate = (value) =>
  value ? new Date(value).toLocaleDateString(localeMap[locale.value] || "es-ES") : "";

// ── Greeting ──────────────────────────────────────────────────
const userName = ref("");
const firstNameOnly = computed(() => userName.value.split(" ")[0]);
const greetingText = computed(() => {
  const h = new Date().getHours();
  if (h < 12) return t('reports.greeting.morning');
  if (h < 18) return t('reports.greeting.afternoon');
  return t('reports.greeting.evening');
});

// ── Stats ─────────────────────────────────────────────────────
const isLoadingStats = ref(true);
const statsError = ref("");
const stats = reactive({ invoices: 0, customers: 0, items: 0, totalEarnings: 0 });

const loadStats = async () => {
  isLoadingStats.value = true;
  statsError.value = "";
  try {
    const { data } = await axios.get("/stats/counts");
    stats.invoices      = data?.invoices      ?? 0;
    stats.customers     = data?.customers     ?? 0;
    stats.items         = data?.items         ?? 0;
    stats.totalEarnings = data?.total_earnings ?? 0;
  } catch {
    statsError.value = t("reports.errors.stats");
  } finally {
    isLoadingStats.value = false;
  }
};

// ── Invoices ──────────────────────────────────────────────────
const isLoadingInvoices = ref(true);
const invoicesError = ref("");
const lastInvoices = ref([]);

const loadLastInvoices = async () => {
  isLoadingInvoices.value = true;
  invoicesError.value = "";
  try {
    const { data } = await axios.get("/invoices", { params: { per_page: 8 } });
    lastInvoices.value = data?.invoices ?? [];
  } catch {
    invoicesError.value = t("reports.errors.invoices");
  } finally {
    isLoadingInvoices.value = false;
  }
};

// ── Cash Overview ─────────────────────────────────────────────
const isLoadingCash = ref(true);
const cashError = ref("");
const cash = reactive({
  expected:       { amount: 0, count: 0 },
  overdue:        { amount: 0, count: 0 },
  received:       { amount: 0, count: 0 },
  urgent_overdue: [],
});

const loadCashOverview = async () => {
  isLoadingCash.value = true;
  cashError.value = "";
  try {
    const { data } = await axios.get("/stats/cash-overview");
    cash.expected       = data.expected;
    cash.overdue        = data.overdue;
    cash.received       = data.received;
    cash.urgent_overdue = data.urgent_overdue ?? [];
  } catch {
    cashError.value = t("reports.cash.error");
  } finally {
    isLoadingCash.value = false;
  }
};

// ── User (for personalised greeting) ─────────────────────────
const loadUser = async () => {
  try {
    const { data } = await axios.get("/user");
    userName.value = data?.user?.name ?? data?.name ?? "";
  } catch { /* silent — greeting works without name */ }
};

// ── Helpers ───────────────────────────────────────────────────
function countLabel(n) {
  const word = n === 1 ? t("reports.cash.invoice") : t("reports.cash.invoices");
  return `${n} ${word}`;
}

function avatarInitials(name) {
  if (!name) return "?";
  const parts = name.trim().split(/\s+/);
  return (parts.length >= 2
    ? parts[0][0] + parts[1][0]
    : name.slice(0, 2)
  ).toUpperCase();
}

const STATUS_LABELS = {
  draft:     () => t("invoices.statusDraft"),
  issued:    () => t("invoices.statusIssued"),
  paid:      () => t("invoices.statusPaid"),
  cancelled: () => t("invoices.statusCancelled"),
};

const STATUS_PILL = {
  draft:     "db-pill--gray",
  issued:    "db-pill--blue",
  paid:      "db-pill--green",
  cancelled: "db-pill--red",
};

function pillClass(status)  { return STATUS_PILL[status] ?? "db-pill--gray"; }
function statusLabel(status) { return STATUS_LABELS[status]?.() ?? status; }

function cashPct(type) {
  const total = cash.received.amount + cash.expected.amount + cash.overdue.amount;
  if (!total) return 0;
  return Math.min(100, Math.round((cash[type].amount / total) * 100));
}

const TL_COLOR = { paid: "green", issued: "blue", draft: "gray", cancelled: "red" };
const TL_ICON  = { paid: "fa-check", issued: "fa-paper-plane", draft: "fa-edit", cancelled: "fa-times" };

function tlColor(status) { return TL_COLOR[status] ?? "gray"; }
function tlIcon(status)  { return TL_ICON[status]  ?? "fa-file"; }

function tlText(inv) {
  const client = inv.customer || "—";
  const amt    = formatCurrency(inv.total);
  if (inv.status === "paid")      return t('reports.timeline.paymentReceived', { client, amount: amt });
  if (inv.status === "issued")    return t('reports.timeline.invoiceSent', { ref: inv.reference, client });
  if (inv.status === "draft")     return t('reports.timeline.draft', { client, amount: amt });
  if (inv.status === "cancelled") return t('reports.timeline.cancelled', { ref: inv.reference });
  return t('reports.timeline.generic', { ref: inv.reference });
}

onMounted(() => {
  loadStats();
  loadLastInvoices();
  loadCashOverview();
  loadUser();
});
</script>


<style scoped>
/* ═══════════════════════════════════════════════════════════════
   Dashboard — scoped styles only. Do NOT add globals here.
   ═══════════════════════════════════════════════════════════════ */

/* ── Tokens: Light ─────────────────────────────────────────── */
.db-wrap {
  --db-bg:          #f3f5f8;
  --db-surface:     #ffffff;
  --db-surface-2:   #f8fafc;
  --db-border:      #e6eaef;
  --db-border-2:    #ccd2db;
  --db-text-1:      #0d1117;
  --db-text-2:      #445066;
  --db-text-3:      #8b96a8;
  --db-pink:        #E91E63;
  --db-pink-dk:     #c2185b;
  --db-pink-bg:     rgba(233, 30, 99, 0.08);
  --db-green:       #00a854;
  --db-green-bg:    rgba(0, 168, 84, 0.09);
  --db-blue:        #2563eb;
  --db-blue-bg:     rgba(37, 99, 235, 0.09);
  --db-orange:      #e07b00;
  --db-orange-bg:   rgba(224, 123, 0, 0.09);
  --db-red:         #dc2626;
  --db-red-bg:      rgba(220, 38, 38, 0.09);
  --db-purple:      #7c3aed;
  --db-purple-bg:   rgba(124, 58, 237, 0.09);
  --db-shadow-xs:   0 1px 2px rgba(13, 17, 23, 0.05);
  --db-shadow-sm:   0 1px 4px rgba(13, 17, 23, 0.06), 0 2px 8px rgba(13, 17, 23, 0.05);
  --db-shadow-md:   0 4px 16px rgba(13, 17, 23, 0.10), 0 1px 4px rgba(13, 17, 23, 0.05);
  --db-radius:      14px;
  --db-radius-sm:   9px;
  --db-ease:        cubic-bezier(0.4, 0, 0.2, 1);
}

/* ── Tokens: Dark ──────────────────────────────────────────── */
@media (prefers-color-scheme: dark) {
  .db-wrap {
    --db-bg:        #0d1117;
    --db-surface:   #161b22;
    --db-surface-2: #1c2230;
    --db-border:    #29323f;
    --db-border-2:  #3b4758;
    --db-text-1:    #e6edf3;
    --db-text-2:    #8b949e;
    --db-text-3:    #606b79;
    --db-pink-bg:   rgba(233, 30, 99, 0.14);
    --db-green-bg:  rgba(0, 168, 84, 0.14);
    --db-blue-bg:   rgba(37, 99, 235, 0.14);
    --db-orange-bg: rgba(224, 123, 0, 0.14);
    --db-red-bg:    rgba(220, 38, 38, 0.14);
    --db-purple-bg: rgba(124, 58, 237, 0.14);
    --db-shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.35);
    --db-shadow-sm: 0 1px 4px rgba(0, 0, 0, 0.35), 0 2px 8px rgba(0, 0, 0, 0.25);
    --db-shadow-md: 0 4px 16px rgba(0, 0, 0, 0.45), 0 1px 4px rgba(0, 0, 0, 0.3);
  }
}

:root[data-theme="dark"] .db-wrap {
  --db-bg:        #0d1117;
  --db-surface:   #161b22;
  --db-surface-2: #1c2230;
  --db-border:    #29323f;
  --db-border-2:  #3b4758;
  --db-text-1:    #e6edf3;
  --db-text-2:    #8b949e;
  --db-text-3:    #606b79;
  --db-pink-bg:   rgba(233, 30, 99, 0.14);
  --db-green-bg:  rgba(0, 168, 84, 0.14);
  --db-blue-bg:   rgba(37, 99, 235, 0.14);
  --db-orange-bg: rgba(224, 123, 0, 0.14);
  --db-red-bg:    rgba(220, 38, 38, 0.14);
  --db-purple-bg: rgba(124, 58, 237, 0.14);
  --db-shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.35);
  --db-shadow-sm: 0 1px 4px rgba(0, 0, 0, 0.35), 0 2px 8px rgba(0, 0, 0, 0.25);
  --db-shadow-md: 0 4px 16px rgba(0, 0, 0, 0.45), 0 1px 4px rgba(0, 0, 0, 0.3);
}

:root[data-theme="light"] .db-wrap {
  --db-bg:          #f3f5f8;
  --db-surface:     #ffffff;
  --db-surface-2:   #f8fafc;
  --db-border:      #e6eaef;
  --db-border-2:    #ccd2db;
  --db-text-1:      #0d1117;
  --db-text-2:      #445066;
  --db-text-3:      #8b96a8;
  --db-pink-bg:     rgba(233, 30, 99, 0.08);
  --db-green-bg:    rgba(0, 168, 84, 0.09);
  --db-blue-bg:     rgba(37, 99, 235, 0.09);
  --db-orange-bg:   rgba(224, 123, 0, 0.09);
  --db-red-bg:      rgba(220, 38, 38, 0.09);
  --db-purple-bg:   rgba(124, 58, 237, 0.09);
  --db-shadow-xs:   0 1px 2px rgba(13, 17, 23, 0.05);
  --db-shadow-sm:   0 1px 4px rgba(13, 17, 23, 0.06), 0 2px 8px rgba(13, 17, 23, 0.05);
  --db-shadow-md:   0 4px 16px rgba(13, 17, 23, 0.10), 0 1px 4px rgba(13, 17, 23, 0.05);
}

/* ── Base ───────────────────────────────────────────────────── */
.db-wrap {
  background: var(--db-bg);
  color: var(--db-text-1);
  padding-bottom: 48px;
}

/* ── Header ─────────────────────────────────────────────────── */
.db-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 30px;
  padding-top: 6px;
}

.db-greeting {
  font-size: 26px;
  font-weight: 750;
  color: var(--db-text-1);
  letter-spacing: -0.035em;
  margin: 0 0 5px;
  line-height: 1.2;
  text-wrap: balance;
}

.db-subtitle {
  font-size: 14px;
  color: var(--db-text-3);
  margin: 0;
  line-height: 1.5;
}

.db-header-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}

/* ── Buttons ─────────────────────────────────────────────────── */
.db-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  border: 1px solid transparent;
  cursor: pointer;
  transition: background .15s ease-in-out, color .15s ease-in-out,
              box-shadow .15s ease-in-out, transform .15s ease-in-out,
              border-color .15s ease-in-out;
  white-space: nowrap;
  line-height: 1;
}

.db-btn--primary {
  background: #E91E63;
  color: #fff;
}
.db-btn--primary:hover {
  background: #c2185b;
  color: #fff;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(233, 30, 99, 0.22);
  text-decoration: none;
}

.db-btn--ghost {
  background: var(--db-surface);
  color: var(--db-text-2);
  border-color: var(--db-border);
}
.db-btn--ghost:hover {
  border-color: var(--db-border-2);
  color: var(--db-text-1);
  background: var(--db-surface-2);
  transform: translateY(-1px);
  text-decoration: none;
}

.db-btn--sm {
  padding: 6px 12px;
  font-size: 13px;
  border-radius: 8px;
}

.db-btn-icon { font-size: 12px; }

/* ── KPI Grid ────────────────────────────────────────────────── */
.db-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

@media (max-width: 1100px) {
  .db-kpi-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 580px) {
  .db-kpi-grid { grid-template-columns: 1fr; }
}

.db-kpi {
  background: var(--db-surface);
  border: 1px solid var(--db-border);
  border-radius: var(--db-radius);
  padding: 20px 20px 18px;
  display: flex;
  align-items: flex-start;
  gap: 14px;
  box-shadow: var(--db-shadow-sm);
  transition: box-shadow 0.2s var(--db-ease),
              transform 0.16s var(--db-ease),
              border-color 0.2s var(--db-ease);
}

.db-kpi:hover {
  box-shadow: var(--db-shadow-md);
  transform: translateY(-2px);
  border-color: var(--db-border-2);
}

.db-kpi--alert {
  border-color: rgba(224, 123, 0, 0.28);
}

.db-kpi-icon-wrap {
  width: 42px;
  height: 42px;
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  flex-shrink: 0;
  transition: transform 0.18s var(--db-ease);
}
.db-kpi:hover .db-kpi-icon-wrap { transform: scale(1.07); }

.db-kpi-icon-wrap--pink   { background: var(--db-pink-bg);   color: var(--db-pink); }
.db-kpi-icon-wrap--green  { background: var(--db-green-bg);  color: var(--db-green); }
.db-kpi-icon-wrap--blue   { background: var(--db-blue-bg);   color: var(--db-blue); }
.db-kpi-icon-wrap--orange { background: var(--db-orange-bg); color: var(--db-orange); }
.db-kpi-icon-wrap--muted  { background: var(--db-surface-2); color: var(--db-text-3); }

.db-kpi-body { flex: 1; min-width: 0; }

.db-kpi-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--db-text-3);
  margin: 0 0 7px;
}

.db-kpi-value {
  font-size: 22px;
  font-weight: 720;
  color: var(--db-text-1);
  letter-spacing: -0.03em;
  margin: 0 0 5px;
  font-variant-numeric: tabular-nums;
  line-height: 1.15;
}
.db-kpi-value--warn { color: var(--db-orange); }

.db-kpi-meta {
  font-size: 12px;
  margin: 0;
  line-height: 1;
}
.db-kpi-meta--neutral { color: var(--db-text-3); }
.db-kpi-meta--green   { color: var(--db-green); }
.db-kpi-meta--blue    { color: var(--db-blue); }
.db-kpi-meta--orange  { color: var(--db-orange); }

/* ── Main Grid ───────────────────────────────────────────────── */
.db-main-grid {
  display: grid;
  grid-template-columns: 1fr 316px;
  gap: 20px;
  align-items: start;
}

@media (max-width: 1060px) {
  .db-main-grid { grid-template-columns: 1fr; }
}

/* ── Card ────────────────────────────────────────────────────── */
.db-card {
  background: var(--db-surface);
  border: 1px solid var(--db-border);
  border-radius: var(--db-radius);
  box-shadow: var(--db-shadow-sm);
  overflow: hidden;
  margin-bottom: 20px;
}

.db-card--warn {
  border-color: rgba(224, 123, 0, 0.22);
}

.db-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 15px 20px;
  border-bottom: 1px solid var(--db-border);
  border-top: 3px solid var(--db-pink);
}

.db-card-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.09em;
  color: var(--db-text-3);
  margin: 0;
}

.db-card-link {
  font-size: 12px;
  font-weight: 500;
  color: var(--db-pink);
  text-decoration: none;
  opacity: 0.8;
  transition: opacity 0.15s;
}
.db-card-link:hover {
  opacity: 1;
  color: var(--db-pink);
  text-decoration: none;
}

/* ── Table ───────────────────────────────────────────────────── */
.db-table-wrap { overflow-x: auto; }

.db-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.db-table thead th {
  padding: 10px 16px;
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--db-text-3);
  border-bottom: 1px solid var(--db-border);
  white-space: nowrap;
  background: var(--db-surface);
}

.db-table tbody td {
  padding: 13px 16px;
  color: var(--db-text-1);
  border-bottom: 1px solid var(--db-border);
  vertical-align: middle;
}

.db-table tbody tr:last-child td { border-bottom: none; }

.db-tr-link {
  cursor: pointer;
  transition: background 0.13s var(--db-ease);
}
.db-tr-link:hover td { background: var(--db-surface-2); }

.db-th-right, .db-td-right   { text-align: right; }
.db-th-center, .db-td-center { text-align: center; }

.db-td-ref {
  font-weight: 700;
  color: var(--db-pink);
  white-space: nowrap;
  letter-spacing: 0.01em;
}

.db-client-wrap {
  display: flex;
  align-items: center;
  gap: 9px;
}

.db-client-name {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 148px;
}

.db-td-date   { color: var(--db-text-2); white-space: nowrap; }
.db-td-amount { font-weight: 650; font-variant-numeric: tabular-nums; white-space: nowrap; }

/* ── Avatar ──────────────────────────────────────────────────── */
.db-avatar {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: var(--db-pink-bg);
  color: var(--db-pink);
  font-size: 10px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.db-avatar--sm {
  width: 26px;
  height: 26px;
  font-size: 9px;
}

/* ── Pill ────────────────────────────────────────────────────── */
.db-pill {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 650;
  letter-spacing: 0.02em;
  white-space: nowrap;
}
.db-pill--gray   { background: rgba(139, 150, 168, 0.12); color: var(--db-text-2); }
.db-pill--blue   { background: var(--db-blue-bg);         color: var(--db-blue); }
.db-pill--green  { background: var(--db-green-bg);        color: var(--db-green); }
.db-pill--red    { background: var(--db-red-bg);          color: var(--db-red); }

/* ── Empty State ─────────────────────────────────────────────── */
.db-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 48px 24px;
  text-align: center;
}

.db-empty-icon-wrap {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  background: var(--db-surface-2);
  border: 1px solid var(--db-border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  color: var(--db-text-3);
  margin-bottom: 4px;
}

.db-empty-title {
  font-size: 15px;
  font-weight: 650;
  color: var(--db-text-1);
  margin: 0;
}

.db-empty-sub {
  font-size: 13px;
  color: var(--db-text-3);
  margin: 0 0 6px;
}

/* ── Inline Error ────────────────────────────────────────────── */
.db-inline-err {
  background: var(--db-orange-bg);
  color: var(--db-orange);
  font-size: 12px;
  font-weight: 500;
  padding: 10px 20px;
  border-bottom: 1px solid var(--db-border);
}

/* ── Timeline ────────────────────────────────────────────────── */
.db-timeline { padding: 4px 20px 14px; }

.db-tl-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 9px 0;
  position: relative;
}

.db-tl-spine {
  position: absolute;
  left: 11px;
  top: 30px;
  bottom: -9px;
  width: 1px;
  background: var(--db-border);
}

.db-tl-dot {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 9px;
  flex-shrink: 0;
  position: relative;
  z-index: 1;
  margin-top: 1px;
}

.db-tl-dot--green  { background: var(--db-green-bg);  color: var(--db-green); }
.db-tl-dot--blue   { background: var(--db-blue-bg);   color: var(--db-blue); }
.db-tl-dot--gray   { background: var(--db-surface-2); color: var(--db-text-3); border: 1px solid var(--db-border); }
.db-tl-dot--red    { background: var(--db-red-bg);    color: var(--db-red); }

.db-tl-body { flex: 1; min-width: 0; }

.db-tl-text {
  font-size: 13px;
  color: var(--db-text-1);
  margin: 0 0 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.db-tl-date {
  font-size: 11px;
  color: var(--db-text-3);
}

/* ── Cash Overview ───────────────────────────────────────────── */
.db-cash-body {
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 17px;
}

.db-cash-labels {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 7px;
}

.db-cash-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.db-cash-dot--green  { background: var(--db-green); }
.db-cash-dot--blue   { background: var(--db-blue); }
.db-cash-dot--orange { background: var(--db-orange); }

.db-cash-name {
  flex: 1;
  font-size: 12px;
  color: var(--db-text-2);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.db-cash-amt {
  font-size: 12px;
  font-weight: 650;
  color: var(--db-text-1);
  font-variant-numeric: tabular-nums;
  flex-shrink: 0;
}

.db-progress-track {
  height: 6px;
  background: var(--db-surface-2);
  border-radius: 3px;
  overflow: hidden;
  border: 1px solid var(--db-border);
}

.db-progress-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.85s var(--db-ease);
  min-width: 3px;
}
.db-progress-fill--green  { background: var(--db-green); }
.db-progress-fill--blue   { background: var(--db-blue); }
.db-progress-fill--orange { background: var(--db-orange); }

/* ── Quick Actions ───────────────────────────────────────────── */
.db-qa-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  padding: 14px;
}

.db-qa {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 9px;
  padding: 16px 10px;
  background: var(--db-surface-2);
  border: 1px solid var(--db-border);
  border-radius: 10px;
  text-decoration: none;
  transition: border-color 0.16s var(--db-ease),
              background 0.16s var(--db-ease),
              transform 0.14s var(--db-ease),
              box-shadow 0.16s var(--db-ease);
  cursor: pointer;
}
.db-qa:hover {
  border-color: var(--db-pink);
  background: var(--db-pink-bg);
  transform: translateY(-1px);
  box-shadow: var(--db-shadow-xs);
  text-decoration: none;
}

.db-qa-icon {
  width: 36px;
  height: 36px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  transition: transform 0.16s var(--db-ease);
}
.db-qa:hover .db-qa-icon { transform: scale(1.1); }

.db-qa-icon--pink   { background: var(--db-pink-bg);   color: var(--db-pink); }
.db-qa-icon--blue   { background: var(--db-blue-bg);   color: var(--db-blue); }
.db-qa-icon--green  { background: var(--db-green-bg);  color: var(--db-green); }
.db-qa-icon--purple { background: var(--db-purple-bg); color: var(--db-purple); }

.db-qa-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--db-text-2);
  text-align: center;
  line-height: 1.35;
  transition: color 0.16s var(--db-ease);
}
.db-qa:hover .db-qa-label { color: var(--db-pink); }

/* ── Stats Strip ─────────────────────────────────────────────── */
.db-stats-strip {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  background: var(--db-surface);
  border: 1px solid var(--db-border);
  border-radius: var(--db-radius);
  box-shadow: var(--db-shadow-sm);
  overflow: hidden;
  margin-bottom: 20px;
}

.db-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 16px 8px;
  text-decoration: none;
  border-right: 1px solid var(--db-border);
  transition: background 0.14s var(--db-ease);
}
.db-stat:last-child { border-right: none; }
.db-stat:hover {
  background: var(--db-surface-2);
  text-decoration: none;
}

.db-stat-val {
  font-size: 20px;
  font-weight: 720;
  color: var(--db-text-1);
  letter-spacing: -0.02em;
  font-variant-numeric: tabular-nums;
  line-height: 1.2;
  min-height: 1.2em;
  min-width: 2ch;
  display: block;
  text-align: center;
}

.db-stat-lbl {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--db-text-3);
  margin-top: 3px;
}

/* ── Urgent Overdue ──────────────────────────────────────────── */
.db-warn-badge {
  background: var(--db-orange-bg);
  color: var(--db-orange);
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  min-width: 20px;
  text-align: center;
}

.db-urgent-list { display: flex; flex-direction: column; }

.db-urgent-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 11px 16px;
  text-decoration: none;
  color: inherit;
  border-bottom: 1px solid var(--db-border);
  transition: background 0.13s var(--db-ease);
}
.db-urgent-row:last-child { border-bottom: none; }
.db-urgent-row:hover {
  background: var(--db-orange-bg);
  text-decoration: none;
}

.db-urgent-info { flex: 1; min-width: 0; }

.db-urgent-client {
  display: block;
  font-size: 12px;
  font-weight: 650;
  color: var(--db-text-1);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.db-urgent-ref {
  display: block;
  font-size: 11px;
  color: var(--db-text-3);
}

.db-urgent-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
  flex-shrink: 0;
}

.db-urgent-amount {
  font-size: 12px;
  font-weight: 700;
  color: var(--db-text-1);
  font-variant-numeric: tabular-nums;
}

.db-urgent-days {
  font-size: 10px;
  font-weight: 600;
  color: var(--db-orange);
  background: var(--db-orange-bg);
  padding: 1px 6px;
  border-radius: 4px;
}

.db-urgent-arrow {
  font-size: 9px;
  color: var(--db-text-3);
  flex-shrink: 0;
}

/* ── All Good ────────────────────────────────────────────────── */
.db-all-good {
  display: flex;
  align-items: center;
  gap: 9px;
  background: var(--db-surface);
  border: 1px solid rgba(0, 168, 84, 0.2);
  border-radius: var(--db-radius);
  padding: 14px 18px;
  font-size: 13px;
  font-weight: 500;
  color: var(--db-green);
  box-shadow: var(--db-shadow-sm);
  margin-bottom: 0;
}

.db-all-good-icon { font-size: 15px; }

/* ── Skeleton ────────────────────────────────────────────────── */
@keyframes db-shimmer {
  0%   { background-position: -500px 0; }
  100% { background-position: 500px 0; }
}

.db-sk-text {
  background: linear-gradient(
    90deg,
    var(--db-border)   0%,
    var(--db-border-2) 50%,
    var(--db-border)   100%
  );
  background-size: 500px 100%;
  animation: db-shimmer 1.6s ease infinite;
  border-radius: 4px;
  color: transparent !important;
  user-select: none;
}

.db-tbl-skeleton { padding: 4px 0; }

.db-sk-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--db-border);
}
.db-sk-row:last-child { border-bottom: none; }

.db-sk-cell {
  height: 13px;
  border-radius: 4px;
  background: linear-gradient(
    90deg,
    var(--db-border)   0%,
    var(--db-border-2) 50%,
    var(--db-border)   100%
  );
  background-size: 500px 100%;
  animation: db-shimmer 1.6s ease infinite;
}
.db-sk-cell--ref  { width: 68px; }
.db-sk-cell--name { flex: 1; max-width: 155px; }
.db-sk-cell--date { width: 76px; }
.db-sk-cell--amt  { width: 72px; margin-left: auto; }
.db-sk-cell--pill { width: 54px; height: 20px; border-radius: 999px; }

.db-sk-cash-row { margin-bottom: 18px; }
.db-sk-cash-row:last-child { margin-bottom: 0; }

.db-sk-cash-label {
  height: 12px;
  width: 55%;
  border-radius: 4px;
  margin-bottom: 8px;
  background: linear-gradient(
    90deg,
    var(--db-border)   0%,
    var(--db-border-2) 50%,
    var(--db-border)   100%
  );
  background-size: 500px 100%;
  animation: db-shimmer 1.6s ease infinite;
}

.db-sk-cash-track {
  height: 6px;
  border-radius: 3px;
  background: linear-gradient(
    90deg,
    var(--db-border)   0%,
    var(--db-border-2) 50%,
    var(--db-border)   100%
  );
  background-size: 500px 100%;
  animation: db-shimmer 1.6s ease infinite;
}

/* ── Reduced motion ──────────────────────────────────────────── */
@media (prefers-reduced-motion: reduce) {
  .db-kpi,
  .db-kpi-icon-wrap,
  .db-btn,
  .db-qa,
  .db-qa-icon,
  .db-progress-fill { transition: none; }
  .db-sk-cell,
  .db-sk-text,
  .db-sk-cash-label,
  .db-sk-cash-track { animation: none; }
}
</style>
