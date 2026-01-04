<template>
  <div class="content content-boxed">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h3 class="mb-1">Plantillas de factura</h3>
        <p class="text-muted mb-0">Personaliza apariencia</p>
      </div>
      <div class="d-flex align-items-center gap-2">
        <div v-if="saveMessage" class="text-success small">{{ saveMessage }}</div>
        <div v-if="saveError" class="text-danger small">{{ saveError }}</div>
        <button
          type="button"
          class="btn btn-primary"
          :disabled="saving"
          @click="saveTemplate"
        >
          <span v-if="saving" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
          Guardar
        </button>
      </div>
    </div>

    <div class="d-flex align-items-center gap-2 mb-3 border rounded px-3 py-2 bg-white p-4">
      <div class="d-inline-flex flex-wrap">
        <button
          v-for="tab in tabsMenu"
          :key="tab.key"
          type="button"
          class="btn border-0"
          :class="{
            'text-primary fw-semibold': activePanel === tab.key,
            'text-secondary': activePanel !== tab.key
          }"
          @click="activePanel = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-lg-8">
        <div class="border rounded bg-white p-4" style="min-height: 520px;">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div
              v-if="previewLogo"
              class="me-3"
              :style="logoWrapperStyle"
            >
              <img
                :src="previewLogo"
                alt="Logo"
                class="img-fluid"
                :style="{ maxWidth: '100%' }"
              />
            </div>
            <div class="flex-grow-1 text-end">
              <div :style="headingStyle">Factura Núm. INV-001</div>
              <div :style="mutedStyle">{{ currentDate }}</div>
            </div>
          </div>

          <div class="row mb-4 mt-4">
            <div :class="design.billing_address_right ? 'col-6 order-2' : 'col-6 order-1'">
              <div :style="mutedStyle">Dirección de facturación</div>
              <div :style="titleStyle">MISC CUSTOMER</div>
              <div v-if="design.show_customer_number" :style="mutedStyle">Cliente #{{ sample.customerNumber }}</div>
              <div :style="textStyle">ait melloul</div>
              <div :style="textStyle">agadir 68602</div>
              <div v-if="design.show_customer_phone" :style="textStyle">Tel: {{ sample.customerPhone }}</div>
            </div>
            <div
              v-if="design.show_shipping_address"
              :class="design.billing_address_right ? 'col-6 order-1' : 'col-6 order-2'"
            >
              <div :style="mutedStyle">Dirección de envío</div>
              <div :style="titleStyle">MISC CUSTOMER</div>
              <div :style="textStyle">ait melloul</div>
              <div :style="textStyle">agadir 68602</div>
            </div>
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-sm mb-0" :style="tableStyle">
              <thead>
                <tr :style="tableHeadStyle">
                  <th :style="thStyle">Nombre de artículo y descripción</th>
                  <th v-if="design.show_tax_column" :style="thStyle">IVA</th>
                  <th v-if="design.show_discount" :style="thStyle">Dto.</th>
                  <th :style="thStyle">Cantidad</th>
                  <th :style="thStyle">Precio unitario</th>
                  <th :style="thStyle">Importe</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td :style="tdStyle">
                    <strong :style="textStyle">PS-001 test</strong><br />
                    <span :style="mutedStyle">fdsafd</span>
                  </td>
                  <td v-if="design.show_tax_column" :style="tdStyle">20%</td>
                  <td v-if="design.show_discount" :style="tdStyle">5%</td>
                  <td :style="tdStyle">1</td>
                  <td :style="tdStyle">333,00 € /day</td>
                  <td :style="tdStyle">333,00 €</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="row mb-2">
            <div class="col-6"></div>
            <div class="col-6">
              <div
                v-if="design.show_subtotal"
                class="d-flex justify-content-between"
                :style="textStyle"
              >
                <span>Subtotal</span><span>333,00 €</span>
              </div>
              <div
                v-if="design.show_tax_breakdown"
                class="d-flex justify-content-between"
                :style="textStyle"
              >
                <span>IVA (333,00 € x 20%)</span><span>66,60 €</span>
              </div>
              <div
                class="d-flex justify-content-between mt-2"
                :style="textStyle"
              >
                <span>Total</span><span>399,60 €</span>
              </div>
              <div
                v-if="design.show_payment_terms"
                class="d-flex justify-content-between mt-2"
                :style="mutedStyle"
              >
                <span>Términos de pago</span><span>{{ sample.paymentTerms }}</span>
              </div>
            </div>
          </div>

          <div class="mt-4" v-if="design.show_payment_note && design.payment_note">
            <div :style="titleStyle">Información de Pago</div>
            <ul class="mb-0" :style="textStyle">
              <li>{{ design.payment_note }}</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="border rounded p-3" style="background: #f8f9fb;">
          <div v-if="activePanel === 'appearance'" class="bg-white border rounded p-3">
            <div class="mb-3">
              <label class="form-label mb-1">Color scheme</label>
              <input
                type="color"
                class="form-control form-control-color"
                v-model="design.primary"
                @input="applyColorScheme($event.target.value)"
              />
            </div>
            <div class="mb-3">
              <label class="form-label mb-1">Font</label>
              <select class="form-select" v-model="design.font_family" @change="syncDesign">
                <option value="Arial, sans-serif">Arial</option>
                <option value="Helvetica, Arial, sans-serif">Helvetica</option>
                <option value="Georgia, serif">Georgia</option>
                <option value="Times New Roman, serif">Times New Roman</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label mb-1">Font size</label>
              <select class="form-select" v-model="design.font_size" @change="syncDesign">
                <option value="small">Small</option>
                <option value="medium">Medium</option>
                <option value="large">Large</option>
              </select>
            </div>
            <div class="mb-1">
              <label class="form-label mb-1">Font color</label>
              <input type="color" class="form-control form-control-color" v-model="design.text" @input="syncDesign" />
            </div>
          </div>

          <div v-else-if="activePanel === 'header'" class="bg-white border rounded p-3">
            <div class="list-group list-group-flush">
              <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span>Payment terms</span>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" v-model="design.show_payment_terms" @change="syncDesign" />
                </div>
              </div>
              <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span>Customer number</span>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" v-model="design.show_customer_number" @change="syncDesign" />
                </div>
              </div>
              <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span>Customer phone no.</span>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" v-model="design.show_customer_phone" @change="syncDesign" />
                </div>
              </div>
              <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span>Shipping address</span>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" v-model="design.show_shipping_address" @change="syncDesign" />
                </div>
              </div>
              <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span>Billing address on right</span>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" v-model="design.billing_address_right" @change="syncDesign" />
                </div>
              </div>
              <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span>Logo placement</span>
                <div class="d-flex gap-2">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" value="above" v-model="design.logo_position" @change="syncDesign" id="logo-above-2" />
                    <label class="form-check-label" for="logo-above-2">Above</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" value="left" v-model="design.logo_position" @change="syncDesign" id="logo-left-2" />
                    <label class="form-check-label" for="logo-left-2">Left</label>
                  </div>
                </div>
              </div>
              <div class="list-group-item px-0">
                <label class="form-label mb-1">Logo Width</label>
                <input type="range" class="form-range" min="30" max="120" step="2" v-model.number="design.logo_width_mm" @input="syncDesign" />
                <div class="text-muted small">{{ design.logo_width_mm }} mm</div>
              </div>
            </div>
          </div>

          <div v-else-if="activePanel === 'invoice_table'" class="bg-white border rounded p-3">
            <div class="mb-3">
              <label class="form-label mb-1">Table header</label>
              <div class="d-flex gap-2">
                <input type="color" class="form-control form-control-color" v-model="design.table_header_bg" @input="syncDesign" />
                <input type="color" class="form-control form-control-color" v-model="design.table_header_text" @input="syncDesign" />
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label mb-1">Table border</label>
              <input type="color" class="form-control form-control-color" v-model="design.table_border" @input="syncDesign" />
            </div>
            <div class="list-group list-group-flush">
              <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span>Show discount column</span>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" v-model="design.show_discount" @change="syncDesign" />
                </div>
              </div>
              <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span>Show tax column</span>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" v-model="design.show_tax_column" @change="syncDesign" />
                </div>
              </div>
            </div>
          </div>

          <div v-else-if="activePanel === 'bottom'" class="bg-white border rounded p-3">
            <label class="form-label mb-1">Payment note</label>
            <textarea class="form-control mb-2" rows="3" v-model="design.payment_note" @input="syncDesign"></textarea>
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" v-model="design.show_payment_note" @change="syncDesign" id="show-payment-note-2" />
              <label class="form-check-label" for="show-payment-note-2">Show payment note</label>
            </div>
            <div class="list-group list-group-flush">
              <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span>Show subtotal</span>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" v-model="design.show_subtotal" @change="syncDesign" />
                </div>
              </div>
              <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span>Show tax breakdown</span>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" v-model="design.show_tax_breakdown" @change="syncDesign" />
                </div>
              </div>
              <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span>Bold total</span>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" v-model="design.bold_total" @change="syncDesign" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import axios from "axios";

