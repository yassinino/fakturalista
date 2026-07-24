<template>
  <div class="content">
    <div class="inv-create">

      <!-- ── TOP BAR ── -->
      <div class="inv-topbar">
        <div>
          <h1 class="inv-page-title">{{ $t('quotes.newTitle') }}</h1>
          <p class="inv-page-hint">{{ $t('quotes.form.pageHint') }}</p>
        </div>
        <button class="inv-btn inv-btn-primary" type="button" @click="handleSave" :disabled="saving">
          <i v-if="saving" class="fa fa-spinner fa-spin me-1"></i>
          {{ $t('quotes.form.createBtn') }}
        </button>
      </div>

      <form @submit.prevent="handleSave" novalidate>

        <!-- ═══════════════════════════════════════
             CARD 1 - Client + Dates
             ═══════════════════════════════════════ -->
        <section class="inv-card">
          <div class="inv-two-col">

            <!-- Client -->
            <div class="inv-field-group">
              <label class="inv-label inv-label-req">{{ $t('documents.customer') }}</label>
              <VueSelect
                v-model="state.customer_id"
                :options="customers"
                label="name"
                :reduce="o => o.uuid"
                @option:selected="onClientSelect"
                @blur="v$.customer_id.$touch"
                :placeholder="$t('quotes.form.clientPlaceholder')"
                :class="{ 'inv-select-err': v$.customer_id.$errors.length }"
              />
              <p v-if="v$.customer_id.$errors.length" class="inv-err-msg">
                <i class="fa fa-exclamation-circle me-1"></i>{{ $t('documents.selectCustomer') }}
              </p>
              <div v-if="state.address" class="inv-addr-preview">
                <i class="fa fa-map-marker-alt"></i> {{ state.address }}
              </div>
            </div>

            <!-- Dates + Advanced -->
            <div class="inv-field-group">
              <div class="inv-date-row">
                <div>
                  <label class="inv-label inv-label-req">{{ $t('quotes.form.issueDateLabel') }}</label>
                  <FlatPickr
                    v-model="state.date"
                    class="inv-input"
                    :config="fpConfig"
                  />
                </div>
                <div>
                  <label class="inv-label">{{ $t('quotes.form.validityDateLabel') }}</label>
                  <FlatPickr
                    v-model="state.expiration_date"
                    class="inv-input"
                    :config="fpConfig"
                  />
                </div>
              </div>

              <button
                type="button"
                class="inv-more-btn"
                @click="showAdvanced = !showAdvanced"
              >
                <i class="fa" :class="showAdvanced ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                {{ $t('quotes.form.moreOptions') }}
              </button>

              <Transition name="inv-slide">
                <div v-if="showAdvanced" class="inv-adv-panel">
                  <label class="inv-label">{{ $t('documents.status') }}</label>
                  <select class="inv-select" v-model="state.status">
                    <option value="">{{ $t('quotes.form.statusNoState') }}</option>
                    <option value="1">{{ $t('quotes.form.statusAccepted') }}</option>
                    <option value="0">{{ $t('quotes.form.statusPending') }}</option>
                  </select>
                </div>
              </Transition>
            </div>

          </div>
        </section>

        <!-- ═══════════════════════════════════════
             CARD 2 - Line Items
             ═══════════════════════════════════════ -->
        <section class="inv-card">
          <p class="inv-section-label">{{ $t('quotes.form.linesLabel') }}</p>
          <div class="inv-table-scroll">
            <table class="inv-table">
              <thead>
                <tr>
                  <th class="inv-th inv-col-desc">{{ $t('quotes.form.colDesc') }}</th>
                  <th class="inv-th inv-col-qty">{{ $t('quotes.form.colQty') }}</th>
                  <th class="inv-th inv-col-unit inv-hide-mobile">{{ $t('quotes.form.colUnit') }}</th>
                  <th class="inv-th inv-col-price">{{ $t('quotes.form.colPrice') }}</th>
                  <th class="inv-th inv-col-vta">{{ $t('quotes.form.colVat') }}</th>
                  <th class="inv-th inv-col-total">{{ $t('documents.total') }}</th>
                  <th class="inv-th inv-col-del"></th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(cart, index) in state.carts"
                  :key="index"
                  class="inv-line"
                >
                  <td class="inv-td inv-td-desc">
                    <VueSelect
                      v-model="cart.item_id"
                      :options="items"
                      label="name"
                      :reduce="o => o.id"
                      @option:selected="v => selectProduct(index, v)"
                      :placeholder="$t('quotes.form.productPlaceholder')"
                      class="inv-line-vs"
                    />
                    <input
                      class="inv-input inv-desc-sub"
                      v-model="cart.description"
                      :placeholder="$t('quotes.form.descSubPlaceholder')"
                      @keydown.enter.prevent="addNewItem"
                    />
                  </td>

                  <td class="inv-td">
                    <input
                      class="inv-input inv-num"
                      v-model="cart.qty"
                      inputmode="decimal"
                      v-decimal="{ decimals: 2 }"
                      @focus="$event.target.select()"
                    />
                  </td>

                  <td class="inv-td inv-hide-mobile">
                    <select class="inv-select" v-model="cart.unite">
                      <option value="pc">{{ $t('units.piece') }}</option>
                      <option value="kg">{{ $t('units.kilogram') }}</option>
                    </select>
                  </td>

                  <td class="inv-td">
                    <input
                      class="inv-input inv-num"
                      v-model="cart.price"
                      inputmode="decimal"
                      v-decimal="{ decimals: 2 }"
                      @focus="$event.target.select()"
                      @keydown.enter.prevent="addNewItem"
                    />
                  </td>

                  <td class="inv-td">
                    <select class="inv-select" v-model="cart.vta">
                      <option value="0">0%</option>
                      <option value="4">4%</option>
                      <option value="10">10%</option>
                      <option value="21">21%</option>
                    </select>
                  </td>

                  <td class="inv-td inv-td-rowtotal">
                    {{ $toComma(totalRow[index]) }}&thinsp;€
                  </td>

                  <td class="inv-td">
                    <button
                      type="button"
                      class="inv-del-btn"
                      @click="removeCart(cart)"
                      :disabled="state.carts.length === 1"
                      :title="$t('quotes.form.removeLineTitle')"
                    >
                      <i class="fa fa-times"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <button type="button" class="inv-add-line" @click="addNewItem">
            <i class="fa fa-plus"></i> {{ $t('quotes.form.addLine') }}
          </button>
        </section>

        <!-- ═══════════════════════════════════════
             CARD 3 - Note + Totals
             ═══════════════════════════════════════ -->
        <section class="inv-card inv-bottom-card">

          <div class="inv-note-col">
            <label class="inv-label">{{ $t('documents.note') }}</label>
            <textarea
              class="inv-textarea"
              v-model="state.note"
              rows="4"
              :placeholder="$t('quotes.form.notesPlaceholder')"
            ></textarea>
          </div>

          <div class="inv-totals-col">
            <div class="inv-tot-row">
              <span class="inv-tot-label">{{ $t('quotes.form.subtotal') }}</span>
              <span class="inv-tot-val">{{ $toComma(subTotal) }}&thinsp;€</span>
            </div>
            <template v-if="vtaTotal4 > 0">
              <div class="inv-tot-row inv-tot-tax">
                <span class="inv-tot-label">{{ $t('documents.taxLabel', { rate: 4 }) }}</span>
                <span class="inv-tot-val">{{ $toComma(vtaTotal4) }}&thinsp;€</span>
              </div>
            </template>
            <template v-if="vtaTotal10 > 0">
              <div class="inv-tot-row inv-tot-tax">
                <span class="inv-tot-label">{{ $t('documents.taxLabel', { rate: 10 }) }}</span>
                <span class="inv-tot-val">{{ $toComma(vtaTotal10) }}&thinsp;€</span>
              </div>
            </template>
            <template v-if="vtaTotal21 > 0">
              <div class="inv-tot-row inv-tot-tax">
                <span class="inv-tot-label">{{ $t('documents.taxLabel', { rate: 21 }) }}</span>
                <span class="inv-tot-val">{{ $toComma(vtaTotal21) }}&thinsp;€</span>
              </div>
            </template>
            <div class="inv-tot-divider"></div>
            <div class="inv-tot-row inv-tot-grand">
              <span class="inv-tot-grand-label">{{ $t('documents.total') }}</span>
              <span class="inv-tot-grand-val">{{ $toComma(total) }}&thinsp;€</span>
            </div>
          </div>

        </section>

      </form>

      <!-- ── STICKY FOOTER ── -->
      <div class="inv-sticky-footer">
        <div class="inv-sticky-inner">
          <span class="inv-footer-hint">
            <i class="fa fa-keyboard me-1"></i>
            {{ $t('quotes.form.footerHint') }}
          </span>
          <button
            class="inv-btn inv-btn-primary"
            type="button"
            @click="handleSave"
            :disabled="saving"
          >
            <i v-if="saving" class="fa fa-spinner fa-spin me-1"></i>
            {{ $t('quotes.form.createBtn') }}
          </button>
        </div>
      </div>

    </div><!-- /.inv-create -->
  </div><!-- /.content -->
