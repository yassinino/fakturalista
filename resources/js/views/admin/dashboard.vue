<template>
  <!-- Page Content -->
  <div class="content">
    <div v-if="statsError" class="alert alert-warning" role="alert">
      {{ statsError }}
    </div>
    <!-- Overview -->
    <div class="row items-push">
      <div class="col-sm-6 col-xxl-3">
        <!-- Pending Orders -->
        <BaseBlock class="d-flex flex-column h-100 mb-0">
          <template #content>
            <div
              class="block-content block-content-full flex-grow-1 d-flex justify-content-between align-items-center"
            >
              <dl class="mb-0">
                <dt class="fs-3 fw-bold">
                  {{ isLoadingStats ? '...' : stats.invoices }}
                </dt>
                <dd class="fs-sm fw-medium fs-sm fw-medium text-muted mb-0">
                  {{ $t("reports.cards.invoices") }}
                </dd>
              </dl>
              <div class="item item-rounded-lg bg-body-light">
                <i class="far fa-edit fs-3 text-primary"></i>
              </div>
            </div>
            <div class="bg-body-light rounded-bottom">
              <a
                class="block-content block-content-full block-content-sm fs-sm fw-medium d-flex align-items-center justify-content-between"
                href="javascript:void(0)"
              >
                <span>{{ $t("reports.actions.viewInvoices") }}</span>
                <i
                  class="fa fa-arrow-alt-circle-right ms-1 opacity-25 fs-base"
                ></i>
              </a>
            </div>
          </template>
        </BaseBlock>
        <!-- END Pending Orders -->
      </div>
      <div class="col-sm-6 col-xxl-3">
        <!-- New Customers -->
        <BaseBlock class="d-flex flex-column h-100 mb-0">
          <template #content>
            <div
              class="block-content block-content-full flex-grow-1 d-flex justify-content-between align-items-center"
            >
              <dl class="mb-0">
                <dt class="fs-3 fw-bold">
                  {{ isLoadingStats ? '...' : stats.customers }}
                </dt>
                <dd class="fs-sm fw-medium fs-sm fw-medium text-muted mb-0">
                  {{ $t("reports.cards.customers") }}
                </dd>
              </dl>
              <div class="item item-rounded-lg bg-body-light">
                <i class="far fa-user fs-3 text-primary"></i>
              </div>
            </div>
            <div class="bg-body-light rounded-bottom">
              <a
                class="block-content block-content-full block-content-sm fs-sm fw-medium d-flex align-items-center justify-content-between"
                href="javascript:void(0)"
              >
                <span>{{ $t("reports.actions.viewCustomers") }}</span>
                <i
                  class="fa fa-arrow-alt-circle-right ms-1 opacity-25 fs-base"
                ></i>
              </a>
            </div>
          </template>
        </BaseBlock>
        <!-- END New Customers -->
      </div>
      <div class="col-sm-6 col-xxl-3">
        <!-- Messages -->
        <BaseBlock class="d-flex flex-column h-100 mb-0">
          <template #content>
            <div
              class="block-content block-content-full flex-grow-1 d-flex justify-content-between align-items-center"
            >
              <dl class="mb-0">
                <dt class="fs-3 fw-bold">
                  {{ isLoadingStats ? '...' : stats.items }}
                </dt>
                <dd class="fs-sm fw-medium fs-sm fw-medium text-muted mb-0">
                  {{ $t("reports.cards.items") }}
                </dd>
              </dl>
              <div class="item item-rounded-lg bg-body-light">
                <i class="far fa-file fs-3 text-primary"></i>
              </div>
            </div>
            <div class="bg-body-light rounded-bottom">
              <a
                class="block-content block-content-full block-content-sm fs-sm fw-medium d-flex align-items-center justify-content-between"
                href="javascript:void(0)"
              >
                <span>{{ $t("reports.actions.viewItems") }}</span>
                <i
                  class="fa fa-arrow-alt-circle-right ms-1 opacity-25 fs-base"
                ></i>
              </a>
            </div>
          </template>
        </BaseBlock>
        <!-- END Messages -->
      </div>
      <div class="col-sm-6 col-xxl-3">
        <!-- Conversion Rate -->
        <BaseBlock class="d-flex flex-column h-100 mb-0">
          <template #content>
            <div
              class="block-content block-content-full flex-grow-1 d-flex justify-content-between align-items-center"
            >
              <dl class="mb-0">
                <dt class="fs-3 fw-bold">
                  {{ isLoadingStats ? '...' : formatCurrency(stats.totalEarnings) }}
                </dt>
                <dd class="fs-sm fw-medium fs-sm fw-medium text-muted mb-0">
                  {{ $t("reports.cards.totalEarnings") }}
                </dd>
              </dl>
              <div class="item item-rounded-lg bg-body-light">
                <i class="fa fa-chart-bar fs-3 text-primary"></i>
              </div>
            </div>
            <div class="bg-body-light rounded-bottom">
              <a
                class="block-content block-content-full block-content-sm fs-sm fw-medium d-flex align-items-center justify-content-between"
                href="javascript:void(0)"
              >
                <span>{{ $t("reports.actions.viewEarnings") }}</span>
                <i
                  class="fa fa-arrow-alt-circle-right ms-1 opacity-25 fs-base"
                ></i>
              </a>
            </div>
          </template>
        </BaseBlock>
        <!-- END Conversion Rate-->
      </div>
    </div>
    <!-- END Overview -->

    <!-- Últimas facturas -->
    <BaseBlock :title="$t('reports.latestInvoicesTitle')">
      <template #content>
        <div class="block-content">
          <div v-if="invoicesError" class="alert alert-warning" role="alert">
            {{ invoicesError }}
          </div>
          <div class="table-responsive">
            <table class="table table-hover table-vcenter">
              <thead>
                <tr>
                  <th>{{ $t("invoices.table.number") }}</th>
                  <th>{{ $t("invoices.table.customer") }}</th>
                  <th>{{ $t("invoices.table.date") }}</th>
                  <th class="text-end">{{ $t("invoices.table.subtotal") }}</th>
                  <th class="text-end">{{ $t("invoices.table.total") }}</th>
                  <th class="text-center">{{ $t("invoices.table.status") }}</th>
                </tr>
              </thead>
              <tbody class="fs-sm">
                <tr v-if="isLoadingInvoices">
                  <td colspan="6" class="text-center text-muted">{{ $t("common.loading") }}</td>
                </tr>
                <tr v-else-if="!lastInvoices.length">
                  <td colspan="6" class="text-center text-muted">
                    {{ $t("reports.emptyInvoices") }}
                  </td>
                </tr>
                <tr v-else v-for="invoice in lastInvoices" :key="invoice.uuid">
                  <td class="fw-semibold">{{ invoice.reference }}</td>
                  <td>{{ invoice.customer }}</td>
                  <td>{{ formatDate(invoice.date) }}</td>
                  <td class="text-end">{{ formatCurrency(invoice.sub_total) }}</td>
                  <td class="text-end">{{ formatCurrency(invoice.total) }}</td>
                  <td class="text-center">
                      <span 
                      class="fs-xs fw-semibold d-inline-block py-1 px-3 rounded-pill"
                      :class="invoice.status == 1 ? 'bg-success-light text-success' : 'bg-danger-light text-danger'">
                      {{ invoice.status == 1 ? $t('invoices.statusPaid') : $t('invoices.statusUnpaid') }}
                      </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </BaseBlock>
    <!-- END Últimas facturas -->

  </div>
  <!-- END Page Content -->
