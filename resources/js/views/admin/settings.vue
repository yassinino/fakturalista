<template>
  <div class="content content-boxed">
    <div v-if="isLoading" class="text-center py-5">
      <i class="fa fa-spinner fa-spin me-2"></i> Cargando ajustes...
    </div>

    <div v-else class="row g-4">
      <div class="col-lg-3">
        <BaseBlock class="h-auto">
          <template #content>
            <div class="list-group list-group-flush">
              <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="list-group-item list-group-item-action d-flex align-items-center"
                :class="{ active: selectedTab === tab.key }"
                @click="selectedTab = tab.key"
              >
                <i v-if="tab.icon" :class="`${tab.icon} me-2`"></i>
                {{ tab.label }}
              </button>
            </div>
          </template>
        </BaseBlock>
      </div>

      <div class="col-lg-9">
        <form @submit.prevent="submitSettings">
          <BaseBlock
            v-if="selectedTab === 'identity'"
            title="Identidad de la empresa"
          >
            <template #content>
              <div class="row push content">
                <div class="col-lg-4">
                  <p class="fs-sm text-muted">
                    Define el nombre legal, comercial y país de registro de tu empresa.
                  </p>
                </div>
                <div class="col-lg-8 col-xl-8">
                  <div class="mb-4">
                    <label class="form-label" for="legal_name">Nombre legal</label>
                    <input
                      id="legal_name"
                      type="text"
                      class="form-control"
                      v-model="form.legal_name"
                      :disabled="isSaving"
                      :class="{ 'is-invalid': errors.legal_name }"
                      required
                      placeholder="Nombre registrado"
                    />
                    <div v-if="errors.legal_name" class="invalid-feedback">
                      {{ errors.legal_name[0] }}
                    </div>
                  </div>

                  <div class="mb-4">
                    <label class="form-label" for="trade_name">Nombre comercial</label>
                    <input
                      id="trade_name"
                      type="text"
                      class="form-control"
                      v-model="form.trade_name"
                      :disabled="isSaving"
                      :class="{ 'is-invalid': errors.trade_name }"
                      placeholder="Marca pública (opcional)"
                    />
                    <div v-if="errors.trade_name" class="invalid-feedback">
                      {{ errors.trade_name[0] }}
                    </div>
                  </div>

                  <div class="row mb-2">
                    <div class="col-12 col-md-6 mb-4 mb-md-0">
                      <label class="form-label" for="industry">Industria</label>
                      <input
                        id="industry"
                        type="text"
                        class="form-control"
                        v-model="form.industry"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.industry }"
                        placeholder="Ej: Software, Retail..."
                      />
                      <div v-if="errors.industry" class="invalid-feedback">
                        {{ errors.industry[0] }}
                      </div>
                    </div>
                    <div class="col-12 col-md-6">
                      <label class="form-label" for="country_code">Código país (ISO2)</label>
                      <input
                        id="country_code"
                        type="text"
                        class="form-control"
                        v-model="form.country_code"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.country_code }"
                        maxlength="2"
                        placeholder="ES"
                      />
                      <div v-if="errors.country_code" class="invalid-feedback">
                        {{ errors.country_code[0] }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </BaseBlock>

          <BaseBlock
            v-if="selectedTab === 'tax'"
            title="Datos fiscales"
          >
            <template #content>
              <div class="row push content">
                <div class="col-lg-4">
                  <p class="fs-sm text-muted">
                    Identificadores legales y de impuestos (NIF, VAT, registro mercantil).
                  </p>
                </div>
                <div class="col-lg-8 col-xl-8">
                  <div class="row">
                    <div class="col-12 col-md-12 mb-4">
                      <label class="form-label" for="tax_id">NIF / CIF / ICE</label>
                      <input
                        id="tax_id"
                        type="text"
                        class="form-control"
                        v-model="form.tax_id"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.tax_id }"
                        placeholder="Identificación fiscal"
                      />
                      <div v-if="errors.tax_id" class="invalid-feedback">
                        {{ errors.tax_id[0] }}
                      </div>
                    </div>
                    <div class="col-12 col-md-12 mb-4">
                      <label class="form-label" for="vat_number">Número VAT</label>
                      <input
                        id="vat_number"
                        type="text"
                        class="form-control"
                        v-model="form.vat_number"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.vat_number }"
                        placeholder="ES00000000"
                      />
                      <div v-if="errors.vat_number" class="invalid-feedback">
                        {{ errors.vat_number[0] }}
                      </div>
                    </div>
                    <div class="col-12 mb-2">
                      <label class="form-label" for="registration_number">Registro mercantil</label>
                      <input
                        id="registration_number"
                        type="text"
                        class="form-control"
                        v-model="form.registration_number"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.registration_number }"
                        placeholder="Número de registro"
                      />
                      <div v-if="errors.registration_number" class="invalid-feedback">
                        {{ errors.registration_number[0] }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </BaseBlock>

          <BaseBlock
            v-if="selectedTab === 'contact'"
            title="Contacto y dirección"
          >
            <template #content>
              <div class="row push content">
                <div class="col-lg-4">
                  <p class="fs-sm text-muted">
                    Datos de contacto y ubicación fiscal/postal.
                  </p>
                </div>
                <div class="col-lg-8 col-xl-8">
                  <div class="row">
                    <div class="col-12 col-md-6 mb-4">
                      <label class="form-label" for="email">Correo</label>
                      <input
                        id="email"
                        type="email"
                        class="form-control"
                        v-model="form.email"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.email }"
                        placeholder="empresa@correo.com"
                      />
                      <div v-if="errors.email" class="invalid-feedback">
                        {{ errors.email[0] }}
                      </div>
                    </div>
                    <div class="col-12 col-md-6 mb-4">
                      <label class="form-label" for="phone">Teléfono</label>
                      <input
                        id="phone"
                        type="text"
                        class="form-control"
                        v-model="form.phone"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.phone }"
                        placeholder="+34 600 000 000"
                      />
                      <div v-if="errors.phone" class="invalid-feedback">
                        {{ errors.phone[0] }}
                      </div>
                    </div>
                    <div class="col-12 mb-4">
                      <label class="form-label" for="website">Sitio web</label>
                      <input
                        id="website"
                        type="text"
                        class="form-control"
                        v-model="form.website"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.website }"
                        placeholder="https://example.com"
                      />
                      <div v-if="errors.website" class="invalid-feedback">
                        {{ errors.website[0] }}
                      </div>
                    </div>
                  </div>

                  <div class="mb-4">
                    <label class="form-label" for="address_line1">Dirección (línea 1)</label>
                    <input
                      id="address_line1"
                      type="text"
                      class="form-control"
                      v-model="form.address_line1"
                      :disabled="isSaving"
                      :class="{ 'is-invalid': errors.address_line1 }"
                      placeholder="Calle y número"
                    />
                    <div v-if="errors.address_line1" class="invalid-feedback">
                      {{ errors.address_line1[0] }}
                    </div>
                  </div>

                  <div class="mb-4">
                    <label class="form-label" for="address_line2">Dirección (línea 2)</label>
                    <input
                      id="address_line2"
                      type="text"
                      class="form-control"
                      v-model="form.address_line2"
                      :disabled="isSaving"
                      :class="{ 'is-invalid': errors.address_line2 }"
                      placeholder="Portal, piso, escalera (opcional)"
                    />
                    <div v-if="errors.address_line2" class="invalid-feedback">
                      {{ errors.address_line2[0] }}
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-12 col-md-6 mb-4">
                      <label class="form-label" for="city">Ciudad</label>
                      <input
                        id="city"
                        type="text"
                        class="form-control"
                        v-model="form.city"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.city }"
                        placeholder="Barcelona"
                      />
                      <div v-if="errors.city" class="invalid-feedback">
                        {{ errors.city[0] }}
                      </div>
                    </div>
                    <div class="col-12 col-md-6 mb-4">
                      <label class="form-label" for="state">Provincia / Estado</label>
                      <input
                        id="state"
                        type="text"
                        class="form-control"
                        v-model="form.state"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.state }"
                        placeholder="Barcelona"
                      />
                      <div v-if="errors.state" class="invalid-feedback">
                        {{ errors.state[0] }}
                      </div>
                    </div>
                    <div class="col-12 col-md-12 mb-4">
                      <label class="form-label" for="postal_code">Código postal</label>
                      <input
                        id="postal_code"
                        type="text"
                        class="form-control"
                        v-model="form.postal_code"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.postal_code }"
                        placeholder="08001"
                      />
                      <div v-if="errors.postal_code" class="invalid-feedback">
                        {{ errors.postal_code[0] }}
                      </div>
                    </div>
                  </div>

                  <div class="mb-2">
                    <label class="form-label" for="country">País (texto libre)</label>
                    <input
                      id="country"
                      type="text"
                      class="form-control"
                      v-model="form.country"
                      :disabled="isSaving"
                      :class="{ 'is-invalid': errors.country }"
                      placeholder="España"
                    />
                    <div v-if="errors.country" class="invalid-feedback">
                      {{ errors.country[0] }}
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </BaseBlock>

          <BaseBlock
            v-if="selectedTab === 'branding'"
            title="Branding"
          >
            <template #content>
              <div class="row push content">
                <div class="col-lg-4">
                  <p class="fs-sm text-muted">
                    Logo, sello y color que se usarán en tus documentos.
                  </p>
                </div>
                <div class="col-lg-8 col-xl-8">
                  <div class="mb-4">
                    <label class="form-label" for="logo">Logo</label>
                    <div class="d-flex align-items-center">
                      <div class="me-3">
                        <div
                          class="border rounded bg-body d-flex align-items-center justify-content-center"
                          style="width: 96px; height: 96px"
                        >
                          <img
                            v-if="logoPreview"
                            :src="logoPreview"
                            class="img-fluid"
                            alt="Logo"
                            style="max-height: 80px"
                          />
                          <span v-else class="text-muted small">Sin logo</span>
                        </div>
                      </div>
                      <div class="flex-grow-1">
                        <input
                          id="logo"
                          class="form-control"
                          type="file"
                          accept="image/*"
                          @change="onFileChange($event, 'logo')"
                          :disabled="isSaving"
                        />
                        <small class="text-muted">Formatos recomendados: PNG o SVG.</small>
                      </div>
                    </div>
                    <div v-if="errors.logo" class="invalid-feedback d-block">
                      {{ errors.logo[0] }}
                    </div>
                  </div>

                  <div class="mb-4">
                    <label class="form-label" for="stamp">Sello / Firma</label>
                    <div class="d-flex align-items-center">
                      <div class="me-3">
                        <div
                          class="border rounded bg-body d-flex align-items-center justify-content-center"
                          style="width: 96px; height: 96px"
                        >
                          <img
                            v-if="stampPreview"
                            :src="stampPreview"
                            class="img-fluid"
                            alt="Sello"
                            style="max-height: 80px"
                          />
                          <span v-else class="text-muted small">Sin sello</span>
                        </div>
                      </div>
                      <div class="flex-grow-1">
                        <input
                          id="stamp"
                          class="form-control"
                          type="file"
                          accept="image/*"
                          @change="onFileChange($event, 'stamp')"
                          :disabled="isSaving"
                        />
                        <small class="text-muted">Opcional para sellar facturas.</small>
                        <div class="mt-2 d-flex gap-2 flex-wrap">
                          <button
                            type="button"
                            class="btn btn-sm btn-outline-primary"
                            :disabled="isSaving"
                            @click="openSignaturePad"
                          >
                            Crear firma en la app
                          </button>
                          <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary"
                            :disabled="isSaving"
                            @click="() => { stampFile.value = null; stampPreview.value = '' }"
                          >
                            Quitar firma
                          </button>
                        </div>
                      </div>
                    </div>
                    <div v-if="errors.stamp" class="invalid-feedback d-block">
                      {{ errors.stamp[0] }}
                    </div>
                  </div>

                  <!-- <div class="mb-4">
                    <label class="form-label" for="brand_color">Color de marca</label>
                    <div class="d-flex align-items-center">
                      <input
                        id="brand_color"
                        type="color"
                        class="form-control form-control-color me-2"
                        v-model="form.brand_color"
                        :disabled="isSaving"
                      />
                      <input
                        type="text"
                        class="form-control"
                        v-model="form.brand_color"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.brand_color }"
                        placeholder="#0d6efd"
                      />
                    </div>
                    <div v-if="errors.brand_color" class="invalid-feedback d-block">
                      {{ errors.brand_color[0] }}
                    </div>
                  </div> -->

                  <div class="mb-2">
                    <label class="form-label" for="invoice_footer_note">Nota de pie de factura</label>
                    <textarea
                      id="invoice_footer_note"
                      class="form-control"
                      rows="3"
                      v-model="form.invoice_footer_note"
                      :disabled="isSaving"
                      :class="{ 'is-invalid': errors.invoice_footer_note }"
                      placeholder="Mensaje final en las facturas (1000 caracteres máx.)"
                    ></textarea>
                    <div v-if="errors.invoice_footer_note" class="invalid-feedback">
                      {{ errors.invoice_footer_note[0] }}
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </BaseBlock>

          <BaseBlock
            v-if="selectedTab === 'billing'"
            title="Facturación por defecto"
          >
            <template #content>
              <div class="row push content">
                <div class="col-lg-4">
                  <p class="fs-sm text-muted">
                    Prefijo, numeración y formato para tus facturas.
                  </p>
                </div>
                <div class="col-lg-8 col-xl-8">
                  <div class="row">
                    <div class="col-12 col-md-6 mb-4">
                      <label class="form-label" for="invoice_prefix">Prefijo</label>
                      <input
                        id="invoice_prefix"
                        type="text"
                        class="form-control"
                        v-model="form.invoice_prefix"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.invoice_prefix }"
                        maxlength="20"
                        required
                      />
                      <div v-if="errors.invoice_prefix" class="invalid-feedback">
                        {{ errors.invoice_prefix[0] }}
                      </div>
                    </div>
                    <div class="col-12 col-md-6 mb-4">
                      <label class="form-label" for="invoice_next_number">Próximo número</label>
                      <input
                        id="invoice_next_number"
                        type="number"
                        min="1"
                        class="form-control"
                        v-model.number="form.invoice_next_number"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.invoice_next_number }"
                        required
                      />
                      <div v-if="errors.invoice_next_number" class="invalid-feedback">
                        {{ errors.invoice_next_number[0] }}
                      </div>
                    </div>
                    <div class="col-12 col-md-6 mb-4">
                      <label class="form-label" for="invoice_number_format">Formato</label>
                      <input
                        id="invoice_number_format"
                        type="text"
                        class="form-control"
                        v-model="form.invoice_number_format"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.invoice_number_format }"
                        placeholder="{PREFIX}-{YYYY}-{NUMBER}"
                        required
                      />
                      <div v-if="errors.invoice_number_format" class="invalid-feedback">
                        {{ errors.invoice_number_format[0] }}
                      </div>
                    </div>
                    <div class="col-12 col-md-6 mb-4">
                      <label class="form-label" for="timezone">Zona horaria</label>
                      <input
                        id="timezone"
                        type="text"
                        class="form-control"
                        v-model="form.timezone"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.timezone }"
                        placeholder="Europe/Madrid"
                      />
                      <div v-if="errors.timezone" class="invalid-feedback">
                        {{ errors.timezone[0] }}
                      </div>
                    </div>
                    <div class="col-12 col-md-6 mb-4">
                      <label class="form-label" for="locale">Idioma</label>
                      <input
                        id="locale"
                        type="text"
                        class="form-control"
                        v-model="form.locale"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.locale }"
                        placeholder="es"
                      />
                      <div v-if="errors.locale" class="invalid-feedback">
                        {{ errors.locale[0] }}
                      </div>
                    </div>
                    <div class="col-12 col-md-6 mb-2">
                      <label class="form-label" for="currency">Moneda (ISO3)</label>
                      <input
                        id="currency"
                        type="text"
                        class="form-control"
                        v-model="form.currency"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.currency }"
                        maxlength="3"
                        placeholder="EUR"
                      />
                      <div v-if="errors.currency" class="invalid-feedback">
                        {{ errors.currency[0] }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </BaseBlock>

          <BaseBlock
            v-if="selectedTab === 'bank'"
            title="Datos bancarios"
          >
            <template #content>
              <div class="row push content">
                <div class="col-lg-4">
                  <p class="fs-sm text-muted">
                    Información que aparecerá en tus facturas para cobrar por banco.
                  </p>
                </div>
                <div class="col-lg-8 col-xl-8">
                  <div class="row">
                    <div class="col-12 col-md-12 mb-4">
                      <label class="form-label" for="bank_name">Banco</label>
                      <input
                        id="bank_name"
                        type="text"
                        class="form-control"
                        v-model="form.bank_name"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.bank_name }"
                        placeholder="Nombre del banco"
                      />
                      <div v-if="errors.bank_name" class="invalid-feedback">
                        {{ errors.bank_name[0] }}
                      </div>
                    </div>
                    <div class="col-12 col-md-12 mb-4">
                      <label class="form-label" for="iban">IBAN</label>
                      <input
                        id="iban"
                        type="text"
                        class="form-control"
                        v-model="form.iban"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.iban }"
                        placeholder="ES00 0000 0000 0000 0000 0000"
                      />
                      <div v-if="errors.iban" class="invalid-feedback">
                        {{ errors.iban[0] }}
                      </div>
                    </div>
                    <div class="col-12 col-md-12 mb-2">
                      <label class="form-label" for="swift">SWIFT / BIC</label>
                      <input
                        id="swift"
                        type="text"
                        class="form-control"
                        v-model="form.swift"
                        :disabled="isSaving"
                        :class="{ 'is-invalid': errors.swift }"
                        placeholder="ABCDEFGH"
                      />
                      <div v-if="errors.swift" class="invalid-feedback">
                        {{ errors.swift[0] }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </BaseBlock>

          <div class="d-flex justify-content-end mt-3 mb-4">
            <button
              type="submit"
              class="btn btn-alt-primary"
              :disabled="isSaving"
            >
              <span
                v-if="isSaving"
                class="spinner-border spinner-border-sm me-2"
                role="status"
                aria-hidden="true"
              ></span>
              Guardar cambios
            </button>
          </div>
        </form>
      </div>
    </div>

    <div
      v-if="showSignaturePad"
      class="modal fade show"
      style="display: block; background: rgba(0,0,0,0.4);"
      tabindex="-1"
      role="dialog"
    >
      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Crear firma</h5>
            <button type="button" class="btn-close" @click="showSignaturePad = false"></button>
          </div>
          <div class="modal-body">
            <p class="text-muted mb-2">Dibuja tu firma con el ratón o el dedo.</p>
            <div class="border rounded bg-body d-flex align-items-center justify-content-center p-2">
              <canvas
                ref="signatureCanvas"
                style="width: 100%; height: 200px; touch-action: none;"
                @mousedown="startSignature"
                @mousemove="drawSignature"
                @mouseup="endSignature"
                @mouseleave="endSignature"
                @touchstart="startSignature"
                @touchmove="drawSignature"
                @touchend="endSignature"
                @touchcancel="endSignature"
              ></canvas>
            </div>
          </div>
          <div class="modal-footer d-flex justify-content-between">
            <div>
              <button type="button" class="btn btn-outline-secondary btn-sm" @click="clearSignature">
                Limpiar
              </button>
            </div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-outline-secondary" @click="showSignaturePad = false">
                Cancelar
              </button>
              <button type="button" class="btn btn-primary" @click="saveSignature">
                Usar esta firma
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted, nextTick } from "vue";
import axios from "axios";
import { createToaster } from "@meforma/vue-toaster";