</template>

<script setup>
import { reactive, ref, computed, onMounted } from "vue";
import VueSelect from "vue-select";
import FlatPickr from "vue-flatpickr-component";
import axios from "axios";
import { useI18n } from "vue-i18n";
import useVuelidate from "@vuelidate/core";
import { required } from "@vuelidate/validators";

const { t } = useI18n();
const emit = defineEmits(["saveDocument"]);

const saving = ref(false);
const showAdvanced = ref(false);

const pad = (n) => String(n).padStart(2, "0");
const fmtDate = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

const today = new Date();
const validDate = new Date(today);
validDate.setDate(validDate.getDate() + 30);

const state = reactive({
  customer_id: "",
  address: "",
  discount_rate: 0,
  discount_amount: 0,
  date: fmtDate(today),
  status: "",
  expiration_date: fmtDate(validDate),
  note: "",
  carts: [
    { item_id: "", name: "", description: "", qty: 1, unite: "pc", price: 0, discount: 0, vta: 0, total: 0 },
  ],
});

const customers = ref([]);
const items = ref([]);

onMounted(async () => {
  const [custRes, itemsRes] = await Promise.all([
    axios.get("/customers"),
    axios.get("/items"),
  ]);
  customers.value = custRes.data.customers;
  items.value = itemsRes.data.items;
});

