<template>
  <div class="content">
    <div class="inv-create">

      <!-- ── TOP BAR ── -->
      <div class="inv-topbar">
        <div class="items-topbar-left">
          <button type="button" class="items-back-btn" @click="goBack">
            <i class="fa fa-arrow-left"></i>
            <span>{{ $t('items.form.backBtn') }}</span>
          </button>
          <h1 class="inv-page-title">{{ $t('items.editTitle') }}</h1>
          <p class="inv-page-hint">{{ $t('items.form.editPageHint') }}</p>
        </div>
        <button class="inv-btn inv-btn-primary" type="button" @click="handleSave" :disabled="saving || loading">
          <i v-if="saving" class="fa fa-spinner fa-spin me-1"></i>
          {{ saving ? $t('items.form.savingBtn') : $t('items.form.editBtn') }}
        </button>
      </div>

      <p class="items-required-note">{{ $t('items.form.requiredNote') }}</p>

      <form @submit.prevent="handleSave" novalidate>

        <!-- ═══════════════════════════════════════
             CARD 1 - Basic information
             ═══════════════════════════════════════ -->
        <section class="inv-card">
          <p class="inv-section-label">{{ $t('items.form.sectionBasic') }}</p>

          <div class="inv-field-group">
            <label class="inv-label">{{ $t('items.form.typeLabel') }}</label>
            <div class="items-type-toggle" role="radiogroup">
              <button
                type="button"
                class="items-type-btn"
                :class="{ 'items-type-btn--active': state.type == 2 }"
                role="radio"
                :aria-checked="state.type == 2"
                @click="state.type = 2"
              >
                <i class="fa fa-box"></i> {{ $t('items.types.product') }}
              </button>
              <button
                type="button"
                class="items-type-btn"
                :class="{ 'items-type-btn--active': state.type == 1 }"
                role="radio"
                :aria-checked="state.type == 1"
                @click="state.type = 1"
              >
                <i class="fa fa-briefcase"></i> {{ $t('items.types.service') }}
              </button>
            </div>
          </div>

          <div class="inv-field-group items-mt">
            <label class="inv-label inv-label-req">{{ $t('items.fields.name') }}</label>
            <input
              type="text"
              class="inv-input"
              :class="{ 'inv-input-err': v$.name.$errors.length }"
              v-model="state.name"
              :placeholder="$t('items.form.namePlaceholder')"
              @blur="v$.name.$touch"
            />
            <p v-if="v$.name.$errors.length" class="inv-err-msg">
              <i class="fa fa-exclamation-circle me-1"></i>{{ $t('items.form.nameRequired') }}
            </p>
          </div>

          <div class="inv-field-group items-mt">
            <label class="inv-label">{{ $t('items.fields.description') }}</label>
            <textarea
              class="inv-textarea"
              rows="4"
              v-model="state.description"
              :placeholder="$t('items.form.descriptionPlaceholder')"
            ></textarea>
          </div>
        </section>

        <!-- ═══════════════════════════════════════
             CARD 2 - Pricing
             ═══════════════════════════════════════ -->
        <section class="inv-card">
          <p class="inv-section-label">{{ $t('items.form.sectionPricing') }}</p>

          <div class="inv-two-col">
            <div class="inv-field-group">
              <label class="inv-label">{{ $t('items.fields.salesPrice') }}</label>
              <input
                type="text"
                class="inv-input inv-num"
                v-model="state.sales_price"
                v-decimal="{ decimals: 2 }"
                inputmode="decimal"
                autocomplete="off"
                :placeholder="$t('items.form.pricePlaceholder')"
              />
            </div>

            <div class="inv-field-group">
              <label class="inv-label">{{ $t('items.fields.currency') }}</label>
              <input
                type="text"
                class="inv-input items-currency-input"
                v-model="state.currency"
                maxlength="3"
                :placeholder="$t('items.form.currencyPlaceholder')"
              />
            </div>
          </div>

          <div :class="state.type == 2 ? 'inv-two-col items-mt' : 'inv-field-group items-mt'">
            <div class="inv-field-group">
              <label class="inv-label">{{ $t('items.fields.tax') }}</label>
              <select class="inv-select" v-model="state.vta">
                <option value="0">0%</option>
                <option value="4">4%</option>
                <option value="10">10%</option>
                <option value="21">21%</option>
              </select>
            </div>

            <div class="inv-field-group" v-if="state.type == 2">
              <label class="inv-label">{{ $t('items.fields.purchasePrice') }}</label>
              <input
                type="text"
                class="inv-input inv-num"
                v-model="state.purchase_price"
                v-decimal="{ decimals: 2 }"
                inputmode="decimal"
                :placeholder="$t('items.form.purchasePricePlaceholder')"
              />
            </div>
          </div>

          <div class="inv-field-group items-mt" v-if="state.type == 2">
            <label class="inv-label">{{ $t('items.fields.unit') }}</label>
            <select class="inv-select" v-model="state.unite">
              <option value="pc">{{ $t('units.piece') }}</option>
              <option value="kg">{{ $t('units.kilogram') }}</option>
            </select>
          </div>
        </section>

        <!-- ═══════════════════════════════════════
             CARD 3 - Additional information
             ═══════════════════════════════════════ -->
        <section class="inv-card">
          <p class="inv-section-label">{{ $t('items.form.sectionAdditional') }}</p>

          <div :class="state.type == 2 ? 'inv-two-col' : ''">
            <div class="inv-field-group" v-if="state.type == 2">
              <label class="inv-label">{{ $t('items.fields.reference') }}</label>
              <input
                type="text"
                class="inv-input"
                v-model="state.reference"
                :placeholder="$t('items.form.referencePlaceholder')"
              />
              <p class="items-field-hint">{{ $t('items.form.referenceHint') }}</p>
            </div>

            <div class="inv-field-group">
              <label class="inv-label inv-label-req">{{ $t('items.fields.family') }}</label>
              <VueSelect
                id="item-family"
                v-model="state.family_id"
                :options="families"
                label="name"
                :reduce="(option) => option.id"
                :placeholder="$t('items.selectFamilyPlaceholder')"
                @update:modelValue="() => v$.family_id.$touch()"
                :class="{ 'inv-select-err': v$.family_id.$error }"
              ></VueSelect>
              <p v-if="v$.family_id.$error" class="inv-err-msg">
                <i class="fa fa-exclamation-circle me-1"></i>{{ $t('items.form.categoryRequired') }}
              </p>
              <small>
                <a href="" data-bs-toggle="modal" data-bs-target="#modal-block-vcenter">
                  {{ $t("items.createFamilyLink") }}
                </a>
              </small>
            </div>
          </div>

          <div class="items-status-row items-mt">
            <label class="inv-label mb-0">{{ $t('items.fields.status') }}</label>
            <div class="items-switch">
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" id="item-active" v-model="state.active">
              </div>
              <label for="item-active" class="items-switch-label">
                {{ state.active ? $t('items.form.active') : $t('items.form.inactive') }}
              </label>
            </div>
            <p class="items-field-hint items-field-hint--full">{{ $t('items.form.activeHint') }}</p>
          </div>
        </section>

      </form>

      <!-- ── STICKY FOOTER ── -->
      <div class="inv-sticky-footer">
        <div class="inv-sticky-inner">
          <span class="inv-footer-hint">
            <i class="fa fa-keyboard me-1"></i>
            {{ $t('items.form.requiredNote') }}
          </span>
          <button
            class="inv-btn inv-btn-primary"
            type="button"
            @click="handleSave"
            :disabled="saving || loading"
          >
            <i v-if="saving" class="fa fa-spinner fa-spin me-1"></i>
            {{ saving ? $t('items.form.savingBtn') : $t('items.form.editBtn') }}
          </button>
        </div>
      </div>

    </div><!-- /.inv-create -->

    <div
      class="modal"
      id="modal-block-vcenter"
      tabindex="-1"
      role="dialog"
      aria-labelledby="modal-block-vcenter"
      aria-hidden="true"
      ref="modal_family"
    >
      <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
          <BaseBlock :title="$t('items.createFamilyTitle')" transparent class="mb-0">
            <template #options>
              <button
                type="button"
                class="btn-block-option"
                data-bs-dismiss="modal"
                aria-label="Close"
              >
                <i class="fa fa-fw fa-times"></i>
              </button>
            </template>

            <template #content>
              <form @submit.prevent="addFamily">
                <div class="block-content fs-sm">
                  <div class="col-lg-9 offset-lg-3">
                    <div class="row mb-2">
                      <label class="col-sm-3 col-form-label" for="family-name"
                        >{{ $t("items.familyNameLabel") }}<span class="text-danger">*</span></label>
                      <div class="col-sm-9">
                        <input
                          type="text"
                          class="form-control"
                          :class="{ 'is-invalid': v$_fm.name.$errors.length }"
                          v-model="family.name"
                          id="family-name"
                          @blur="v$_fm.name.$touch"
                        >
                      </div>
                    </div>
                  </div>
                </div>
                <div class="block-content block-content-full text-end bg-body">
                  <button
                    type="button"
                    class="btn btn-sm btn-alt-secondary me-1"
                    data-bs-dismiss="modal"
                  >
                    {{ $t("common.close") }}
                  </button>
                  <button type="submit" class="btn btn-sm btn-primary">
                    {{ $t("common.save") }}
                  </button>
                </div>
              </form>
            </template>
          </BaseBlock>
        </div>
      </div>
    </div>

  </div>
  <!-- END Page Content -->
