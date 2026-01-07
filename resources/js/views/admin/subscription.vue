<template>
  <!-- Hero -->

  <!-- Page Content -->
  <div class="content">
    <!-- Modern Design -->

    <h2 class="content-heading">{{ $t("subscription.title") }}</h2>
    <BaseBlock>
      <template #content>
        <div class="table-responsive">
          <table
            class="table table-borderless table-hover table-vcenter text-center mb-0"
          >
            <thead class="table-dark text-uppercase fs-sm">
              <tr>
                <th class="py-3" style="width: 180px"></th>
                <th class="py-3">{{ $t("subscription.plans.basic") }}</th>
                <th class="py-3">{{ $t("subscription.plans.professional") }}</th>
                <th class="py-3 bg-primary">
                  <i class="fa fa-thumbs-up me-1"></i>
                  {{ $t("subscription.plans.enterprise") }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr class="bg-body-light">
                <td></td>
                <td class="py-4">
                  <div class="h1 fw-bold mb-2">9 €</div>
                  <div class="h6 text-muted mb-0">{{ $t("subscription.perMonth") }}</div>
                </td>
                <td class="py-4">
                  <div class="h1 fw-bold mb-2">19 €</div>
                  <div class="h6 text-muted mb-0">{{ $t("subscription.perMonth") }}</div>
                </td>
                <td class="py-4">
                  <div class="h1 fw-bold text-primary mb-2">29 €</div>
                  <div class="h6 text-primary-light mb-0">{{ $t("subscription.perMonth") }}</div>
                </td>
              </tr>
              <tr>
                <td class="fw-semibold text-start">{{ $t("subscription.features.invoices") }}</td>
                <td>10</td>
                <td>100</td>
                <td>{{ $t("subscription.values.unlimitedFeminine") }}</td>
              </tr>
              <tr>
                <td class="fw-semibold text-start">{{ $t("subscription.features.teamUsers") }}</td>
                <td>3</td>
                <td>10</td>
                <td>{{ $t("subscription.values.unlimitedMasculine") }}</td>
              </tr>
              <tr>
                <td class="fw-semibold text-start">{{ $t("subscription.features.customers") }}</td>
                <td>10</td>
                <td>100</td>
                <td>{{ $t("subscription.values.unlimitedMasculine") }}</td>
              </tr>
              <tr>
                <td class="fw-semibold text-start">{{ $t("subscription.features.support") }}</td>
                <td>{{ $t("subscription.values.supportEmail") }}</td>
                <td>{{ $t("subscription.values.supportFull") }}</td>
                <td>{{ $t("subscription.values.supportPriority") }}</td>
              </tr>
              <tr>
                <td class="fw-semibold text-start">
                  {{ $t("subscription.features.pdfCustomization") }}
                </td>
                <td>
                  <i class="fa fa-check fa-fw text-success"></i>
                </td>
                <td>
                  <i class="fa fa-check fa-fw text-success"></i>
                </td>
                <td>
                  <i class="fa fa-check fa-fw text-success"></i>
                </td>
              </tr>
              <tr class="bg-body-light">
                <td></td>
                <td v-for="plan in plans" :key="plan.name">
                  <button
                    type="button"
                    class="btn rounded-pill px-4"
                    :class="[
                      isCurrentPlan(plan) ? 'btn-outline-secondary' : 'btn-secondary'
                    ]"
                    :disabled="isCurrentPlan(plan)"
                    @click="selectPlan(plan)"
                  >
                    {{
                      isCurrentPlan(plan)
                        ? $t("subscription.currentPlan")
                        : $t("subscription.choosePlan")
                    }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </BaseBlock>
<div v-if="selectedPlan" class="mt-4">
  <h4 class="mb-3">
    {{ $t("subscription.selectedPlan") }}
    <span class="fw-bold">
      {{ getPlanLabel(selectedPlan) }} ({{ selectedPlan.price }})
    </span>
  </h4>

  <BaseBlock :title="$t('subscription.paymentMethodTitle')">
    <template #content>
      <div class="row g-3 content">
        <div
          class="col-md-6"
          v-for="method in methods"
          :key="method.id"
        >
          <div
            class="form-check border rounded p-3 h-100"
            :class="{
              'border-primary': paymentMethod === method.id,
              'cursor-pointer': true
            }"
            role="button"
            @click="paymentMethod = method.id"
          >
            <input
              class="form-check-input"
              type="radio"
              :id="`pay-${method.id}`"
              name="payment-method"
              :value="method.id"
              v-model="paymentMethod"
            />
            <label
              class="form-check-label ms-2"
              :for="`pay-${method.id}`"
            >
              {{ method.label }}
            </label>
          </div>
        </div>

        <!-- Message d'erreur -->
        <div class="col-12" v-if="errorMessage">
          <div class="alert alert-danger mb-0">
            {{ errorMessage }}
          </div>
        </div>

        <!-- Bouton continuer -->
        <div
          class="mb-3 mt-4 d-flex justify-content-between align-items-center"
        >
          <div class="text-muted">
            {{ $t("subscription.paymentHint") }}
          </div>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="!paymentMethod || !selectedPlan || loading"
            @click="continuePayment"
          >
            <span v-if="!loading">{{ $t("subscription.continue") }}</span>
            <span v-else>
              <i class="fa fa-sync fa-spin me-1"></i>
              {{ $t("subscription.processing") }}
            </span>
          </button>
        </div>
      </div>
    </template>
  </BaseBlock>