const toaster = createToaster({});

const isLoading = ref(false);
const isSaving = ref(false);
const errors = ref({});

const tabs = [
  { key: "identity", label: "Mi empresa", icon: "fa fa-building" },
  { key: "tax", label: "Datos fiscales", icon: "fa fa-file-invoice-dollar" },
  { key: "contact", label: "Contacto y dirección", icon: "fa fa-map-marker-alt" },
  { key: "branding", label: "Branding", icon: "fa fa-palette" },
  { key: "billing", label: "Facturación", icon: "fa fa-list-ol" },
  { key: "bank", label: "Datos bancarios", icon: "fa fa-university" },
];

const selectedTab = ref(tabs[0].key);

const form = reactive({
  legal_name: "",
  trade_name: "",
  industry: "",
  country_code: "ES",
  tax_id: "",
  vat_number: "",
  registration_number: "",
  email: "",
  phone: "",
  website: "",
  address_line1: "",
  address_line2: "",
  city: "",
  state: "",
  postal_code: "",
  country: "",
  brand_color: "#0d6efd",
  invoice_footer_note: "",
  invoice_prefix: "INV",
  invoice_next_number: 1,
  invoice_number_format: "{PREFIX}-{YYYY}-{NUMBER}",
  timezone: "Europe/Madrid",
  locale: "es",
  currency: "EUR",
  bank_name: "",
  iban: "",
  swift: "",
});

