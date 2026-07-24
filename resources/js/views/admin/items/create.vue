<template>
  <div class="content">
    <div class="itc">

      <!-- ── PLAN-LIMIT BANNER ── -->
      <Transition name="itc-fade">
        <div v-if="planLimitError" class="itc-banner">
          <i class="fa fa-triangle-exclamation itc-banner__icon"></i>
          <div class="itc-banner__body">
            <strong>{{ $t('items.form.planLimitTitle') }}</strong>
            <span>{{ $t('items.form.planLimitBody', { limit: planLimitError.limit, plan: planLimitError.plan_name }) }}</span>
          </div>
          <router-link :to="{ name: 'backend-subscription' }" class="itc-btn itc-btn--primary itc-btn--sm">
            {{ $t('items.form.planLimitCta') }}
          </router-link>
        </div>
      </Transition>

      <!-- ── PAGE HEADER ── -->
      <div class="itc-header">
        <div class="itc-header__left">
          <button type="button" class="itc-back-btn" @click="goBack">
            <i class="fa fa-arrow-left"></i>
            {{ $t('items.form.backBtn') }}
          </button>
          <div class="itc-header__title-row">
            <div class="itc-header__icon">
              <Transition name="itc-icon-swap" mode="out-in">
                <i :key="state.type" :class="state.type === 2 ? 'fa fa-box' : 'fa fa-briefcase'"></i>
              </Transition>
            </div>
            <div>
              <h1 class="itc-header__title">{{ $t('items.form.pageTitle') }}</h1>
              <p class="itc-header__sub">{{ $t('items.form.pageHint') }}</p>
            </div>
          </div>
        </div>
        <div class="itc-header__actions">
          <button type="button" class="itc-btn itc-btn--ghost" @click="goBack">
            {{ $t('common.cancel') }}
          </button>
          <button type="button" class="itc-btn itc-btn--primary" @click="handleSave" :disabled="saving">
            <i :class="saving ? 'fa fa-spinner fa-spin' : 'fa fa-check'"></i>
            {{ saving ? $t('items.form.savingBtn') : $t('items.form.createBtn') }}
          </button>
        </div>
      </div>

      <!-- ── BODY LAYOUT ── -->
      <div class="itc-layout">

        <!-- ── FORM COLUMN ── -->
        <div class="itc-col-form">
          <form @submit.prevent="handleSave" novalidate>

            <!-- ═══ CARD 1: Basic Information ═══ -->
            <section class="itc-card">
              <div class="itc-card__head">
                <span class="itc-card__icon"><i class="fa fa-info-circle"></i></span>
                <span class="itc-card__label">{{ $t('items.form.sectionBasic') }}</span>
              </div>

              <!-- Type selector -->
              <div class="itc-field">
                <label class="itc-label">{{ $t('items.form.typeLabel') }}</label>
                <div class="itc-type-grid" role="radiogroup">
                  <button
                    type="button"
                    class="itc-type-card"
                    :class="{ 'itc-type-card--on': state.type === 2 }"
                    role="radio"
                    :aria-checked="String(state.type === 2)"
                    @click="state.type = 2"
                  >
                    <span class="itc-type-card__check"><i class="fa fa-check"></i></span>
                    <span class="itc-type-card__icon"><i class="fa fa-box"></i></span>
                    <span class="itc-type-card__name">{{ $t('items.types.product') }}</span>
                    <span class="itc-type-card__desc">{{ $t('items.form.typeProductDesc') }}</span>
                  </button>
                  <button
                    type="button"
                    class="itc-type-card"
                    :class="{ 'itc-type-card--on': state.type === 1 }"
                    role="radio"
                    :aria-checked="String(state.type === 1)"
                    @click="state.type = 1"
                  >
                    <span class="itc-type-card__check"><i class="fa fa-check"></i></span>
                    <span class="itc-type-card__icon"><i class="fa fa-briefcase"></i></span>
                    <span class="itc-type-card__name">{{ $t('items.types.service') }}</span>
                    <span class="itc-type-card__desc">{{ $t('items.form.typeServiceDesc') }}</span>
                  </button>
                </div>
              </div>

              <!-- Name -->
              <div class="itc-field itc-field--mt">
                <label class="itc-label itc-label--req" for="itc-name">{{ $t('items.fields.name') }}</label>
                <input
                  id="itc-name"
                  type="text"
                  class="itc-input"
                  :class="{ 'itc-input--err': v$.name.$errors.length }"
                  v-model="state.name"
                  :placeholder="$t('items.form.namePlaceholder')"
                  @blur="v$.name.$touch"
                  maxlength="100"
                />
                <p v-if="v$.name.$errors.length" class="itc-err-msg">
                  <i class="fa fa-circle-exclamation"></i>
                  {{ $t('items.form.nameRequired') }}
                </p>
              </div>

              <!-- Description -->
              <div class="itc-field itc-field--mt">
                <label class="itc-label" for="itc-desc">{{ $t('items.fields.description') }}</label>
                <textarea
                  id="itc-desc"
                  class="itc-textarea"
                  rows="3"
                  v-model="state.description"
                  :placeholder="$t('items.form.descriptionPlaceholder')"
                ></textarea>
              </div>
            </section>

            <!-- ═══ CARD 2: Pricing ═══ -->
            <section class="itc-card">
              <div class="itc-card__head">
                <span class="itc-card__icon"><i class="fa fa-tag"></i></span>
                <span class="itc-card__label">{{ $t('items.form.sectionPricing') }}</span>
              </div>

              <div class="itc-pricing-grid">
                <!-- Sales price -->
                <div class="itc-field">
                  <label class="itc-label" for="itc-price">{{ $t('items.fields.salesPrice') }}</label>
                  <div class="itc-input-prefixed">
                    <span class="itc-prefix">{{ state.currency || 'EUR' }}</span>
                    <input
                      id="itc-price"
                      type="text"
                      class="itc-input itc-input--num itc-input--no-border"
                      v-model="state.sales_price"
                      v-decimal="{ decimals: 2 }"
                      inputmode="decimal"
                      autocomplete="off"
                      :placeholder="$t('items.form.pricePlaceholder')"
                    />
                  </div>
                </div>

                <!-- Currency -->
                <div class="itc-field">
                  <label class="itc-label" for="itc-currency">{{ $t('items.fields.currency') }}</label>
                  <input
                    id="itc-currency"
                    type="text"
                    class="itc-input itc-input--upper"
                    v-model="state.currency"
                    maxlength="3"
                    :placeholder="$t('items.form.currencyPlaceholder')"
                  />
                </div>

                <!-- Unit — products only -->
                <div v-if="state.type === 2" class="itc-field">
                  <label class="itc-label" for="itc-unit">{{ $t('items.fields.unit') }}</label>
                  <select id="itc-unit" class="itc-select" v-model="state.unite">
                    <option value="pc">{{ $t('units.piece') }}</option>
                    <option value="kg">{{ $t('units.kilogram') }}</option>
                  </select>
                </div>

                <!-- Purchase price — products only -->
                <div v-if="state.type === 2" class="itc-field">
                  <label class="itc-label" for="itc-cost">{{ $t('items.fields.purchasePrice') }}</label>
                  <div class="itc-input-prefixed">
                    <span class="itc-prefix">{{ state.currency || 'EUR' }}</span>
                    <input
                      id="itc-cost"
                      type="text"
                      class="itc-input itc-input--num itc-input--no-border"
                      v-model="state.purchase_price"
                      v-decimal="{ decimals: 2 }"
                      inputmode="decimal"
                      :placeholder="$t('items.form.purchasePricePlaceholder')"
                    />
                  </div>
                </div>
              </div>

              <!-- VAT chips -->
              <div class="itc-field itc-field--mt">
                <label class="itc-label">{{ $t('items.fields.tax') }}</label>
                <div class="itc-vat-row" role="radiogroup">
                  <label
                    v-for="rate in [0, 4, 10, 21]"
                    :key="rate"
                    class="itc-vat-chip"
                    :class="{ 'itc-vat-chip--on': Number(state.vta) === rate }"
                  >
                    <input
                      type="radio"
                      :value="rate"
                      v-model.number="state.vta"
                      class="itc-sr-only"
                    />
                    {{ rate }}%
                  </label>
                </div>
              </div>
            </section>

            <!-- ═══ CARD 3: Additional Information ═══ -->
            <section class="itc-card">
              <div class="itc-card__head">
                <span class="itc-card__icon"><i class="fa fa-sliders"></i></span>
                <span class="itc-card__label">{{ $t('items.form.sectionAdditional') }}</span>
              </div>

              <div :class="state.type === 2 ? 'itc-two-col' : ''">

                <!-- SKU / Reference — products only -->
                <div v-if="state.type === 2" class="itc-field">
                  <label class="itc-label" for="itc-sku">{{ $t('items.fields.reference') }}</label>
                  <input
                    id="itc-sku"
                    type="text"
                    class="itc-input"
                    v-model="state.reference"
                    :placeholder="$t('items.form.referencePlaceholder')"
                  />
                  <p class="itc-hint">{{ $t('items.form.referenceHint') }}</p>
                </div>

                <!-- Category -->
                <div class="itc-field">
                  <label class="itc-label itc-label--req" for="itc-family">{{ $t('items.fields.family') }}</label>
                  <VueSelect
                    id="itc-family"
                    v-model="state.family_id"
                    :options="families"
                    label="name"
                    :reduce="o => o.id"
                    :placeholder="$t('items.selectFamilyPlaceholder')"
                    @update:modelValue="v$.family_id.$touch()"
                    :class="{ 'itc-vs-err': v$.family_id.$error }"
                  />
                  <p v-if="v$.family_id.$error" class="itc-err-msg">
                    <i class="fa fa-circle-exclamation"></i>
                    {{ $t('items.form.categoryRequired') }}
                  </p>
                  <button type="button" class="itc-link-btn" @click="openFamilyModal">
                    <i class="fa fa-plus"></i>
                    {{ $t('items.createFamilyLink') }}
                  </button>
                </div>
              </div>

              <!-- Status toggle -->
              <div class="itc-status-row">
                <div class="itc-status-row__info">
                  <p class="itc-label">{{ $t('items.fields.status') }}</p>
                  <p class="itc-hint">{{ $t('items.form.activeHint') }}</p>
                </div>
                <label class="itc-toggle" :class="{ 'itc-toggle--on': state.active }">
                  <input type="checkbox" v-model="state.active" class="itc-sr-only" />
                  <span class="itc-toggle__track">
                    <span class="itc-toggle__thumb"></span>
                  </span>
                  <span class="itc-toggle__label">
                    <span
                      class="itc-dot"
                      :class="state.active ? 'itc-dot--on' : ''"
                    ></span>
                    {{ state.active ? $t('items.form.active') : $t('items.form.inactive') }}
                  </span>
                </label>
              </div>
            </section>

          </form>
        </div>

        <!-- ── PREVIEW COLUMN ── -->
        <aside class="itc-col-preview">
          <div class="itc-preview">
            <p class="itc-preview__label">{{ $t('items.form.previewLabel') }}</p>

            <div class="itc-preview__header">
              <span
                class="itc-preview__type-badge"
                :class="state.type === 2 ? 'itc-preview__type-badge--product' : 'itc-preview__type-badge--service'"
              >
                <Transition name="itc-icon-swap" mode="out-in">
                  <i :key="state.type" :class="state.type === 2 ? 'fa fa-box' : 'fa fa-briefcase'"></i>
                </Transition>
                <Transition name="itc-icon-swap" mode="out-in">
                  <span :key="state.type">
                    {{ state.type === 2 ? $t('items.types.product') : $t('items.types.service') }}
                  </span>
                </Transition>
              </span>
              <span class="itc-preview__status-dot" :class="{ 'itc-preview__status-dot--on': state.active }"></span>
            </div>

            <p class="itc-preview__name" :class="{ 'itc-preview__name--placeholder': !state.name }">
              {{ state.name || $t('items.form.namePlaceholder') }}
            </p>

            <p v-if="state.description" class="itc-preview__desc">{{ state.description }}</p>

            <hr class="itc-preview__divider" />

            <dl class="itc-preview__meta">
              <div class="itc-preview__row">
                <dt>{{ $t('items.fields.salesPrice') }}</dt>
                <dd class="itc-preview__price">
                  {{ state.sales_price ? Number(state.sales_price).toFixed(2) : '—' }}
                  {{ state.currency || 'EUR' }}
                </dd>
              </div>
              <div class="itc-preview__row">
                <dt>{{ $t('items.fields.tax') }}</dt>
                <dd>{{ state.vta }}%</dd>
              </div>
              <div v-if="state.type === 2" class="itc-preview__row">
                <dt>{{ $t('items.fields.unit') }}</dt>
                <dd>{{ state.unite === 'pc' ? $t('units.piece') : $t('units.kilogram') }}</dd>
              </div>
              <div class="itc-preview__row">
                <dt>{{ $t('items.fields.family') }}</dt>
                <dd>{{ selectedFamilyName || '—' }}</dd>
              </div>
              <div class="itc-preview__row">
                <dt>{{ $t('items.fields.status') }}</dt>
                <dd>
                  <span class="itc-preview__status-label" :class="state.active ? 'itc-preview__status-label--on' : ''">
                    <span class="itc-dot" :class="state.active ? 'itc-dot--on' : ''"></span>
                    {{ state.active ? $t('items.form.active') : $t('items.form.inactive') }}
                  </span>
                </dd>
              </div>
            </dl>
          </div>
        </aside>

      </div>
    </div>

    <!-- ── STICKY FOOTER ── -->
    <div class="itc-footer">
      <div class="itc-footer__inner">
        <p class="itc-footer__note">
          <i class="fa fa-asterisk"></i>
          {{ $t('items.form.requiredNote') }}
        </p>
        <div class="itc-footer__btns">
          <button type="button" class="itc-btn itc-btn--ghost" @click="goBack">
            {{ $t('common.cancel') }}
          </button>
          <button type="button" class="itc-btn itc-btn--primary" @click="handleSave" :disabled="saving">
            <i :class="saving ? 'fa fa-spinner fa-spin' : 'fa fa-check'"></i>
            {{ saving ? $t('items.form.savingBtn') : $t('items.form.createBtn') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── CREATE FAMILY MODAL ── -->
    <div
      class="modal"
      id="modal-block-vcenter"
      tabindex="-1"
      role="dialog"
      aria-labelledby="modal-block-vcenter"
      aria-hidden="true"
      ref="modal_family"
    >
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <BaseBlock :title="$t('items.createFamilyTitle')" transparent class="mb-0">
            <template #options>
              <button type="button" class="btn-block-option" data-bs-dismiss="modal" aria-label="Close">
                <i class="fa fa-fw fa-times"></i>
              </button>
            </template>
            <template #content>
              <form @submit.prevent="addFamily">
                <div class="block-content fs-sm">
                  <div class="col-lg-9 offset-lg-3">
                    <div class="row mb-2">
                      <label class="col-sm-3 col-form-label" for="family-name">
                        {{ $t("items.familyNameLabel") }}<span class="text-danger">*</span>
                      </label>
                      <div class="col-sm-9">
                        <input
                          type="text"
                          class="form-control"
                          :class="{ 'is-invalid': v$_fm.name.$errors.length }"
                          v-model="family.name"
                          id="family-name"
                          @blur="v$_fm.name.$touch"
                        />
                      </div>
                    </div>
                  </div>
                </div>
                <div class="block-content block-content-full text-end bg-body">
                  <button type="button" class="btn btn-sm btn-alt-secondary me-1" data-bs-dismiss="modal">
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
</template>

<script setup>
import { reactive, ref, computed, onMounted } from "vue";
import axios from "axios";
import { Modal } from "bootstrap";
import VueSelect from "vue-select";
import { createToaster } from "@meforma/vue-toaster";
import { useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import useVuelidate from "@vuelidate/core";
import { required } from "@vuelidate/validators";

const { t } = useI18n();
const toaster = createToaster();
const router = useRouter();

const saving = ref(false);
const planLimitError = ref(null);

function goBack() {
  router.push({ name: "backend-items" });
}

const state = reactive({
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

const selectedFamilyName = computed(() =>
  families.value.find(f => f.id === state.family_id)?.name ?? null
);

onMounted(async () => {
  const response = await axios.get("/families");
  families.value = response.data.families;
  if (modal_family.value) {
    familyModal = new Modal(modal_family.value);
  }
});

function openFamilyModal() {
  if (familyModal) familyModal.show();
}

const rules = computed(() => ({
  name: { required },
  family_id: { required },
}));
const rule_fm = computed(() => ({ name: { required } }));

const v$ = useVuelidate(rules, state);
const v$_fm = useVuelidate(rule_fm, family);

async function addFamily() {
  const ok = await v$_fm.value.$validate();
  if (!ok) return;
  axios.post("/families", family).then(res => {
    families.value.push(res.data.family);
    family.name = "";
    if (familyModal) familyModal.hide();
  });
}

async function handleSave() {
  const ok = await v$.value.$validate();
  if (!ok) return;

  saving.value = true;
  planLimitError.value = null;

  axios
    .post("/items", { ...state, currency: state.currency?.toUpperCase() ?? null })
    .then(() => {
      toaster.success(t("items.form.createSuccess"));
      router.push({ name: "backend-items" });
    })
    .catch(err => {
      if (err.response?.status === 402 && err.response.data?.error === "plan_limit_reached") {
        planLimitError.value = err.response.data;
      } else if (err.response?.status === 422) {
        toaster.error(err.response.data?.message ?? t("items.form.genericError"));
      } else {
        toaster.error(t("items.form.genericError"));
      }
    })
    .finally(() => { saving.value = false; });
}
</script>

<style scoped>
/* ─────────────────────────────────────────────
   DESIGN TOKENS
───────────────────────────────────────────── */
.itc {
  --accent:        #E91E63;
  --accent-bg:     rgba(233, 30, 99, 0.07);
  --accent-ring:   rgba(233, 30, 99, 0.15);
  --accent-hover:  #c2185b;

  --bg:            #ffffff;
  --bg-subtle:     #f8fafc;
  --border:        #e2e8f0;

  --text:          #0f172a;
  --text-body:     #374151;
  --text-muted:    #64748b;
  --text-dim:      #94a3b8;

  --shadow-sm:     0 1px 2px rgba(0,0,0,0.06);
  --radius:        14px;
  --radius-sm:     8px;

  padding-bottom: 96px;
}

/* ── Dark mode ── */
:global(.dark-mode) .itc {
  --bg:            #1e2433;
  --bg-subtle:     #252d3d;
  --border:        rgba(255,255,255,0.09);
  --text:          #e2e8f0;
  --text-body:     #94a3b8;
  --text-muted:    #64748b;
  --text-dim:      #3d4762;
  --shadow-sm:     0 1px 2px rgba(0,0,0,0.3);
}

/* ─────────────────────────────────────────────
   PLAN LIMIT BANNER
───────────────────────────────────────────── */
.itc-banner {
  display: flex;
  align-items: center;
  gap: 14px;
  background: #fff7ed;
  border: 1.5px solid #fed7aa;
  border-radius: var(--radius-sm);
  padding: 14px 18px;
  margin-bottom: 20px;
}
:global(.dark-mode) .itc-banner {
  background: rgba(234,88,12,0.08);
  border-color: rgba(234,88,12,0.3);
}
.itc-banner__icon { font-size: 1.2rem; color: #ea580c; flex-shrink: 0; }
.itc-banner__body { flex: 1; min-width: 0; }
.itc-banner__body strong { display: block; font-size: 0.875rem; font-weight: 600; color: #9a3412; }
.itc-banner__body span  { font-size: 0.8rem; color: #c2410c; }
:global(.dark-mode) .itc-banner__body strong { color: #fb923c; }
:global(.dark-mode) .itc-banner__body span   { color: #fdba74; }

/* ─────────────────────────────────────────────
   PAGE HEADER
───────────────────────────────────────────── */
.itc-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}
.itc-header__left {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.itc-back-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: var(--bg);
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 6px 12px;
  font-size: 0.8rem;
  font-weight: 500;
  color: var(--text-muted);
  cursor: pointer;
  transition: border-color 0.15s, color 0.15s;
  align-self: flex-start;
}
.itc-back-btn:hover { border-color: var(--accent); color: var(--accent); }

.itc-header__title-row {
  display: flex;
  align-items: center;
  gap: 14px;
}
.itc-header__icon {
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--accent-bg);
  color: var(--accent);
  border-radius: 12px;
  font-size: 1.1rem;
  flex-shrink: 0;
}
.itc-header__title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text);
  margin: 0 0 2px;
  line-height: 1.2;
}
.itc-header__sub {
  font-size: 0.8rem;
  color: var(--text-dim);
  margin: 0;
}
.itc-header__actions { display: flex; align-items: center; gap: 8px; }

/* ─────────────────────────────────────────────
   BODY LAYOUT
───────────────────────────────────────────── */
.itc-layout {
  display: grid;
  grid-template-columns: 1fr 296px;
  gap: 20px;
  align-items: start;
}

/* ─────────────────────────────────────────────
   CARDS
───────────────────────────────────────────── */
.itc-card {
  background: var(--bg);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  padding: 20px 24px;
  margin-bottom: 14px;
  box-shadow: var(--shadow-sm);
}
.itc-card__head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 18px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--border);
}
.itc-card__icon {
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--accent-bg);
  color: var(--accent);
  border-radius: 7px;
  font-size: 0.8rem;
  flex-shrink: 0;
}
.itc-card__label {
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--text-muted);
}

/* ─────────────────────────────────────────────
   FIELDS
───────────────────────────────────────────── */
.itc-field { display: flex; flex-direction: column; }
.itc-field--mt { margin-top: 18px; }
.itc-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

.itc-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-body);
  margin: 0 0 6px;
}
.itc-label--req::after { content: " *"; color: var(--accent); }