</template>


<script setup>
import { reactive, ref, onMounted } from "vue";
import axios from "axios";
import { useI18n } from "vue-i18n";

// vue-chartjs, for more info and examples you can check out https://vue-chartjs.org/ and http://www.chartjs.org/docs/ -->
import { Line, Bar } from "vue-chartjs";
import { Chart, registerables } from "chart.js";

Chart.register(...registerables);

// Set Global Chart.js configuration
Chart.defaults.color = "#818d96";
Chart.defaults.scale.grid.lineWidth = 0;
Chart.defaults.scale.beginAtZero = true;
Chart.defaults.datasets.bar.maxBarThickness = 45;
Chart.defaults.elements.bar.borderRadius = 4;
Chart.defaults.elements.bar.borderSkipped = false;
Chart.defaults.elements.point.radius = 0;
Chart.defaults.elements.point.hoverRadius = 0;
Chart.defaults.plugins.tooltip.radius = 3;
Chart.defaults.plugins.legend.labels.boxWidth = 10;

// Helper variables
const orderSearch = ref(false);
const isLoadingStats = ref(true);
const { locale, t } = useI18n();
const stats = reactive({
  invoices: 0,
  customers: 0,
  items: 0,
  totalEarnings: 0,
});
const statsError = ref("");
const localeMap = {
  es: "es-ES",
  en: "en-US",
  fr: "fr-FR",
};
const formatCurrency = (value) =>
  new Intl.NumberFormat(localeMap[locale.value] || "es-ES", {
    style: "currency",
    currency: "EUR",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value ?? 0));

const loadStats = async () => {
  isLoadingStats.value = true;
  statsError.value = "";

  try {
    const { data } = await axios.get("/stats/counts");
    stats.invoices = data?.invoices ?? 0;
    stats.customers = data?.customers ?? 0;
    stats.items = data?.items ?? 0;
    stats.totalEarnings = data?.total_earnings ?? 0;
  } catch (error) {
    statsError.value = t("reports.errors.stats");
  } finally {
    isLoadingStats.value = false;
  }
};

const lastInvoices = ref([]);
const isLoadingInvoices = ref(true);
const invoicesError = ref("");
const formatDate = (value) =>
  value ? new Date(value).toLocaleDateString(localeMap[locale.value] || "es-ES") : "";

const loadLastInvoices = async () => {
  isLoadingInvoices.value = true;
  invoicesError.value = "";

  try {
    const { data } = await axios.get("/invoices", {
      params: { per_page: 5 },
    });
    lastInvoices.value = data?.invoices ?? [];
  } catch (error) {
    invoicesError.value = t("reports.errors.invoices");
  } finally {
    isLoadingInvoices.value = false;
  }
};

onMounted(() => {
  loadStats();
  loadLastInvoices();
});


</script>