const logoPreview = ref("");
const stampPreview = ref("");
const logoFile = ref(null);
const stampFile = ref(null);
const showSignaturePad = ref(false);
const signatureCanvas = ref(null);
const isDrawing = ref(false);
const signatureCtx = ref(null);

const fillForm = (data = {}) => {
  Object.keys(form).forEach((key) => {
    if (data[key] !== undefined && data[key] !== null) {
      form[key] = data[key];
    }
  });

  if (data.logo_path) {
    logoPreview.value = data.logo_path;
  }
  if (data.stamp_path) {
    stampPreview.value = data.stamp_path;
  }
};

const fetchSettings = async () => {
  isLoading.value = true;
  errors.value = {};

  try {
    const { data } = await axios.get("/settings");
    const settings = data?.settings ?? data ?? {};
    fillForm(settings);
  } catch (error) {
    toaster.error("No se pudieron cargar los ajustes de empresa.");
  } finally {
    isLoading.value = false;
  }
};

onMounted(fetchSettings);

const onFileChange = (event, type) => {
  const [file] = event.target.files || [];

  if (type === "logo") {
    logoFile.value = file || null;
    logoPreview.value = file ? URL.createObjectURL(file) : "";
  } else {
    stampFile.value = file || null;
    stampPreview.value = file ? URL.createObjectURL(file) : "";
  }
};