</template>

<script setup>
import { reactive, ref, computed, onMounted } from "vue";
import axios from "axios";
import { Modal } from "bootstrap";
import VueSelect from "vue-select";
import { createToaster } from "@meforma/vue-toaster";
import { useRouter, useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import useVuelidate from "@vuelidate/core";
import { required } from "@vuelidate/validators";

const { t } = useI18n();
const toaster = createToaster();
const router = useRouter();
const route = useRoute();

const saving = ref(false);
const loading = ref(true);

function goBack() {
  router.push({ name: "backend-items" });
}

const state = reactive({
  uuid: null,
  type: 2,
  unite: "pc",
  name: null,
  sales_price: null,
  purchase_price: null,
  reference: null,
  vta: 0,
  currency: "EUR",
  active: true,
  description: null,
  family_id: null,
});

const family = reactive({ name: null });
const modal_family = ref();
let familyModal = null;
const families = ref([]);

onMounted(async () => {
  const [itemRes, familiesRes] = await Promise.all([
    axios.get("/items/" + route.params.id + "/edit"),
    axios.get("/families"),
  ]);

  const item = itemRes.data.item;
  Object.assign(state, {
    uuid:           item.uuid,
    type:           Number(item.type),
    unite:          item.unite ?? "pc",
    name:           item.name,
    sales_price:    item.sales_price,
    purchase_price: item.purchase_price,
    reference:      item.reference,
    vta:            item.vta ?? 0,
    currency:       item.currency ?? "EUR",
    active:         item.active ?? true,
    description:    item.description,
    family_id:      item.family_id,
  });

  families.value = familiesRes.data.families;
  loading.value = false;

  if (modal_family.value) {
    familyModal = new Modal(modal_family.value);
  }
});

const rules = computed(() => ({
  name:      { required },
  family_id: { required },
}));

const rule_fm = computed(() => ({
  name: { required },
}));

const v$ = useVuelidate(rules, state);
const v$_fm = useVuelidate(rule_fm, family);

async function addFamily() {
  const result = await v$_fm.value.$validate();
  if (!result) return;

  axios.post("/families", family).then((res) => {
    families.value.push(res.data.family);
    family.name = "";
    if (familyModal) familyModal.hide();
  });
}

async function handleSave() {
  const result = await v$.value.$validate();
  if (!result) return;

  saving.value = true;

  const payload = {
    ...state,
    currency: state.currency ? state.currency.toUpperCase() : null,
  };

  axios
    .put("/items/" + state.uuid, payload)
    .then(() => {
      toaster.success(t("items.form.editSuccess"));
      router.push({ name: "backend-items" });
    })
    .catch((error) => {
      if (error.response?.status === 422) {
        toaster.error(error.response.data?.message ?? t("items.form.genericError"));
      } else {
        toaster.error(t("items.form.genericError"));
      }
    })
    .finally(() => {
      saving.value = false;
    });
}
</script>

<style scoped>
/* =========================================================
   items/edit.vue — design system identical to create.vue.
   Only the i18n keys and submit logic differ.
   ========================================================= */

.inv-create { padding: 0 0 120px; }

/* ── Top bar ── */
.inv-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 12px;
}

