<template>
  <div class="rp-wrap content">

    <!-- ── HEADER ──────────────────────────────────────────────── -->
    <div class="rp-header">
      <div class="rp-header-left">
        <h1 class="rp-title">{{ $t('reportsPage.title') }}</h1>
        <p class="rp-subtitle">{{ $t('reportsPage.subtitle') }}</p>
      </div>
      <div class="rp-header-right">
        <div class="rp-period-wrap">
          <select v-model="period" @change="onPeriodChange" class="rp-period-select">
            <option v-for="p in periodOptions" :key="p" :value="p">{{ $t('reportsPage.periods.' + p) }}</option>
          </select>
          <i class="fa fa-chevron-down rp-period-chevron"></i>
        </div>
        <div v-if="period === 'custom'" class="rp-custom-range">
          <input type="date" v-model="customFrom" class="rp-date-input" :max="customTo || undefined" />
          <span class="rp-custom-sep">–</span>
          <input type="date" v-model="customTo" class="rp-date-input" :min="customFrom || undefined" />
          <button type="button" class="rp-btn rp-btn--primary rp-btn--sm" :disabled="!customFrom || !customTo" @click="applyCustom">
            {{ $t('reportsPage.apply') }}
          </button>
        </div>
        <button type="button" class="rp-btn rp-btn--ghost" :disabled="isExporting || isLoading" @click="exportCsv">
          <i class="fa fa-arrow-down-to-line rp-btn-icon" v-if="!isExporting"></i>
          <i class="fa fa-spinner fa-spin rp-btn-icon" v-else></i>
          {{ isExporting ? $t('reportsPage.exporting') : $t('reportsPage.export') }}
        </button>
      </div>
    </div>

    <!-- ── LOADING SKELETON ────────────────────────────────────── -->
    <div v-if="isLoading" class="rp-skeleton">
      <div class="rp-kpi-grid">
        <div class="rp-kpi" v-for="i in 4" :key="'sk-kpi-' + i">
          <div class="rp-sk-icon"></div>
          <div class="rp-kpi-body">
            <div class="rp-sk-cell rp-sk-cell--label"></div>
            <div class="rp-sk-cell rp-sk-cell--value"></div>
            <div class="rp-sk-cell rp-sk-cell--meta"></div>
          </div>
        </div>
      </div>
      <div class="rp-card">
        <div class="rp-card-head"><div class="rp-sk-cell rp-sk-cell--title"></div></div>
        <div class="rp-sk-chart"></div>
      </div>
    </div>

    <!-- ── ERROR STATE ─────────────────────────────────────────── -->
    <div v-else-if="loadError" class="rp-state-card">
      <i class="fa fa-triangle-exclamation rp-state-icon"></i>
      <p class="rp-state-title">{{ $t('reportsPage.errors.load') }}</p>
      <button type="button" class="rp-btn rp-btn--ghost rp-btn--sm" @click="fetchReports">{{ $t('common.retry') }}</button>
    </div>

    <!-- ── EMPTY STATE ─────────────────────────────────────────── -->
    <div v-else-if="isEmpty" class="rp-state-card">
      <i class="fa fa-chart-line rp-state-icon"></i>
      <p class="rp-state-title">{{ $t('reportsPage.empty.title') }}</p>
      <p class="rp-state-text">{{ $t('reportsPage.empty.text') }}</p>
    </div>

    <!-- ── CONTENT ─────────────────────────────────────────────── -->
    <template v-else-if="report">

      <!-- KPI cards -->
      <div class="rp-kpi-grid">
        <div class="rp-kpi" v-for="k in kpiCards" :key="k.key">
          <div class="rp-kpi-icon-wrap" :class="'rp-kpi-icon-wrap--' + k.color">
            <i :class="k.icon"></i>
          </div>
          <div class="rp-kpi-body">
            <p class="rp-kpi-label">{{ k.label }}</p>
            <p class="rp-kpi-value">{{ k.value }}</p>
            <p class="rp-kpi-trend" :class="'rp-kpi-trend--' + k.trend">
              <i class="fa" :class="trendIcon(k.trend)"></i>
              {{ Math.abs(k.changePct) }}%
              <span class="rp-kpi-trend-label">{{ $t('reportsPage.vsPreviousPeriod') }}</span>
            </p>
          </div>
        </div>
      </div>

      <!-- Main chart -->
      <div class="rp-card">
        <div class="rp-card-head">
          <h3 class="rp-card-title">{{ $t('reportsPage.chart.title') }}</h3>
        </div>
        <div class="rp-chart-wrap">
          <Line :key="'chart-' + isDark" :data="chartData" :options="chartOptions" />
        </div>
      </div>

      <!-- Status + Payment methods -->
      <div class="rp-split-grid">
        <div class="rp-card">
          <div class="rp-card-head">
            <h3 class="rp-card-title">{{ $t('reportsPage.invoiceStatus.title') }}</h3>
          </div>
          <div class="rp-bar-list">
            <div v-for="s in report.invoice_status" :key="s.status" class="rp-bar-row">
              <span class="rp-bar-dot" :class="'rp-bar-dot--' + statusColor(s.status)"></span>
              <span class="rp-bar-label">{{ $t('reportsPage.invoiceStatus.' + s.status) }}</span>
              <div class="rp-bar-track">
                <div class="rp-bar-fill" :class="'rp-bar-fill--' + statusColor(s.status)" :style="{ width: s.percent + '%' }"></div>
              </div>
              <span class="rp-bar-value">{{ s.count }} · {{ s.percent }}%</span>
            </div>
          </div>
        </div>

        <div class="rp-card">
          <div class="rp-card-head">
            <h3 class="rp-card-title">{{ $t('reportsPage.paymentMethods.title') }}</h3>
          </div>
          <div class="rp-bar-list" v-if="report.payment_methods.length">
            <div v-for="(m, i) in report.payment_methods" :key="m.method" class="rp-bar-row">
              <span class="rp-bar-dot" :class="'rp-bar-dot--' + methodColor(i)"></span>
              <span class="rp-bar-label">{{ methodLabel(m.method) }}</span>
              <div class="rp-bar-track">
                <div class="rp-bar-fill" :class="'rp-bar-fill--' + methodColor(i)" :style="{ width: m.percent + '%' }"></div>
              </div>
              <span class="rp-bar-value">{{ formatCurrency(m.total) }} · {{ m.percent }}%</span>
            </div>
          </div>
          <p v-else class="rp-empty-inline">{{ $t('reportsPage.topClients.empty') }}</p>
        </div>
      </div>

      <!-- Top clients -->
      <div class="rp-card">
        <div class="rp-card-head">
          <h3 class="rp-card-title">{{ $t('reportsPage.topClients.title') }}</h3>
        </div>
        <div class="rp-table-wrap" v-if="report.top_clients.length">
          <table class="rp-table">
            <thead>
              <tr>
                <th>{{ $t('reportsPage.topClients.client') }}</th>
                <th class="rp-td-right">{{ $t('reportsPage.topClients.invoicesCount') }}</th>
                <th class="rp-td-right">{{ $t('reportsPage.topClients.totalInvoiced') }}</th>
                <th class="rp-td-right">{{ $t('reportsPage.topClients.totalCollected') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in report.top_clients" :key="c.client">
                <td>{{ c.client }}</td>
                <td class="rp-td-right">{{ c.invoices_count }}</td>
                <td class="rp-td-right rp-td-amount">{{ formatCurrency(c.invoiced) }}</td>
                <td class="rp-td-right rp-td-amount">{{ formatCurrency(c.collected) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="rp-empty-inline">{{ $t('reportsPage.topClients.empty') }}</p>
      </div>

      <!-- Performance -->
      <div class="rp-card">
        <div class="rp-card-head">
          <h3 class="rp-card-title">{{ $t('reportsPage.performance.title') }}</h3>
        </div>
        <div class="rp-perf-grid">
          <div class="rp-perf-item">
            <p class="rp-perf-label">{{ $t('reportsPage.performance.avgInvoiceValue') }}</p>
            <p class="rp-perf-value">{{ formatCurrency(report.performance.avg_invoice_value) }}</p>
          </div>
          <div class="rp-perf-item">
            <p class="rp-perf-label">{{ $t('reportsPage.performance.collectionRate') }}</p>
            <p class="rp-perf-value">{{ report.performance.collection_rate }}%</p>
          </div>
          <div class="rp-perf-item">
            <p class="rp-perf-label">{{ $t('reportsPage.performance.unpaidAmount') }}</p>
            <p class="rp-perf-value">{{ formatCurrency(report.performance.unpaid_amount) }}</p>
          </div>
          <div class="rp-perf-item">
            <p class="rp-perf-label">{{ $t('reportsPage.performance.clientsInvoiced') }}</p>
            <p class="rp-perf-value">{{ report.performance.clients_invoiced }}</p>
          </div>
        </div>
      </div>

    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import axios from "axios";
import { useI18n } from "vue-i18n";
import { useTemplateStore } from "@/stores/template";
import { createToaster } from "@meforma/vue-toaster";
import { Line } from "vue-chartjs";
import {
  Chart as ChartJS,
  LineElement,
  PointElement,
  LinearScale,
  CategoryScale,
  Tooltip,
  Legend,
  Filler,
} from "chart.js";

ChartJS.register(LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend, Filler);

const { t, locale } = useI18n();
const store = useTemplateStore();
const toaster = createToaster();

const localeMap = { es: "es-ES", en: "en-US", fr: "fr-FR", ar: "ar-MA" };

const periodOptions = ["this_month", "last_month", "last_3_months", "last_6_months", "this_year", "custom"];

const period = ref("this_month");
const customFrom = ref("");
const customTo = ref("");
const appliedFrom = ref("");
const appliedTo = ref("");

const isLoading = ref(true);
const loadError = ref(false);
const isExporting = ref(false);
const report = ref(null);

const isDark = computed(() => store.settings.darkMode);

async function fetchReports() {
  isLoading.value = true;
  loadError.value = false;
  try {
    const params = { period: period.value };
    if (period.value === "custom" && appliedFrom.value && appliedTo.value) {
      params.from = appliedFrom.value;
      params.to = appliedTo.value;
    }
    const { data } = await axios.get("/reports/summary", { params });
    report.value = data;
  } catch {
    loadError.value = true;
  } finally {
    isLoading.value = false;
  }
}

function onPeriodChange() {
  if (period.value !== "custom") fetchReports();
}

function applyCustom() {
  if (!customFrom.value || !customTo.value) return;
  appliedFrom.value = customFrom.value;
  appliedTo.value = customTo.value;
  fetchReports();
}

async function exportCsv() {
  isExporting.value = true;
  try {
    const params = { period: period.value };
    if (period.value === "custom" && appliedFrom.value && appliedTo.value) {
      params.from = appliedFrom.value;
      params.to = appliedTo.value;
    }
    const response = await axios.get("/reports/export", { params, responseType: "blob" });
    const url = URL.createObjectURL(new Blob([response.data], { type: "text/csv" }));
    const link = document.createElement("a");
    link.href = url;
    link.download = `fakturalista-report-${period.value}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  } catch {
    toaster.error(t("reportsPage.exportError"));
  } finally {
    isExporting.value = false;
  }
}

onMounted(fetchReports);

const isEmpty = computed(() =>
  !!report.value && report.value.kpis.invoices_count.value === 0 && report.value.kpis.revenue.value === 0
);

const formatCurrency = (value) =>
  new Intl.NumberFormat(localeMap[locale.value] || "fr-FR", {
    style: "currency",
    currency: store.company.currency || "MAD",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value ?? 0));

const formatCompact = (value) =>
  new Intl.NumberFormat(localeMap[locale.value] || "fr-FR", {
    notation: "compact",
    maximumFractionDigits: 1,
  }).format(Number(value ?? 0));

function trendIcon(trend) {
  return trend === "up" ? "fa-arrow-up" : trend === "down" ? "fa-arrow-down" : "fa-minus";
}

const STATUS_COLORS = { paid: "green", pending: "blue", overdue: "orange", cancelled: "red" };
function statusColor(status) {
  return STATUS_COLORS[status] || "muted";
}

const METHOD_COLORS = ["pink", "blue", "green", "orange", "purple", "muted"];
function methodColor(index) {
  return METHOD_COLORS[index % METHOD_COLORS.length];
}

const METHOD_LABEL_KEYS = ["cash", "bank_transfer", "card", "stripe", "check", "other", "unspecified"];
function methodLabel(method) {
  return METHOD_LABEL_KEYS.includes(method) ? t("reportsPage.paymentMethods." + method) : method;
}

const kpiCards = computed(() => {
  if (!report.value) return [];
  const k = report.value.kpis;

  return [
    { key: "revenue",        icon: "fa fa-sack-dollar",    color: "pink",   label: t("reportsPage.kpis.revenue"),       value: formatCurrency(k.revenue.value),        changePct: k.revenue.change_pct,        trend: k.revenue.trend },
    { key: "collected",      icon: "fa fa-circle-check",   color: "green",  label: t("reportsPage.kpis.collected"),     value: formatCurrency(k.collected.value),      changePct: k.collected.change_pct,      trend: k.collected.trend },
    { key: "outstanding",    icon: "fa fa-hourglass-half", color: "orange", label: t("reportsPage.kpis.outstanding"),   value: formatCurrency(k.outstanding.value),    changePct: k.outstanding.change_pct,    trend: k.outstanding.trend },
    { key: "invoices_count", icon: "fa fa-file-invoice",   color: "blue",   label: t("reportsPage.kpis.invoicesCount"), value: String(k.invoices_count.value),         changePct: k.invoices_count.change_pct, trend: k.invoices_count.trend },
  ];
});

const chartData = computed(() => {
  if (!report.value) return { labels: [], datasets: [] };
  const c = report.value.chart;

  return {
    labels: c.labels,
    datasets: [
      {
        label: t("reportsPage.chart.invoiced"),
        data: c.invoiced,
        borderColor: "#E91E63",
        backgroundColor: "rgba(233, 30, 99, 0.10)",
        fill: true,
        tension: 0.35,
        borderWidth: 2.5,
        pointRadius: 0,
        pointHoverRadius: 4,
        pointBackgroundColor: "#E91E63",
      },
      {
        label: t("reportsPage.chart.collected"),
        data: c.collected,
        borderColor: "#10b981",
        backgroundColor: "rgba(16, 185, 129, 0.08)",
        fill: true,
        tension: 0.35,
        borderWidth: 2.5,
        pointRadius: 0,
        pointHoverRadius: 4,
        pointBackgroundColor: "#10b981",
      },
    ],
  };
});

const chartOptions = computed(() => {
  const gridColor = isDark.value ? "rgba(255, 255, 255, 0.08)" : "rgba(13, 17, 23, 0.06)";
  const textColor = isDark.value ? "#8b96a8" : "#445066";

  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: {
      legend: {
        display: true,
        position: "top",
        align: "end",
        labels: { color: textColor, usePointStyle: true, boxWidth: 8, padding: 16, font: { size: 12 } },
      },
      tooltip: {
        backgroundColor: isDark.value ? "#1a1f2b" : "#ffffff",
        titleColor: textColor,
        bodyColor: textColor,
        borderColor: gridColor,
        borderWidth: 1,
        padding: 10,
        boxPadding: 4,
        callbacks: {
          label: (ctx) => ` ${ctx.dataset.label}: ${formatCurrency(ctx.parsed.y)}`,
        },
      },
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: { color: textColor, font: { size: 11 } },
      },
      y: {
        beginAtZero: true,
        grid: { color: gridColor },
        ticks: { color: textColor, font: { size: 11 }, callback: (v) => formatCompact(v) },
      },
    },
  };
});
</script>

<style scoped>
/* ── Tokens: Light ──────────────────────────────────────────── */
.rp-wrap {
  --rp-bg:          #f3f5f8;
  --rp-surface:     #ffffff;
  --rp-surface-2:   #f8fafc;
  --rp-border:      #e6eaef;
  --rp-border-2:    #ccd2db;
  --rp-text-1:      #0d1117;
  --rp-text-2:      #445066;
  --rp-text-3:      #8b96a8;
  --rp-pink:        var(--brand-primary);
  --rp-pink-bg:     rgba(233, 30, 99, 0.08);
  --rp-green:       #00a854;
  --rp-green-bg:    rgba(0, 168, 84, 0.09);
  --rp-blue:        #2563eb;
  --rp-blue-bg:     rgba(37, 99, 235, 0.09);
  --rp-orange:      #e07b00;
  --rp-orange-bg:   rgba(224, 123, 0, 0.09);
  --rp-red:         #dc2626;
  --rp-red-bg:      rgba(220, 38, 38, 0.09);
  --rp-purple:      #7c3aed;
  --rp-purple-bg:   rgba(124, 58, 237, 0.09);
  --rp-shadow-sm:   0 1px 4px rgba(13, 17, 23, 0.06), 0 2px 8px rgba(13, 17, 23, 0.05);
  --rp-shadow-md:   0 4px 16px rgba(13, 17, 23, 0.10), 0 1px 4px rgba(13, 17, 23, 0.05);
  --rp-radius:      14px;
  --rp-ease:        cubic-bezier(0.4, 0, 0.2, 1);
}

/* ── Tokens: Dark ───────────────────────────────────────────── */
:global(.dark-mode) .rp-wrap {
  --rp-bg:        var(--dark-bg);
  --rp-surface:   var(--dark-surface);
  --rp-surface-2: var(--dark-surface-elevated);
  --rp-border:    var(--dark-border-subtle);
  --rp-border-2:  var(--dark-border);
  --rp-text-1:    var(--dark-text);
  --rp-text-2:    var(--dark-text-muted);
  --rp-text-3:    var(--dark-text-disabled);
  --rp-pink-bg:   rgba(233, 30, 99, 0.14);
  --rp-green-bg:  rgba(0, 168, 84, 0.14);
  --rp-blue-bg:   rgba(37, 99, 235, 0.14);
  --rp-orange-bg: rgba(224, 123, 0, 0.14);
  --rp-red-bg:    rgba(220, 38, 38, 0.14);
  --rp-purple-bg: rgba(124, 58, 237, 0.14);
  --rp-shadow-sm: 0 1px 4px rgba(0, 0, 0, 0.35), 0 2px 8px rgba(0, 0, 0, 0.25);
  --rp-shadow-md: 0 4px 16px rgba(0, 0, 0, 0.45), 0 1px 4px rgba(0, 0, 0, 0.3);
}

.rp-wrap {
  background: var(--rp-bg);
  color: var(--rp-text-1);
  padding-bottom: 48px;
}

/* ── Header ─────────────────────────────────────────────────── */
.rp-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 26px;
  padding-top: 6px;
}
.rp-title {
  font-size: 26px;
  font-weight: 750;
  color: var(--rp-text-1);
  letter-spacing: -0.035em;
  margin: 0 0 5px;
  line-height: 1.2;
}
.rp-subtitle {
  font-size: 14px;
  color: var(--rp-text-3);
  margin: 0;
  line-height: 1.5;
}
.rp-header-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.rp-period-wrap { position: relative; }
.rp-period-select {
  appearance: none;
  background: var(--rp-surface);
  border: 1px solid var(--rp-border);
  color: var(--rp-text-1);
  border-radius: 10px;
  padding: 8px 34px 8px 14px;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  min-width: 168px;
  transition: border-color 0.15s var(--rp-ease);
}
.rp-period-select:focus { outline: none; border-color: var(--rp-pink); }
.rp-period-chevron {
  position: absolute;
  right: 13px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 10px;
  color: var(--rp-text-3);
  pointer-events: none;
}

.rp-custom-range { display: flex; align-items: center; gap: 8px; }
.rp-date-input {
  background: var(--rp-surface);
  border: 1px solid var(--rp-border);
  color: var(--rp-text-1);
  border-radius: 9px;
  padding: 7px 10px;
  font-size: 13px;
}
.rp-date-input:focus { outline: none; border-color: var(--rp-pink); }
.rp-custom-sep { color: var(--rp-text-3); }

/* ── Buttons ────────────────────────────────────────────────── */
.rp-btn {
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
  transition: background .15s var(--rp-ease), color .15s var(--rp-ease),
              box-shadow .15s var(--rp-ease), transform .15s var(--rp-ease),
              border-color .15s var(--rp-ease);
  white-space: nowrap;
  line-height: 1;
}
.rp-btn:disabled { opacity: 0.55; cursor: not-allowed; transform: none !important; }
.rp-btn--primary { background: var(--brand-primary); color: #fff; }
.rp-btn--primary:hover:not(:disabled) {
  background: var(--brand-primary-hover);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(233, 30, 99, 0.22);
}
.rp-btn--ghost { background: var(--rp-surface); color: var(--rp-text-2); border-color: var(--rp-border); }
.rp-btn--ghost:hover:not(:disabled) {
  border-color: var(--rp-border-2);
  color: var(--rp-text-1);
  background: var(--rp-surface-2);
  transform: translateY(-1px);
}
.rp-btn--sm { padding: 6px 12px; font-size: 13px; border-radius: 8px; }
.rp-btn-icon { font-size: 12px; }

/* ── KPI Grid ───────────────────────────────────────────────── */
.rp-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}
@media (max-width: 1100px) { .rp-kpi-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 580px)  { .rp-kpi-grid { grid-template-columns: 1fr; } }

.rp-kpi {
  background: var(--rp-surface);
  border: 1px solid var(--rp-border);
  border-radius: var(--rp-radius);
  padding: 20px 20px 18px;
  display: flex;
  align-items: flex-start;
  gap: 14px;
  box-shadow: var(--rp-shadow-sm);
  transition: box-shadow 0.2s var(--rp-ease), transform 0.16s var(--rp-ease), border-color 0.2s var(--rp-ease);
}
.rp-kpi:hover { box-shadow: var(--rp-shadow-md); transform: translateY(-2px); border-color: var(--rp-border-2); }

.rp-kpi-icon-wrap {
  width: 42px;
  height: 42px;
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  flex-shrink: 0;
}
.rp-kpi-icon-wrap--pink   { background: var(--rp-pink-bg);   color: var(--rp-pink); }
.rp-kpi-icon-wrap--green  { background: var(--rp-green-bg);  color: var(--rp-green); }
.rp-kpi-icon-wrap--blue   { background: var(--rp-blue-bg);   color: var(--rp-blue); }
.rp-kpi-icon-wrap--orange { background: var(--rp-orange-bg); color: var(--rp-orange); }

.rp-kpi-body { flex: 1; min-width: 0; }
.rp-kpi-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--rp-text-3);
  margin: 0 0 7px;
}
.rp-kpi-value {
  font-size: 22px;
  font-weight: 720;
  color: var(--rp-text-1);
  letter-spacing: -0.03em;
  margin: 0 0 6px;
  font-variant-numeric: tabular-nums;
  line-height: 1.15;
}
.rp-kpi-trend {
  font-size: 12px;
  font-weight: 600;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 5px;
}
.rp-kpi-trend i { font-size: 10px; }
.rp-kpi-trend--up   { color: var(--rp-green); }
.rp-kpi-trend--down { color: var(--rp-red); }
.rp-kpi-trend--flat { color: var(--rp-text-3); }
.rp-kpi-trend-label { font-weight: 400; color: var(--rp-text-3); }

/* ── Cards ──────────────────────────────────────────────────── */
.rp-card {
  background: var(--rp-surface);
  border: 1px solid var(--rp-border);
  border-radius: var(--rp-radius);
  box-shadow: var(--rp-shadow-sm);
  overflow: hidden;
  margin-bottom: 20px;
}
.rp-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 15px 20px;
  border-bottom: 1px solid var(--rp-border);
  border-top: 3px solid var(--rp-pink);
}
.rp-card-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.09em;
  color: var(--rp-text-3);
  margin: 0;
}

/* ── Chart ──────────────────────────────────────────────────── */
.rp-chart-wrap {
  padding: 20px;
  height: 340px;
}
@media (max-width: 640px) { .rp-chart-wrap { height: 280px; padding: 14px; } }

/* ── Split grid (status + payment methods) ─────────────────── */
.rp-split-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 0;
}
@media (max-width: 900px) { .rp-split-grid { grid-template-columns: 1fr; } }

.rp-bar-list { padding: 16px 20px 20px; display: flex; flex-direction: column; gap: 16px; }
.rp-bar-row { display: flex; align-items: center; gap: 10px; }
.rp-bar-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.rp-bar-dot--pink   { background: var(--rp-pink); }
.rp-bar-dot--green  { background: var(--rp-green); }
.rp-bar-dot--blue   { background: var(--rp-blue); }
.rp-bar-dot--orange { background: var(--rp-orange); }
.rp-bar-dot--red    { background: var(--rp-red); }
.rp-bar-dot--purple { background: var(--rp-purple); }
.rp-bar-dot--muted  { background: var(--rp-text-3); }

.rp-bar-label { font-size: 13px; color: var(--rp-text-2); width: 108px; flex-shrink: 0; }
.rp-bar-track {
  flex: 1;
  height: 7px;
  border-radius: 999px;
  background: var(--rp-surface-2);
  overflow: hidden;
}
.rp-bar-fill { height: 100%; border-radius: 999px; transition: width 0.4s var(--rp-ease); }
.rp-bar-fill--pink   { background: var(--rp-pink); }
.rp-bar-fill--green  { background: var(--rp-green); }
.rp-bar-fill--blue   { background: var(--rp-blue); }
.rp-bar-fill--orange { background: var(--rp-orange); }
.rp-bar-fill--red    { background: var(--rp-red); }
.rp-bar-fill--purple { background: var(--rp-purple); }
.rp-bar-fill--muted  { background: var(--rp-text-3); }
.rp-bar-value {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--rp-text-1);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
  flex-shrink: 0;
}

/* ── Top clients table ─────────────────────────────────────── */
.rp-table-wrap { overflow-x: auto; }
.rp-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.rp-table thead th {
  text-align: left;
  padding: 10px 20px;
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--rp-text-3);
  background: var(--rp-surface-2);
  border-bottom: 1px solid var(--rp-border);
  white-space: nowrap;
}
.rp-table tbody td {
  padding: 12px 20px;
  color: var(--rp-text-2);
  border-bottom: 1px solid var(--rp-border);
  white-space: nowrap;
}
.rp-table tbody tr:last-child td { border-bottom: none; }
.rp-td-right { text-align: right; }
.rp-td-amount { color: var(--rp-text-1); font-weight: 600; font-variant-numeric: tabular-nums; }

/* ── Performance grid ───────────────────────────────────────── */
.rp-perf-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
  padding: 20px;
}
@media (max-width: 900px) { .rp-perf-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .rp-perf-grid { grid-template-columns: 1fr; } }

.rp-perf-item { text-align: center; }
.rp-perf-label {
  font-size: 11.5px;
  color: var(--rp-text-3);
  margin: 0 0 6px;
  font-weight: 600;
}
.rp-perf-value {
  font-size: 20px;
  font-weight: 720;
  color: var(--rp-text-1);
  margin: 0;
  font-variant-numeric: tabular-nums;
}

/* ── Empty inline (no data within a card) ───────────────────── */
.rp-empty-inline {
  padding: 32px 20px;
  text-align: center;
  color: var(--rp-text-3);
  font-size: 13px;
  margin: 0;
}

/* ── Empty / error state (whole page) ───────────────────────── */
.rp-state-card {
  background: var(--rp-surface);
  border: 1px solid var(--rp-border);
  border-radius: var(--rp-radius);
  box-shadow: var(--rp-shadow-sm);
  padding: 64px 24px;
  text-align: center;
}
.rp-state-icon { font-size: 30px; color: var(--rp-text-3); margin-bottom: 14px; display: block; }
.rp-state-title { font-size: 15px; font-weight: 700; color: var(--rp-text-1); margin: 0 0 6px; }
.rp-state-text { font-size: 13.5px; color: var(--rp-text-3); margin: 0 0 16px; }

/* ── Skeleton ───────────────────────────────────────────────── */
@keyframes rp-shimmer { 0% { background-position: -500px 0; } 100% { background-position: 500px 0; } }
.rp-sk-icon,
.rp-sk-cell,
.rp-sk-chart {
  background: linear-gradient(90deg, var(--rp-border) 0%, var(--rp-border-2) 50%, var(--rp-border) 100%);
  background-size: 500px 100%;
  animation: rp-shimmer 1.6s ease infinite;
}
.rp-sk-icon { width: 42px; height: 42px; border-radius: 11px; flex-shrink: 0; }
.rp-sk-cell { border-radius: 4px; height: 12px; margin-bottom: 8px; }
.rp-sk-cell--label { width: 70%; height: 10px; }
.rp-sk-cell--value { width: 85%; height: 18px; }
.rp-sk-cell--meta   { width: 55%; height: 10px; margin-bottom: 0; }
.rp-sk-cell--title  { width: 160px; height: 11px; margin-bottom: 0; }
.rp-sk-chart { height: 300px; margin: 20px; border-radius: 10px; }

/* ── RTL (Arabic) ─────────────────────────────────────────────
   Numeric table columns (.rp-td-right) intentionally stay
   right-aligned in RTL too - same convention already used for the
   invoice/quote line-item tables elsewhere in the app. */
:global(.rtl-support) .rp-period-select { padding: 8px 14px 8px 34px; }
:global(.rtl-support) .rp-period-chevron { right: auto; left: 13px; }
</style>