const openSignaturePad = () => {
  showSignaturePad.value = true;
  nextTick(() => {
    const canvas = signatureCanvas.value;
    if (!canvas) return;
    const dpr = window.devicePixelRatio || 1;
    const width = 500;
    const height = 200;
    canvas.width = width * dpr;
    canvas.height = height * dpr;
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;
    const ctx = canvas.getContext("2d");
    ctx.scale(dpr, dpr);
    ctx.lineWidth = 2;
    ctx.lineCap = "round";
    ctx.strokeStyle = "#111";
    signatureCtx.value = ctx;
    ctx.clearRect(0, 0, width, height);
  });
};

const getCanvasPos = (event) => {
  const canvas = signatureCanvas.value;
  if (!canvas) return { x: 0, y: 0 };
  const rect = canvas.getBoundingClientRect();
  const clientX = event.touches ? event.touches[0].clientX : event.clientX;
  const clientY = event.touches ? event.touches[0].clientY : event.clientY;
  return {
    x: clientX - rect.left,
    y: clientY - rect.top,
  };
};

const startSignature = (event) => {
  if (!signatureCtx.value) return;
  isDrawing.value = true;
  const { x, y } = getCanvasPos(event);
  signatureCtx.value.beginPath();
  signatureCtx.value.moveTo(x, y);
};