const tabsMenu = [
  { key: "appearance", label: "Appearance" },
  { key: "header", label: "Header" },
  { key: "invoice_table", label: "Table" },
  { key: "bottom", label: "Bottom" },
];

const activePanel = ref("appearance");
const previewLogo = ref("https://fakturalista.com/assets/logo.svg");

function defaultDesign() {
  return {
    primary: "#dad7d2",
    text: "#1f1c1a",
    muted: "#6b6764",
    table_header_bg: "#dedad5",
    table_header_text: "#4a4745",
    table_border: "#cfcac5",
    font_family: "Arial, sans-serif",
    font_size: "medium",
    logo_width_mm: 50,
    logo_position: "above",
    show_payment_terms: true,
    show_customer_number: false,
    show_customer_phone: false,
    show_shipping_address: true,
    billing_address_right: false,
    show_discount: false,
    show_tax_column: true,
    show_subtotal: true,
    show_tax_breakdown: true,
    bold_total: true,
    payment_note: "",
    show_payment_note: true,
  };
}

const design = reactive(defaultDesign());
const saving = ref(false);
const saveMessage = ref("");
const saveError = ref("");
const existingTemplateId = ref(null);

function fontSizePx(size) {
  if (size === "small") return 13;
  if (size === "large") return 16;
  return 14;
}