.itc-input,
.itc-select,
.itc-textarea {
  display: block;
  width: 100%;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 9px 12px;
  font-size: 0.875rem;
  color: var(--text);
  background: var(--bg);
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
  line-height: 1.4;
}
.itc-input:focus,
.itc-select:focus,
.itc-textarea:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-ring);
}
.itc-input--err  { border-color: #ef4444 !important; }
.itc-input--num  { text-align: right; }
.itc-input--upper { text-transform: uppercase; }
.itc-select { cursor: pointer; }
.itc-textarea { resize: vertical; min-height: 80px; }

/* Prefixed input (currency + number) */
.itc-input-prefixed {
  display: flex;
  align-items: stretch;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  overflow: hidden;
  background: var(--bg);
  transition: border-color 0.15s, box-shadow 0.15s;
}
.itc-input-prefixed:focus-within {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-ring);
}
.itc-prefix {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 10px;
  min-width: 48px;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--text-muted);
  background: var(--bg-subtle);
  border-right: 1.5px solid var(--border);
  user-select: none;
  letter-spacing: 0.04em;
}
.itc-input--no-border {
  border: none;
  border-radius: 0;
  box-shadow: none !important;
  flex: 1;
  background: transparent;
}

.itc-err-msg {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 0.75rem;
  color: #ef4444;
  margin: 5px 0 0;
}
.itc-hint {
  font-size: 0.75rem;
  color: var(--text-dim);
  margin: 5px 0 0;
  line-height: 1.4;
}