const drawSignature = (event) => {
  if (!isDrawing.value || !signatureCtx.value) return;
  event.preventDefault();
  const { x, y } = getCanvasPos(event);
  signatureCtx.value.lineTo(x, y);
  signatureCtx.value.stroke();
};

const endSignature = () => {
  if (!isDrawing.value || !signatureCtx.value) return;
  signatureCtx.value.closePath();
  isDrawing.value = false;
};

const clearSignature = () => {
  const canvas = signatureCanvas.value;
  if (!canvas || !signatureCtx.value) return;
  signatureCtx.value.clearRect(0, 0, canvas.width, canvas.height);
};

const saveSignature = () => {
  const canvas = signatureCanvas.value;
  if (!canvas) return;
  canvas.toBlob((blob) => {
    if (!blob) return;
    stampFile.value = new File([blob], "signature.png", { type: "image/png" });
    stampPreview.value = URL.createObjectURL(blob);
    showSignaturePad.value = false;
  });
};

const submitSettings = async () => {
  isSaving.value = true;
  errors.value = {};

  try {
    const payload = new FormData();
    payload.append("_method", "put");

    Object.entries(form).forEach(([key, value]) => {
      const normalizedValue =
        value === null || value === undefined ? "" : value;
      payload.append(key, normalizedValue);
    });

    if (logoFile.value) {
      payload.append("logo", logoFile.value);
    }
    if (stampFile.value) {
      payload.append("stamp", stampFile.value);
    }

    const { data } = await axios.post("/settings", payload, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });

    toaster.success(data?.message ?? "Ajustes guardados correctamente.");

    const newSettings = data?.settings ?? {};
    fillForm(newSettings);
    logoFile.value = null;
    stampFile.value = null;
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data?.errors || {};
      return;
    }

    toaster.error("No se pudieron guardar los ajustes.");
  } finally {
    isSaving.value = false;
  }
};
</script>