const fpConfig = { dateFormat: "Y-m-d", allowInput: true };

const onClientSelect = (value) => {
  state.address = value.address_billing;
};

const selectProduct = (index, value) => {
  state.carts[index] = {
    item_id:     value.id,
    description: value.description ?? "",
    qty:         1,
    unite:       value.unite || "pc",
    price:       value.sales_price,
    discount:    0,
    vta:         0,
    total:       value.sales_price,
  };
};

const addNewItem = () => {
  state.carts.push({ item_id: "", name: "", description: "", qty: 1, unite: "pc", price: 0, discount: 0, vta: 0, total: 0 });
};

const removeCart = (cart) => {
  if (state.carts.length === 1) return;
  if (confirm(t("documents.removeConfirm")))
    state.carts = state.carts.filter((c) => c !== cart);
};

const totalRow = computed(() =>
  state.carts.map((cart) => {
    const row = Number(cart.qty * cart.price) - (Number(cart.qty * cart.price) * cart.discount) / 100;
    cart.total = Number(row.toFixed(2));
    return cart.total;
  })
);

const subTotal = computed(() => {
  const sum = state.carts.reduce(
    (acc, cart) =>
      acc + Number(cart.qty * cart.price) - (Number(cart.qty * cart.price) * cart.discount) / 100,
    0
  );
  return Number(sum.toFixed(2));
});

const vtaTotal = computed(() => {
  const vta = state.carts.reduce((acc, cart) => acc + (cart.vta * cart.total) / 100, 0);
  return vta - (vta * state.discount_rate) / 100;
});

const calcVta = (rate) => {
  const raw = state.carts.reduce((acc, cart) => {
    if (Number(cart.vta) === rate) {
      const row = Number(cart.qty * cart.price) - (Number(cart.qty * cart.price) * cart.discount) / 100;
      return acc + (row * rate) / 100;
    }
    return acc;
  }, 0);
  const final = raw - (raw * Number(state.discount_rate || 0)) / 100;
  return Number(final.toFixed(2));
};

const vtaTotal4  = computed(() => calcVta(4));
const vtaTotal10 = computed(() => calcVta(10));
const vtaTotal21 = computed(() => calcVta(21));

const discountTotal = computed(() => (subTotal.value * state.discount_rate) / 100);

const total = computed(() =>
  Number((subTotal.value + vtaTotal.value - discountTotal.value).toFixed(2))
);

const rules = computed(() => ({
  customer_id:     { required },
  date:            { required },
  expiration_date: { required },
}));
const v$ = useVuelidate(rules, state);

const handleSave = async () => {
  const valid = await v$.value.$validate();
  if (!valid) return;

  saving.value = true;

  state.total           = total.value;
  state.sub_total       = subTotal.value;
  state.vta             = vtaTotal.value;
  state.vta4            = vtaTotal4.value;
  state.vta10           = vtaTotal10.value;
  state.vta21           = vtaTotal21.value;
  state.discount_amount = discountTotal.value;

  emit("saveDocument", state);
  saving.value = false;
};
</script>

<style scoped>
/* =========================================================
   CreateQuoteForm - ALL styles scoped to this component
   Uses inv-* prefix. Mirrors CreateInvoiceForm.vue.
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

/* ── Address preview ── */
.inv-addr-preview {
  margin-top: 8px;
  font-size: 0.78rem;
  color: #6b7280;
  background: #f8f9fc;
  border-radius: 8px;
  padding: 8px 10px;
  line-height: 1.5;
}

.inv-addr-preview i {
  color: #E91E63;
  margin-right: 4px;
}

/* ── Dates ── */
.inv-date-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 14px;
}