const baseStyle = computed(() => ({
  fontFamily: design.font_family,
  color: design.text,
  fontSize: `${fontSizePx(design.font_size)}px`,
}));

const headingStyle = computed(() => ({
  ...baseStyle.value,
  fontWeight: 700,
  fontSize: `${fontSizePx(design.font_size) + 4}px`,
  color: design.primary,
}));

const titleStyle = computed(() => ({
  ...baseStyle.value,
  fontWeight: 700,
}));

const textStyle = computed(() => ({
  ...baseStyle.value,
}));

const mutedStyle = computed(() => ({
  ...baseStyle.value,
  color: design.muted,
}));

const tableStyle = computed(() => ({
  ...baseStyle.value,
  borderColor: design.table_border,
}));

const tableHeadStyle = computed(() => ({
  background: design.table_header_bg,
  color: design.table_header_text,
  ...baseStyle.value,
  fontWeight: 700,
}));

const thStyle = computed(() => ({
  borderBottom: `1px solid ${design.table_border}`,
  padding: "10px",
}));

const tdStyle = computed(() => ({
  borderBottom: `1px solid ${design.table_border}`,
  padding: "10px",
  verticalAlign: "top",
}));

const totalStyle = computed(() => ({
  ...baseStyle.value,
  fontWeight: design.bold_total ? 900 : 500,
}));

const logoWrapperStyle = computed(() => ({
  width: `${design.logo_width_mm}mm`,
}));

const sample = {
  customerPhone: "657 985 633 / 652 138 016",
  customerNumber: "C-001",
  paymentTerms: "Neto 30 días",
};

const currentDate = new Date().toLocaleDateString("es-ES");

function hexToRgb(hex) {
  if (!hex) return null;
  const clean = hex.replace("#", "");
  if (![3, 6].includes(clean.length)) return null;
  const full = clean.length === 3 ? clean.split("").map((c) => c + c).join("") : clean;
  const intVal = parseInt(full, 16);
  return [(intVal >> 16) & 255, (intVal >> 8) & 255, intVal & 255];
}

function isColorDark(hex) {
  const rgb = hexToRgb(hex);
  if (!rgb) return false;
  const [r, g, b] = rgb;
  const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
  return luminance < 0.6;
}

function applyColorScheme(color) {
  design.primary = color;
  const useLightText = isColorDark(color);
  design.table_header_bg = color;
  design.table_border = color;
  design.table_header_text = useLightText ? "#ffffff" : "#1f1c1a";
  syncDesign();
}

const syncDesign = () => {};

function buildPayload() {
  return {
    name: "Default invoice",
    slug: "default-invoice",
    version: 1,
    is_default: true,
    is_active: true,
    document_type: "invoice",
    engine: "dompdf",
    paper_size: "A4",
    orientation: "portrait",
    locale: "es",
    timezone: "Europe/Madrid",
    margin_top_mm: 15,
    margin_right_mm: 18,
    margin_bottom_mm: 15,
    margin_left_mm: 18,
    css: null,
    header_html: null,
    body_html: "<div></div>",
    footer_html: null,
    ...design,
  };
}

async function loadTemplate() {
  try {
    const { data } = await axios.get("/invoice-templates");
    const tpl = data.templates?.[0];
    if (tpl) {
      existingTemplateId.value = tpl.id;
      Object.keys(defaultDesign()).forEach((key) => {
        if (tpl[key] !== undefined) {
          design[key] = tpl[key];
        }
      });
    }
  } catch (error) {
    console.error("No se pudo cargar plantilla", error);
  }
}

async function saveTemplate() {
  saveMessage.value = "";
  saveError.value = "";
  saving.value = true;

  const payload = buildPayload();

  try {
    let response;
    if (existingTemplateId.value) {
      response = await axios.put(`/invoice-templates/${existingTemplateId.value}`, payload);
    } else {
      response = await axios.post("/invoice-templates", payload);
    }
    const { data } = response;
    saveMessage.value = data.message || "Guardado correctamente";
    if (data.template?.id) {
      existingTemplateId.value = data.template.id;
    }
  } catch (error) {
    saveError.value =
      error.response?.data?.message ||
      error.response?.data?.error ||
      "No se pudo guardar";
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  loadTemplate();
});
</script>
