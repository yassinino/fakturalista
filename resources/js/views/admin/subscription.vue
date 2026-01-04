<template>
  <!-- Hero -->

  <!-- Page Content -->
  <div class="content">
    <!-- Modern Design -->

    <h2 class="content-heading">Elige un plan para seguir usando en Fakturalista</h2>
    <BaseBlock>
      <template #content>
        <div class="table-responsive">
          <table
            class="table table-borderless table-hover table-vcenter text-center mb-0"
          >
            <thead class="table-dark text-uppercase fs-sm">
              <tr>
                <th class="py-3" style="width: 180px"></th>
                <th class="py-3">Básico</th>
                <th class="py-3">Profesional</th>
                <th class="py-3 bg-primary">
                  <i class="fa fa-thumbs-up me-1"></i> Empresa
                </th>
              </tr>
            </thead>
            <tbody>
              <tr class="bg-body-light">
                <td></td>
                <td class="py-4">
                  <div class="h1 fw-bold mb-2">9 €</div>
                  <div class="h6 text-muted mb-0">por mes</div>
                </td>
                <td class="py-4">
                  <div class="h1 fw-bold mb-2">19 €</div>
                  <div class="h6 text-muted mb-0">por mes</div>
                </td>
                <td class="py-4">
                  <div class="h1 fw-bold text-primary mb-2">29 €</div>
                  <div class="h6 text-primary-light mb-0">por mes</div>
                </td>
              </tr>
              <tr>
                <td class="fw-semibold text-start">Facturas</td>
                <td>10</td>
                <td>100</td>
                <td>Ilimitadas</td>
              </tr>
              <tr>
                <td class="fw-semibold text-start">Usuarios del equipo</td>
                <td>3</td>
                <td>10</td>
                <td>Ilimitados</td>
              </tr>
              <tr>
                <td class="fw-semibold text-start">Clientes</td>
                <td>10</td>
                <td>100</td>
                <td>Ilimitados</td>
              </tr>
              <tr>
                <td class="fw-semibold text-start">Soporte</td>
                <td>Email</td>
                <td>Completo</td>
                <td>Prioritario</td>
              </tr>
              <tr>
                <td class="fw-semibold text-start">Personalización de template PDF</td>
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
                    {{ isCurrentPlan(plan) ? "Tu plan actual" : "Elegir este plan" }}
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
    Plan seleccionado:
    <span class="fw-bold">
      {{ selectedPlan.name }} ({{ selectedPlan.price }})
    </span>
  </h4>

  <BaseBlock title="Método de pago">
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
            Selecciona PayPal o Stripe y continúa.
          </div>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="!paymentMethod || !selectedPlan || loading"
            @click="continuePayment"
          >
            <span v-if="!loading">Continuar</span>
            <span v-else>
              <i class="fa fa-sync fa-spin me-1"></i> Procesando...
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

const plans = ref(true);
const planError = ref("");
const loadPlans = async () => {
  planError.value = "";
  try {
    const { data } = await axios.get("/plans");
    plans.value = data?.plans ?? [];
  } catch (error) {
    planError.value = "No se pudieron cargar planes.";
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
    errorMessage.value = "Por favor, selecciona un plan.";
    return;
  }

  if (!paymentMethod.value) {
    errorMessage.value = "Por favor, selecciona un método de pago.";
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
        throw new Error("No se ha recibido la URL de pago.");
      }

      // 🔥 Redirection vers la page sécurisée Stripe
      window.location.href = checkoutUrl;
    } catch (error) {
      console.error(error);
      errorMessage.value =
        error.response?.data?.message ||
        "Ha ocurrido un error al iniciar el pago con Stripe.";
    } finally {
      loading.value = false;
    }
  } else if (paymentMethod.value === "paypal") {
    // On laissera ce bloc pour plus tard
    errorMessage.value =
      "El pago con PayPal estará disponible próximamente.";
  }
};
</script>