.items-topbar-left {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
}

.items-back-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: none;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 7px 13px;
  font-size: 13px;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
  transition: background 0.13s, border-color 0.13s;
  margin-bottom: 6px;
}
.items-back-btn:hover { background: #f9fafb; border-color: #d1d5db; }

.inv-page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 2px;
  line-height: 1.2;
}

.inv-page-hint {
  font-size: 0.8rem;
  color: #9ca3af;
  margin: 0;
}

/* ── Required-fields note ── */
.items-required-note {
  font-size: 0.78rem;
  color: #9ca3af;
  margin: 0 0 16px;
}

/* ── Cards ── */
.inv-card {
  background: #fff;
  border: 1.5px solid #e8eaed;
  border-top: 3px solid #E91E63;
  border-radius: 16px;
  padding: 24px 28px;
  margin-bottom: 16px;
}

.inv-section-label {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: #9ca3af;
  margin: 0 0 14px;
}

/* ── Two-column grid ── */
.inv-two-col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 32px;
}

.items-mt { margin-top: 20px; }

/* ── Form elements ── */
.inv-field-group {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.inv-label {
  display: block;
  font-size: 0.8rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 6px;
}

.inv-label-req::after {
  content: " *";
  color: #E91E63;
}

.inv-input {
  display: block;
  width: 100%;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  padding: 9px 12px;
  font-size: 0.875rem;
  color: #0f172a;
  background: #fff;
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
  line-height: 1.4;
}

.inv-input:focus {
  border-color: #E91E63;
  box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
}

.inv-input.inv-input-err { border-color: #ef4444; }

.items-currency-input { text-transform: uppercase; }

.inv-select {
  display: block;
  width: 100%;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  padding: 9px 12px;
  font-size: 0.875rem;
  color: #0f172a;
  background: #fff;
  outline: none;
  cursor: pointer;
}

.inv-select:focus {
  border-color: #E91E63;
  box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
}

.inv-textarea {
  display: block;
  width: 100%;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  padding: 10px 12px;
  font-size: 0.875rem;
  color: #0f172a;
  resize: vertical;
  outline: none;
  transition: border-color 0.15s;
}

.inv-textarea:focus {
  border-color: #E91E63;
  box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
}

.inv-err-msg {
  font-size: 0.75rem;
  color: #ef4444;
  margin: 5px 0 0;
}

.inv-num { text-align: right; }

/* ── Type toggle (Product / Service) ── */
.items-type-toggle {
  display: flex;
  gap: 10px;
}

.items-type-btn {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 16px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
  font-size: 0.9rem;
  font-weight: 600;
  color: #6b7280;
  cursor: pointer;
  transition: border-color 0.15s, color 0.15s, background 0.15s;
}

.items-type-btn:hover { border-color: #E91E63; color: #E91E63; }

.items-type-btn--active {
  border-color: #E91E63;
  background: rgba(233, 30, 99, 0.06);
  color: #E91E63;
}

/* ── Status switch ── */
.items-status-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px 14px;
}

.items-switch {
  display: flex;
  align-items: center;
  gap: 8px;
}

.items-switch-label {
  font-size: 0.85rem;
  font-weight: 600;
  color: #374151;
  margin: 0;
  cursor: pointer;
}

.items-field-hint {
  font-size: 0.78rem;
  color: #9ca3af;
  margin: 6px 0 0;
}

.items-field-hint--full { flex-basis: 100%; margin-top: 0; }

/* ── Buttons ── */
.inv-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 10px;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  border: 1px solid transparent;
  transition: background .15s ease-in-out, border-color .15s ease-in-out,
              box-shadow .15s ease-in-out, transform .15s ease-in-out;
  text-decoration: none;
  white-space: nowrap;
  line-height: 1;
}

.inv-btn-primary {
  background: #E91E63;
  color: #fff !important;
  border-color: #E91E63;
}
.inv-btn-primary:hover:not(:disabled) {
  background: #c2185b;
  border-color: #c2185b;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(233, 30, 99, 0.22);
}

.inv-btn-primary:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

/* ── Sticky footer ── */
.inv-sticky-footer {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 200;
  background: rgba(255, 255, 255, 0.96);
  backdrop-filter: blur(8px);
  border-top: 1px solid #e8eaed;
  padding: 14px 0;
}

.inv-sticky-inner {
  max-width: 1320px;
  margin: 0 auto;
  padding: 0 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.inv-footer-hint {
  font-size: 0.78rem;
  color: #9ca3af;
}

/* ── vue-select overrides ── */
:deep(.v-select) { font-size: 0.875rem; }

:deep(.vs__dropdown-toggle) {
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  padding: 4px 6px;
  transition: border-color 0.15s;
}

:deep(.vs__dropdown-toggle:focus-within) {
  border-color: #E91E63;
  box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
}

:deep(.inv-select-err .vs__dropdown-toggle) { border-color: #ef4444; }

:deep(.vs__search) { font-size: 0.875rem; color: #0f172a; }
:deep(.vs__selected) { font-size: 0.875rem; color: #0f172a; margin: 2px; }
:deep(.vs__placeholder) { font-size: 0.875rem; color: #9ca3af; }
:deep(.vs__actions) { padding-right: 4px; }

/* ── Responsive ── */
@media (max-width: 768px) {
  .inv-topbar       { flex-direction: column; align-items: flex-start; }
  .inv-two-col      { grid-template-columns: 1fr; gap: 20px; }
  .inv-card         { padding: 18px 16px; }
  .inv-footer-hint  { display: none; }
  .inv-sticky-inner { justify-content: flex-end; }
  .items-type-toggle{ flex-direction: column; }
}
</style>