/* ── More options toggle ── */
.inv-more-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: none;
  border: none;
  font-size: 0.8rem;
  color: #6b7280;
  cursor: pointer;
  padding: 4px 0;
  margin-top: 4px;
  transition: color 0.15s;
}

.inv-more-btn:hover { color: #E91E63; }

.inv-adv-panel {
  margin-top: 12px;
  padding: 14px;
  background: #f8f9fc;
  border-radius: 10px;
}

/* ── Slide transition ── */
.inv-slide-enter-active,
.inv-slide-leave-active {
  transition: max-height 0.25s ease, opacity 0.2s ease;
  overflow: hidden;
  max-height: 200px;
}
.inv-slide-enter-from,
.inv-slide-leave-to {
  max-height: 0;
  opacity: 0;
}

/* ── Line items table ── */
.inv-table-scroll { overflow-x: auto; margin: 0 -4px; }

.inv-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 640px;
}

.inv-th {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #9ca3af;
  padding: 0 8px 10px;
  border-bottom: 2px solid #f1f4f8;
  white-space: nowrap;
}

.inv-col-desc  { min-width: 220px; }
.inv-col-qty   { width: 80px; }
.inv-col-unit  { width: 90px; }
.inv-col-price { width: 120px; }
.inv-col-vta   { width: 90px; }
.inv-col-total { width: 120px; text-align: right; }
.inv-col-del   { width: 44px; }

.inv-td {
  padding: 8px;
  vertical-align: top;
  border-bottom: 1px solid #f8fafc;
}

.inv-td-desc { padding-left: 0; }

.inv-td-rowtotal {
  text-align: right;
  font-weight: 600;
  color: #0f172a;
  font-size: 0.9rem;
  padding-top: 14px;
  white-space: nowrap;
}

.inv-line:last-child .inv-td { border-bottom: none; }

.inv-desc-sub {
  margin-top: 6px;
  font-size: 0.8rem;
  padding: 6px 10px;
  border-color: #f0f0f0;
  color: #6b7280;
}

.inv-num {
  text-align: right;
  padding-right: 10px;
}

.inv-add-line {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 14px;
  padding: 8px 16px;
  background: transparent;
  border: 1.5px dashed #d1d5db;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition: border-color 0.15s, color 0.15s;
}

.inv-add-line:hover {
  border-color: #E91E63;
  color: #E91E63;
}

.inv-del-btn {
  width: 30px;
  height: 30px;
  border-radius: 6px;
  border: 1.5px solid #fca5a5;
  color: #ef4444;
  background: transparent;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  transition: background 0.15s;
}

.inv-del-btn:hover:not(:disabled) { background: #fef2f2; }
.inv-del-btn:disabled { opacity: 0.25; cursor: default; }

/* ── Bottom card ── */
.inv-bottom-card {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 40px;
  align-items: start;
}

/* ── Totals ── */
.inv-totals-col { padding-top: 4px; }

.inv-tot-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 7px 0;
}

.inv-tot-label { font-size: 0.875rem; color: #6b7280; }
.inv-tot-val   { font-size: 0.9rem; font-weight: 600; color: #0f172a; }

.inv-tot-tax .inv-tot-label { font-size: 0.82rem; padding-left: 8px; }

.inv-tot-divider { border-top: 2px solid #0f172a; margin: 10px 0; }

.inv-tot-grand       { padding: 4px 0; }
.inv-tot-grand-label { font-size: 1rem; font-weight: 700; color: #0f172a; }
.inv-tot-grand-val   { font-size: 1.5rem; font-weight: 800; color: #E91E63; }

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
:deep(.v-select)             { font-size: 0.875rem; }

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

:deep(.vs__search)      { font-size: 0.875rem; color: #0f172a; }
:deep(.vs__selected)    { font-size: 0.875rem; color: #0f172a; margin: 2px; }
:deep(.vs__placeholder) { font-size: 0.875rem; color: #9ca3af; }
:deep(.vs__actions)     { padding-right: 4px; }

:deep(.inv-line-vs .vs__dropdown-toggle) {
  border-color: transparent;
  background: #f8f9fc;
}

:deep(.inv-line-vs .vs__dropdown-toggle:focus-within) {
  border-color: #E91E63;
  background: #fff;
}

/* ── Responsive ── */
@media (max-width: 768px) {
  .inv-topbar      { flex-direction: column; align-items: flex-start; }
  .inv-two-col     { grid-template-columns: 1fr; gap: 20px; }
  .inv-date-row    { grid-template-columns: 1fr; gap: 12px; }
  .inv-bottom-card { grid-template-columns: 1fr; gap: 24px; }
  .inv-card        { padding: 18px 16px; }
  .inv-hide-mobile { display: none; }
  .inv-footer-hint { display: none; }
  .inv-sticky-inner { justify-content: flex-end; }
}
</style>
