<script setup>
import { ref } from "vue";

const plans = [
  { name: "Básico", price: "9 €", color: "secondary" },
  { name: "Profesional", price: "19 €", color: "secondary" },
  { name: "Empresa", price: "29 €", color: "primary" },
  { name: "Corporativo", price: "99 €", color: "secondary" },
];

const selectedPlan = ref(null);
const paymentMethod = ref("");

const selectPlan = (plan) => {
  selectedPlan.value = plan;
  paymentMethod.value = "";
};

const methods = [
  { id: "paypal", label: "PayPal" },
  { id: "stripe", label: "Stripe" },
];
</script>

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
                <th class="py-3">Corporativo</th>
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
                <td class="py-4">
                  <div class="h1 fw-bold mb-2">99 €</div>
                  <div class="h6 text-muted mb-0">por mes</div>
                </td>
              </tr>
              <tr>
                <td class="fw-semibold text-start">Facturas</td>
                <td>Ilimitadas</td>
                <td>Ilimitadas</td>
                <td>Ilimitadas</td>
                <td>Ilimitadas</td>
              </tr>
              <tr>
                <td class="fw-semibold text-start">Usuarios del equipo</td>
                <td>1</td>
                <td>3</td>
                <td>10</td>
                <td>Ilimitados</td>
              </tr>
              <tr>
                <td class="fw-semibold text-start">Clientes</td>
                <td>15</td>
                <td>100</td>
                <td>1000</td>
                <td>Ilimitados</td>
              </tr>
              <tr>
                <td class="fw-semibold text-start">Soporte</td>
                <td>Email</td>
                <td>Completo</td>
                <td>Prioritario</td>
                <td>Dedicado</td>
              </tr>
              <tr>
                <td class="fw-semibold text-start">Personalización de marca</td>
                <td>
                  <i class="fa fa-times fa-fw text-danger"></i>
                </td>
                <td>
                  <i class="fa fa-times fa-fw text-danger"></i>
                </td>
                <td>
                  <i class="fa fa-times fa-fw text-danger"></i>
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
                    :class="`btn-${plan.color}`"
                    @click="selectPlan(plan)"
                  >
                    Elegir este plan
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </BaseBlock>
    <div v-if="selectedPlan" class=" mt-4">
          <h4 class="mb-3">
            Plan seleccionado:
            <span class="fw-bold">{{ selectedPlan.name }} ({{ selectedPlan.price }})</span>
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
                    <label class="form-check-label ms-2" :for="`pay-${method.id}`">
                      {{ method.label }}
                    </label>
                  </div>
                </div>

              <div class="mb-3 mt-4 d-flex justify-content-between align-items-center">
                <div class="text-muted">
                  Selecciona PayPal o Stripe y continúa.
                </div>
                <button
                  type="button"
                  class="btn btn-primary"
                  :disabled="!paymentMethod"
                >
                  Continuar
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