/* ─────────────────────────────────────────────
   TYPE SELECTOR CARDS
───────────────────────────────────────────── */
.itc-type-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.itc-type-card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  padding: 16px;
  border: 2px solid var(--border);
  border-radius: var(--radius);
  background: var(--bg);
  cursor: pointer;
  text-align: left;
  transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
}
.itc-type-card:hover:not(.itc-type-card--on) {
  border-color: var(--accent);
  background: var(--accent-bg);
}
.itc-type-card--on {
  border-color: var(--accent);
  background: var(--accent-bg);
  box-shadow: 0 0 0 3px var(--accent-ring);
}
.itc-type-card__check {
  position: absolute;
  top: 10px;
  right: 10px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.6rem;
  background: var(--border);
  color: transparent;
  transition: background 0.15s, color 0.15s;
}
.itc-type-card--on .itc-type-card__check { background: var(--accent); color: #fff; }
.itc-type-card__icon {
  font-size: 1.2rem;
  color: var(--text-muted);
  transition: color 0.15s;
  margin-bottom: 4px;
}
.itc-type-card--on .itc-type-card__icon { color: var(--accent); }
.itc-type-card__name {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--text);
}
.itc-type-card__desc {
  font-size: 0.73rem;
  color: var(--text-dim);
  line-height: 1.3;
}