</div>

    <!-- END Classic Design -->
  </div>
  <!-- END Page Content -->
</template>


<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const plans = ref(true);
const planError = ref("");
const loadPlans = async () => {
  planError.value = "";
  try {
    const { data } = await axios.get("/plans");
    plans.value = data?.plans ?? [];
  } catch (error) {
    planError.value = t("subscription.errors.loadPlans");
  } finally {
   
  }
};

const currentSubscription = ref(null);
const loadCurrentSubscription = async () => {
  try {
    const { data } = await axios.get("/subscription");
    currentSubscription.value = data?.subscription ?? null;
  } catch (error) {
    console.error("No se pudo cargar la suscripción actual", error);
  }
};

onMounted(() => {
  loadPlans();
  loadCurrentSubscription();
});


const selectedPlan = ref(null);
const paymentMethod = ref("");
const loading = ref(false);
const errorMessage = ref("");
const planNameMap = {
  "B\u00e1sico": "basic",
  Basico: "basic",
  Profesional: "professional",
  Empresa: "enterprise",
};

const getPlanLabel = (plan) => {
  const key = planNameMap[plan?.name] ?? null;
  return key ? t(`subscription.plans.${key}`) : plan?.name ?? "";
};

const selectPlan = (plan) => {
  if (isCurrentPlan(plan)) {
    return;
  }

  selectedPlan.value = plan;
  paymentMethod.value = "";
  errorMessage.value = "";
};

const isCurrentPlan = (plan) => {
  const activeStatuses = ["active", "trialing"];
  const currentStatus = currentSubscription.value?.status ?? null;

  if (!currentStatus || !activeStatuses.includes(String(currentStatus).toLowerCase())) {
    return false;
  }

  const currentPlanId =
    currentSubscription.value?.plan_id ??
    currentSubscription.value?.plan?.id ??
    null;

  if (!currentPlanId && plan.status != 'active') {
    return false;
  }

  return Number(currentPlanId) === Number(plan.id);
};

const methods = [
  { id: "paypal", label: "PayPal" },
  { id: "stripe", label: "Stripe" },
];

const continuePayment = async () => {
  errorMessage.value = "";

  if (!selectedPlan.value) {
    errorMessage.value = t("subscription.errors.selectPlan");
    return;
  }

  if (!paymentMethod.value) {
    errorMessage.value = t("subscription.errors.selectPaymentMethod");
    return;
  }

  // Pour l'instant on implémente Stripe d’abord
  if (paymentMethod.value === "stripe") {
    try {
      loading.value = true;

      const response = await axios.post("/subscription/checkout", {
        plan_id: selectedPlan.value.id
      });

      const checkoutUrl = response.data.checkout_url;

      if (!checkoutUrl) {
        throw new Error(t("subscription.errors.missingCheckoutUrl"));
      }

      // 🔥 Redirection vers la page sécurisée Stripe
      window.location.href = checkoutUrl;
    } catch (error) {
      console.error(error);
      errorMessage.value =
        error.response?.data?.message ||
        t("subscription.errors.stripeStart");
    } finally {
      loading.value = false;
    }
  } else if (paymentMethod.value === "paypal") {
    // On laissera ce bloc pour plus tard
    errorMessage.value = t("subscription.errors.paypalSoon");
  }
};
</script>