/* ─────────────────────────────────────────────
   PRICING GRID
───────────────────────────────────────────── */
.itc-pricing-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px 20px;
}

/* ─────────────────────────────────────────────
   VAT CHIPS
───────────────────────────────────────────── */
.itc-vat-row { display: flex; gap: 8px; flex-wrap: wrap; }
.itc-vat-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 7px 20px;
  border: 1.5px solid var(--border);
  border-radius: 100px;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-muted);
  cursor: pointer;
  transition: border-color 0.12s, color 0.12s, background 0.12s;
  user-select: none;
}
.itc-vat-chip:hover:not(.itc-vat-chip--on) { border-color: var(--accent); color: var(--accent); }
.itc-vat-chip--on { border-color: var(--accent); background: var(--accent); color: #fff; }

/* ─────────────────────────────────────────────
   STATUS TOGGLE
───────────────────────────────────────────── */
.itc-status-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid var(--border);
  flex-wrap: wrap;
}
.itc-status-row__info { flex: 1; min-width: 0; }
.itc-status-row__info .itc-label { margin-bottom: 2px; }

.itc-toggle {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  user-select: none;
  flex-shrink: 0;
}
.itc-toggle__track {
  position: relative;
  width: 40px;
  height: 22px;
  border-radius: 100px;
  background: var(--border);
  transition: background 0.2s;
  flex-shrink: 0;
}
.itc-toggle--on .itc-toggle__track { background: var(--accent); }
.itc-toggle__thumb {
  position: absolute;
  top: 3px;
  left: 3px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
  transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.itc-toggle--on .itc-toggle__thumb { transform: translateX(18px); }
.itc-toggle__label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-body);
}

/* Status / active dot */
.itc-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--text-dim);
  flex-shrink: 0;
  display: inline-block;
  transition: background 0.2s;
}
.itc-dot--on { background: #22c55e; }

/* Link-style button (create new category) */
.itc-link-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: none;
  border: none;
  padding: 0;
  margin-top: 6px;
  font-size: 0.78rem;
  font-weight: 500;
  color: var(--accent);
  cursor: pointer;
  transition: opacity 0.15s;
}
.itc-link-btn:hover { opacity: 0.7; }

/* Screenreader-only helper */
.itc-sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0,0,0,0);
  white-space: nowrap;
  border: 0;
}

/* ─────────────────────────────────────────────
   PREVIEW PANEL
───────────────────────────────────────────── */
.itc-col-preview {
  position: sticky;
  top: 72px;
}
.itc-preview {
  background: var(--bg);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  padding: 18px 20px;
  box-shadow: var(--shadow-sm);
}
.itc-preview__label {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--text-dim);
  margin: 0 0 12px;
}
.itc-preview__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}
.itc-preview__type-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.72rem;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 100px;
}
.itc-preview__type-badge--product { background: rgba(59,130,246,0.1); color: #3b82f6; }
.itc-preview__type-badge--service { background: rgba(139,92,246,0.1);  color: #8b5cf6; }
.itc-preview__status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--text-dim);
  transition: background 0.2s;
  flex-shrink: 0;
}
.itc-preview__status-dot--on { background: #22c55e; }
.itc-preview__name {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--text);
  margin: 0 0 4px;
  line-height: 1.3;
}
.itc-preview__name--placeholder { color: var(--text-dim); font-style: italic; font-weight: 400; }
.itc-preview__desc {
  font-size: 0.78rem;
  color: var(--text-muted);
  margin: 0 0 8px;
  line-height: 1.4;
}
.itc-preview__divider {
  border: none;
  border-top: 1px solid var(--border);
  margin: 14px 0;
}
.itc-preview__meta {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
}
.itc-preview__row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
}
.itc-preview__row dt {
  font-size: 0.75rem;
  color: var(--text-dim);
  font-weight: 400;
  flex-shrink: 0;
}
.itc-preview__row dd {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-body);
  margin: 0;
  text-align: right;
}
.itc-preview__price { color: var(--accent); }
.itc-preview__status-label {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-dim);
}
.itc-preview__status-label--on { color: #22c55e; }

/* ─────────────────────────────────────────────
   BUTTONS
───────────────────────────────────────────── */
.itc-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 9px 18px;
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  border: 1.5px solid transparent;
  transition: background 0.15s, border-color 0.15s, box-shadow 0.15s, transform 0.12s;
  white-space: nowrap;
  line-height: 1;
}
.itc-btn--primary {
  background: var(--accent);
  border-color: var(--accent);
  color: #fff;
}
.itc-btn--primary:hover:not(:disabled) {
  background: var(--accent-hover);
  border-color: var(--accent-hover);
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(233,30,99,0.25);
}
.itc-btn--primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
.itc-btn--ghost {
  background: transparent;
  border-color: var(--border);
  color: var(--text-body);
}
.itc-btn--ghost:hover { background: var(--bg-subtle); border-color: var(--text-muted); }
.itc-btn--sm { padding: 6px 12px; font-size: 0.8rem; }

/* ─────────────────────────────────────────────
   STICKY FOOTER
───────────────────────────────────────────── */
.itc-footer {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 200;
  background: rgba(255,255,255,0.93);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-top: 1px solid var(--border);
  padding: 12px 0;
}
:global(.dark-mode) .itc-footer { background: rgba(30,36,51,0.93); }
.itc-footer__inner {
  max-width: 1320px;
  margin: 0 auto;
  padding: 0 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.itc-footer__note {
  font-size: 0.78rem;
  color: var(--text-dim);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 5px;
}
.itc-footer__note i { font-size: 0.55rem; }
.itc-footer__btns { display: flex; align-items: center; gap: 8px; }

/* ─────────────────────────────────────────────
   VUE-SELECT OVERRIDES
───────────────────────────────────────────── */
:deep(.v-select) { font-size: 0.875rem; }
:deep(.vs__dropdown-toggle) {
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 3px 6px;
  background: var(--bg);
  transition: border-color 0.15s;
}
:deep(.vs__dropdown-toggle:focus-within) {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-ring);
}
:deep(.itc-vs-err .vs__dropdown-toggle) { border-color: #ef4444; }
:deep(.vs__search),
:deep(.vs__selected) { font-size: 0.875rem; color: var(--text); }
:deep(.vs__placeholder)    { font-size: 0.875rem; color: var(--text-dim); }
:deep(.vs__actions)        { padding-right: 4px; }
:deep(.vs__dropdown-menu) {
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  box-shadow: 0 4px 20px rgba(0,0,0,0.1);
  background: var(--bg);
}
:deep(.vs__dropdown-option) { font-size: 0.875rem; color: var(--text); }
:deep(.vs__dropdown-option--highlight) { background: var(--accent-bg); color: var(--accent); }

/* ─────────────────────────────────────────────
   TRANSITIONS
───────────────────────────────────────────── */
.itc-fade-enter-active,
.itc-fade-leave-active { transition: opacity 0.2s, transform 0.2s; }
.itc-fade-enter-from,
.itc-fade-leave-to { opacity: 0; transform: translateY(-8px); }

.itc-icon-swap-enter-active,
.itc-icon-swap-leave-active { transition: opacity 0.15s, transform 0.15s; }
.itc-icon-swap-enter-from { opacity: 0; transform: scale(0.7) rotate(-10deg); }
.itc-icon-swap-leave-to   { opacity: 0; transform: scale(0.7) rotate(10deg); }

/* ─────────────────────────────────────────────
   RESPONSIVE
───────────────────────────────────────────── */
@media (max-width: 1100px) {
  .itc-layout { grid-template-columns: 1fr; }
  .itc-col-preview { position: static; display: none; }
}

@media (max-width: 768px) {
  .itc-header            { flex-direction: column; align-items: flex-start; }
  .itc-header__actions   { width: 100%; justify-content: flex-end; }
  .itc-type-grid         { grid-template-columns: 1fr; }
  .itc-pricing-grid      { grid-template-columns: 1fr; }
  .itc-two-col           { grid-template-columns: 1fr; }
  .itc-card              { padding: 16px; }
  .itc-status-row        { flex-direction: column; align-items: flex-start; }
  .itc-footer__note      { display: none; }
  .itc-footer__btns      { width: 100%; }
  .itc-footer__btns .itc-btn { flex: 1; justify-content: center; }
}
</style>
