<template>
  <div class="content">
    <div class="tb-root">

      <!-- ════ PHASE: GALLERY ════ -->
      <transition name="tb-phase" mode="out-in">
        <div v-if="phase === 'gallery'" class="tb-gallery" key="gallery">
          <div class="tb-gallery-hd">
            <h2 class="tb-gallery-title">{{ $t('templates.pageTitle') }}</h2>
            <p class="tb-gallery-sub">{{ $t('templates.pageSubtitle') }}</p>
          </div>

          <div class="tb-preset-grid">
            <button
              v-for="preset in PRESETS"
              :key="preset.id"
              class="tb-preset-card"
              :class="{ 'tb-preset-card--active': activePresetId === preset.id }"
              @click="selectPreset(preset); phase = 'editor'"
            >
              <!-- Mini doc thumbnail -->
              <div class="tb-mini-doc">
                <div class="tb-mini-accent" :style="{ background: preset.design.primary }"></div>
                <div class="tb-mini-body">
                  <div class="tb-mini-header-row">
                    <div class="tb-mini-logo-block"></div>
                    <div class="tb-mini-ref">
                      <div class="tb-mini-line tb-mini-line--title" :style="{ background: preset.design.primary }"></div>
                      <div class="tb-mini-line tb-mini-line--sub"></div>
                    </div>
                  </div>
                  <div class="tb-mini-addr-row">
                    <div class="tb-mini-addr-block">
                      <div class="tb-mini-line tb-mini-line--sm"></div>
                      <div class="tb-mini-line tb-mini-line--md"></div>
                      <div class="tb-mini-line tb-mini-line--sm"></div>
                    </div>
                  </div>
                  <div class="tb-mini-table">
                    <div class="tb-mini-thead" :style="{ background: preset.design.table_header_bg, color: preset.design.table_header_text }">
                      <div class="tb-mini-th tb-mini-th--wide"></div>
                      <div class="tb-mini-th"></div>
                      <div class="tb-mini-th"></div>
                    </div>
                    <div class="tb-mini-trow" v-for="r in 2" :key="r">
                      <div class="tb-mini-td tb-mini-td--wide"></div>
                      <div class="tb-mini-td"></div>
                      <div class="tb-mini-td"></div>
                    </div>
                  </div>
                  <div class="tb-mini-total-row">
                    <div class="tb-mini-total-label" :style="{ background: preset.design.primary }"></div>
                  </div>
                </div>
              </div>

              <!-- Card info -->
              <div class="tb-preset-meta">
                <div v-if="preset.badge" class="tb-preset-badge">{{ preset.badge }}</div>
                <span class="tb-preset-name">{{ preset.name }}</span>
                <span class="tb-preset-desc">{{ preset.desc }}</span>
              </div>

              <div v-if="activePresetId === preset.id" class="tb-preset-check">
                <i class="fa fa-check"></i>
              </div>
            </button>
          </div>
        </div><!-- /gallery -->

        <!-- ════ PHASE: EDITOR ════ -->
        <div v-else class="tb-editor" key="editor">

          <!-- Top bar -->
          <div class="tb-topbar">
            <button class="tb-topbar-back" @click="phase = 'gallery'">
              <i class="fa fa-arrow-left"></i>
              <span>Templates</span>
            </button>
            <div class="tb-topbar-mid">
              <span class="tb-topbar-preset-dot" :style="{ background: design.primary }"></span>
              <span class="tb-topbar-preset-name">{{ activePresetName }}</span>
            </div>
            <div class="tb-topbar-right">
              <transition name="tb-fade">
                <span v-if="saveMessage" class="tb-feedback tb-feedback--ok">
                  <i class="fa fa-check-circle"></i>{{ saveMessage }}
                </span>
              </transition>
              <transition name="tb-fade">
                <span v-if="saveError" class="tb-feedback tb-feedback--err">
                  <i class="fa fa-exclamation-circle"></i>{{ saveError }}
                </span>
              </transition>
              <button class="tb-btn tb-btn--ghost" @click="resetDesign">
                <i class="fa fa-rotate-left"></i>{{ $t('templates.resetBtn') }}
              </button>
              <button class="tb-btn tb-btn--primary" @click="saveTemplate" :disabled="saving">
                <i v-if="saving" class="fa fa-spinner fa-spin"></i>
                <i v-else class="fa fa-save"></i>
                {{ saving ? $t('templates.savingBtn') : $t('templates.saveBtn') }}
              </button>
            </div>
          </div>

          <!-- 3-panel layout -->
          <div class="tb-panels">

            <!-- LEFT: compact template switcher -->
            <aside class="tb-panel-left">
              <div class="tb-panel-left-head">Templates</div>
              <div class="tb-template-list">
                <button
                  v-for="preset in PRESETS"
                  :key="preset.id"
                  class="tb-tpl-thumb"
                  :class="{ 'tb-tpl-thumb--active': activePresetId === preset.id }"
                  @click="selectPreset(preset)"
                >
                  <div class="tb-tpl-swatch" :style="{ background: preset.design.primary }"></div>
                  <div class="tb-tpl-info">
                    <span class="tb-tpl-name">{{ preset.name }}</span>
                    <span class="tb-tpl-desc">{{ preset.desc }}</span>
                  </div>
                  <i v-if="activePresetId === preset.id" class="fa fa-check tb-tpl-check"></i>
                </button>
              </div>
            </aside>

            <!-- CENTER: live A4 preview -->
            <div class="tb-panel-center">
              <div class="tb-preview-label">
                <i class="fa fa-eye"></i>{{ $t('templates.preview.label') }}
              </div>
              <div class="tb-preview-stage">
                <div class="tb-paper" :style="paperBaseStyle">

                  <!-- Header: 2-column table matching PDF _header.blade.php exactly -->
                  <table class="tb-header-table">
                    <!-- Logo above: spans both columns (mirrors $isLogoAbove in the PDF blade) -->
                    <tr v-if="design.logo_position === 'above' && previewLogo">
                      <td colspan="2" class="tb-logo-above-cell">
                        <img :src="previewLogo" class="tb-inv-logo" :style="{ maxWidth: `${logoWidthPx}px`, marginBottom: '0' }" />
                      </td>
                    </tr>
                    <tr>
                      <!-- Left: logo (left position) + company name + contact -->
                      <td class="tb-col-company">
                        <img
                          v-if="previewLogo && design.logo_position !== 'above'"
                          :src="previewLogo"
                          class="tb-inv-logo"
                          :style="{ maxWidth: `${logoWidthPx}px` }"
                        />
                        <div :style="companyNameStyle">Your Company</div>
                        <div :style="companyDetailStyle">123 Main Street, City</div>
                        <div :style="companyDetailStyle">Tel: +1 234 567 890</div>
                        <div :style="companyDetailStyle">contact@yourcompany.com</div>
                      </td>
                      <!-- Right: doc label + reference + date -->
                      <td class="tb-col-meta">
                        <div :style="docLabelStyle">{{ $t('templates.preview.invoiceTitle') }}</div>
                        <div :style="docNumberStyle">{{ $t('templates.preview.invoiceTitle') }} N° INV-0001</div>
                        <div class="tb-meta-row">
                          <span class="tb-meta-key">{{ $t('templates.preview.dateLabel') }}</span>
                          <span class="tb-meta-val" :style="metaValStyle">{{ currentDate }}</span>
                        </div>
                      </td>
                    </tr>
                  </table>

                  <!-- Divider: 2px solid matching PDF .divider -->
                  <hr class="tb-inv-divider" :style="{ borderTop: `2px solid ${design.primary}` }" />

                  <!-- Addresses: table-based column swap matching PDF _addresses.blade.php -->
                  <table class="tb-addrs-table">
                    <tr>
                      <template v-if="design.billing_address_right">
                        <!-- Shipping left, billing right -->
                        <td v-if="design.show_shipping_address" style="width:50%; padding-right:20px; vertical-align:top;">
                          <div class="tb-addr-label" :style="addrLabelStyle">{{ $t('templates.preview.shippingAddress') }}</div>
                          <div :style="addrNameStyle">MISC CUSTOMER</div>
                          <div :style="addrSubStyle">ait melloul, agadir 68602</div>
                        </td>
                        <td :style="{ width: design.show_shipping_address ? '50%' : '100%', verticalAlign: 'top' }">
                          <div class="tb-addr-label" :style="addrLabelStyle">{{ $t('templates.preview.billingAddress') }}</div>
                          <div :style="addrNameStyle">MISC CUSTOMER</div>
                          <div v-if="design.show_customer_number" :style="addrSubStyle">Cliente #C-001</div>
                          <div :style="addrSubStyle">ait melloul, agadir 68602</div>
                          <div v-if="design.show_customer_phone" :style="addrSubStyle">Tel: 657 985 633</div>
                        </td>
                      </template>
                      <template v-else>
                        <!-- Billing left, shipping right (default) -->
                        <td :style="{ width: design.show_shipping_address ? '50%' : '100%', paddingRight: design.show_shipping_address ? '20px' : '0', verticalAlign: 'top' }">
                          <div class="tb-addr-label" :style="addrLabelStyle">{{ $t('templates.preview.billingAddress') }}</div>
                          <div :style="addrNameStyle">MISC CUSTOMER</div>
                          <div v-if="design.show_customer_number" :style="addrSubStyle">Cliente #C-001</div>
                          <div :style="addrSubStyle">ait melloul, agadir 68602</div>
                          <div v-if="design.show_customer_phone" :style="addrSubStyle">Tel: 657 985 633</div>
                        </td>
                        <td v-if="design.show_shipping_address" style="width:50%; vertical-align:top;">
                          <div class="tb-addr-label" :style="addrLabelStyle">{{ $t('templates.preview.shippingAddress') }}</div>
                          <div :style="addrNameStyle">MISC CUSTOMER</div>
                          <div :style="addrSubStyle">ait melloul, agadir 68602</div>
                        </td>
                      </template>
                    </tr>
                  </table>

                  <!-- Items table: column widths match PDF _items.blade.php -->
                  <table class="tb-inv-table" :style="tableStyle">
                    <thead>
                      <tr :style="tableHeadStyle">
                        <th :style="thStyle" class="tb-th-main">{{ $t('templates.preview.itemCol') }}</th>
                        <th v-if="design.show_tax_column" :style="thStyle" class="tb-th-center">IVA</th>
                        <th v-if="design.show_discount" :style="thStyle" class="tb-th-center">Dto.</th>
                        <th :style="thStyle" class="tb-th-center">{{ $t('templates.preview.qtyCol') }}</th>
                        <th :style="thStyle" class="tb-th-center">{{ $t('templates.preview.priceCol') }}</th>
                        <th :style="thStyle" class="tb-th-right">{{ $t('templates.preview.amountCol') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td :style="tdStyle">
                          <div :style="itemNameStyle">PS-001 test</div>
                          <div :style="itemDescStyle">{{ $t('templates.preview.itemDesc') }}</div>
                        </td>
                        <td v-if="design.show_tax_column" :style="tdStyle" class="tb-td-center">20%</td>
                        <td v-if="design.show_discount" :style="tdStyle" class="tb-td-center">5%</td>
                        <td :style="tdStyle" class="tb-td-center">1</td>
                        <td :style="tdStyle" class="tb-td-center">333,00 €</td>
                        <td :style="tdStyle" class="tb-td-right">333,00 €</td>
                      </tr>
                    </tbody>
                  </table>

                  <!-- Totals: 50% spacer + 50% table (matches PDF's DomPDF-safe table approach) -->
                  <table class="tb-totals-outer">
                    <tr>
                      <td class="tb-totals-spacer"></td>
                      <td class="tb-totals-data">
                        <table class="tb-totals-inner">
                          <tbody>
                            <tr v-if="design.show_subtotal">
                              <td :style="totalLabelStyle">{{ $t('templates.preview.subtotalHT') }}</td>
                              <td :style="totalValueStyle">333,00 €</td>
                            </tr>
                            <tr v-if="design.show_tax_breakdown">
                              <td :style="totalLabelStyle">IVA (333 × 20%)</td>
                              <td :style="totalValueStyle">66,60 €</td>
                            </tr>
                            <tr v-if="design.show_payment_terms">
                              <td :style="totalLabelStyle">{{ $t('templates.preview.paymentTermsRow') }}</td>
                              <td :style="totalValueStyle">{{ sample.paymentTerms }}</td>
                            </tr>
                            <tr>
                              <td :style="totalFinalLabelStyle">{{ $t('templates.preview.grandTotal') }}</td>
                              <td :style="totalFinalValueStyle">399,60 €</td>
                            </tr>
                          </tbody>
                        </table>
                      </td>
                    </tr>
                  </table>

                  <div v-if="design.show_payment_note && design.payment_note" class="tb-inv-note">
                    <div :style="titleStyle">{{ $t('templates.preview.paymentInfo') }}</div>
                    <div :style="mutedStyle" style="margin-top:3px;">{{ design.payment_note }}</div>
                  </div>

                </div><!-- /.tb-paper -->
              </div><!-- /.tb-preview-stage -->
            </div><!-- /center -->

            <!-- RIGHT: settings panel -->
            <aside class="tb-panel-right">
              <div class="tb-settings-scroll">

                <!-- ─ SIMPLE SETTINGS (always visible) ─ -->
                <div class="tb-simple">

                  <!-- Logo -->
                  <div class="tb-setting-block">
                    <div class="tb-setting-label">{{ $t('templates.sections.logo') }}</div>
                    <div
                      class="tb-logo-drop"
                      :class="{ 'tb-logo-drop--has': previewLogo, 'tb-logo-drop--over': isDraggingLogo }"
                      @dragover.prevent="isDraggingLogo = true"
                      @dragleave="isDraggingLogo = false"
                      @drop.prevent="handleLogoDrop"
                      @click="logoInput.click()"
                    >
                      <img v-if="previewLogo" :src="previewLogo" class="tb-logo-img" />
                      <div v-else class="tb-logo-placeholder">
                        <i class="fa fa-upload tb-logo-icon"></i>
                        <span>{{ $t('templates.logo.uploadHint') }}</span>
                        <small>{{ $t('templates.logo.fileTypes') }}</small>
                      </div>
                      <input type="file" ref="logoInput" class="tb-file-hidden" @change="handleLogoFile" accept="image/*" />
                    </div>
                    <div v-if="previewLogo" class="tb-logo-remove">
                      <button class="tb-link-btn" @click.stop="removeLogo">
                        <i class="fa fa-trash-alt"></i>{{ $t('templates.logo.removeLogo') }}
                      </button>
                    </div>
                  </div>

                  <!-- Accent color -->
                  <div class="tb-setting-block">
                    <div class="tb-setting-label">{{ $t('templates.colors.accent') }}</div>
                    <div class="tb-swatches">
                      <button
                        v-for="p in colorPresets"
                        :key="p.primary"
                        class="tb-swatch"
                        :class="{ 'tb-swatch--active': design.primary === p.primary }"
                        :style="{ background: p.primary }"
                        :title="p.name"
                        @click="applyColorScheme(p.primary)"
                      ></button>
                    </div>
                    <div class="tb-color-custom-row">
                      <input type="color" class="tb-cpicker" v-model="design.primary" @input="applyColorScheme($event.target.value)" />
                      <code class="tb-hex">{{ design.primary }}</code>
                    </div>
                  </div>

                  <!-- Font -->
                  <div class="tb-setting-block">
                    <div class="tb-setting-label">{{ $t('templates.typography.fontFamily') }}</div>
                    <select class="tb-select" v-model="design.font_family">
                      <option value="Arial, sans-serif">Arial</option>
                      <option value="Helvetica, Arial, sans-serif">Helvetica</option>
                      <option value="Georgia, serif">Georgia</option>
                      <option value="'Times New Roman', serif">Times New Roman</option>
                    </select>
                  </div>

                  <!-- Logo size -->
                  <div class="tb-setting-block">
                    <div class="tb-setting-label">{{ $t('templates.logo.widthLabel') }} — <strong>{{ design.logo_width_mm }} mm</strong></div>
                    <input type="range" class="tb-range" min="20" max="120" step="2" v-model.number="design.logo_width_mm" />
                    <div class="tb-range-ends"><span>20 mm</span><span>120 mm</span></div>
                  </div>
                </div><!-- /simple -->

                <!-- ─ ADVANCED TOGGLE ─ -->
                <button class="tb-advanced-toggle" @click="showAdvanced = !showAdvanced">
                  <i class="fa fa-sliders"></i>
                  <span>Advanced settings</span>
                  <i class="fa tb-adv-arrow" :class="showAdvanced ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                </button>

                <!-- ─ ADVANCED SETTINGS ─ -->
                <div v-if="showAdvanced" class="tb-advanced">

                  <!-- Colors accordion -->
                  <div class="tb-acc" :class="{ 'tb-acc--open': openSection === 'colors' }">
                    <button class="tb-acc-head" @click="toggleSection('colors')">
                      <span class="tb-acc-icon"><i class="fa fa-palette"></i></span>
                      <span>{{ $t('templates.sections.colors') }}</span>
                      <i class="fa fa-chevron-down tb-acc-arrow"></i>
                    </button>
                    <div class="tb-acc-body">
                      <div class="tb-color-grid">
                        <div class="tb-color-cell">
                          <label class="tb-color-label">{{ $t('templates.colors.text') }}</label>
                          <div class="tb-color-row">
                            <input type="color" class="tb-cpicker" v-model="design.text" />
                            <code class="tb-hex">{{ design.text }}</code>
                          </div>
                        </div>
                        <div class="tb-color-cell">
                          <label class="tb-color-label">{{ $t('templates.colors.tableHeaderBg') }}</label>
                          <div class="tb-color-row">
                            <input type="color" class="tb-cpicker" v-model="design.table_header_bg" />
                            <code class="tb-hex">{{ design.table_header_bg }}</code>
                          </div>
                        </div>
                        <div class="tb-color-cell">
                          <label class="tb-color-label">{{ $t('templates.colors.tableHeaderText') }}</label>
                          <div class="tb-color-row">
                            <input type="color" class="tb-cpicker" v-model="design.table_header_text" />
                            <code class="tb-hex">{{ design.table_header_text }}</code>
                          </div>
                        </div>
                        <div class="tb-color-cell">
                          <label class="tb-color-label">{{ $t('templates.colors.tableBorder') }}</label>
                          <div class="tb-color-row">
                            <input type="color" class="tb-cpicker" v-model="design.table_border" />
                            <code class="tb-hex">{{ design.table_border }}</code>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Typography accordion -->
                  <div class="tb-acc" :class="{ 'tb-acc--open': openSection === 'typography' }">
                    <button class="tb-acc-head" @click="toggleSection('typography')">
                      <span class="tb-acc-icon"><i class="fa fa-font"></i></span>
                      <span>{{ $t('templates.sections.typography') }}</span>
                      <i class="fa fa-chevron-down tb-acc-arrow"></i>
                    </button>
                    <div class="tb-acc-body">
                      <div class="tb-field">
                        <label class="tb-field-label">{{ $t('templates.typography.fontSize') }}</label>
                        <div class="tb-chips">
                          <button
                            v-for="sz in fontSizes"
                            :key="sz.v"
                            class="tb-chip"
                            :class="{ 'tb-chip--active': design.font_size === sz.v }"
                            @click="design.font_size = sz.v"
                          >{{ sz.l }}</button>
                        </div>
                      </div>
                      <div class="tb-field">
                        <label class="tb-field-label">{{ $t('templates.logo.positionLabel') }}</label>
                        <div class="tb-radio-row">
                          <label class="tb-radio-card" :class="{ 'tb-radio-card--active': design.logo_position === 'left' }">
                            <input type="radio" value="left" v-model="design.logo_position" />
                            <i class="fa fa-align-left"></i><span>{{ $t('templates.logo.positionLeft') }}</span>
                          </label>
                          <label class="tb-radio-card" :class="{ 'tb-radio-card--active': design.logo_position === 'above' }">
                            <input type="radio" value="above" v-model="design.logo_position" />
                            <i class="fa fa-align-center"></i><span>{{ $t('templates.logo.positionAbove') }}</span>
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Client data accordion -->
                  <div class="tb-acc" :class="{ 'tb-acc--open': openSection === 'client' }">
                    <button class="tb-acc-head" @click="toggleSection('client')">
                      <span class="tb-acc-icon"><i class="fa fa-id-card"></i></span>
                      <span>{{ $t('templates.sections.clientData') }}</span>
                      <i class="fa fa-chevron-down tb-acc-arrow"></i>
                    </button>
                    <div class="tb-acc-body">
                      <div class="tb-toggles">
                        <label class="tb-toggle-row">
                          <span class="tb-toggle-lbl">{{ $t('templates.clientData.showCustomerNumber') }}</span>
                          <span class="tb-toggle"><input type="checkbox" v-model="design.show_customer_number" /><span class="tb-toggle-track"></span></span>
                        </label>
                        <label class="tb-toggle-row">
                          <span class="tb-toggle-lbl">{{ $t('templates.clientData.showCustomerPhone') }}</span>
                          <span class="tb-toggle"><input type="checkbox" v-model="design.show_customer_phone" /><span class="tb-toggle-track"></span></span>
                        </label>
                        <label class="tb-toggle-row">
                          <span class="tb-toggle-lbl">{{ $t('templates.clientData.showShippingAddress') }}</span>
                          <span class="tb-toggle"><input type="checkbox" v-model="design.show_shipping_address" /><span class="tb-toggle-track"></span></span>
                        </label>
                        <label class="tb-toggle-row">
                          <span class="tb-toggle-lbl">{{ $t('templates.clientData.billingAddressRight') }}</span>
                          <span class="tb-toggle"><input type="checkbox" v-model="design.billing_address_right" /><span class="tb-toggle-track"></span></span>
                        </label>
                        <label class="tb-toggle-row">
                          <span class="tb-toggle-lbl">{{ $t('templates.clientData.showPaymentTerms') }}</span>
                          <span class="tb-toggle"><input type="checkbox" v-model="design.show_payment_terms" /><span class="tb-toggle-track"></span></span>
                        </label>
                      </div>
                    </div>
                  </div>

                  <!-- Table options accordion -->
                  <div class="tb-acc" :class="{ 'tb-acc--open': openSection === 'table' }">
                    <button class="tb-acc-head" @click="toggleSection('table')">
                      <span class="tb-acc-icon"><i class="fa fa-table"></i></span>
                      <span>{{ $t('templates.sections.itemsTable') }}</span>
                      <i class="fa fa-chevron-down tb-acc-arrow"></i>
                    </button>
                    <div class="tb-acc-body">
                      <div class="tb-toggles">
                        <label class="tb-toggle-row">
                          <span class="tb-toggle-lbl">{{ $t('templates.itemsTable.showDiscount') }}</span>
                          <span class="tb-toggle"><input type="checkbox" v-model="design.show_discount" /><span class="tb-toggle-track"></span></span>
                        </label>
                        <label class="tb-toggle-row">
                          <span class="tb-toggle-lbl">{{ $t('templates.itemsTable.showTaxColumn') }}</span>
                          <span class="tb-toggle"><input type="checkbox" v-model="design.show_tax_column" /><span class="tb-toggle-track"></span></span>
                        </label>
                      </div>
                    </div>
                  </div>

                  <!-- Totals accordion -->
                  <div class="tb-acc" :class="{ 'tb-acc--open': openSection === 'totals' }">
                    <button class="tb-acc-head" @click="toggleSection('totals')">
                      <span class="tb-acc-icon"><i class="fa fa-calculator"></i></span>
                      <span>{{ $t('templates.sections.totals') }}</span>
                      <i class="fa fa-chevron-down tb-acc-arrow"></i>
                    </button>
                    <div class="tb-acc-body">
                      <div class="tb-toggles">
                        <label class="tb-toggle-row">
                          <span class="tb-toggle-lbl">{{ $t('templates.totals.showSubtotal') }}</span>
                          <span class="tb-toggle"><input type="checkbox" v-model="design.show_subtotal" /><span class="tb-toggle-track"></span></span>
                        </label>
                        <label class="tb-toggle-row">
                          <span class="tb-toggle-lbl">{{ $t('templates.totals.showTaxBreakdown') }}</span>
                          <span class="tb-toggle"><input type="checkbox" v-model="design.show_tax_breakdown" /><span class="tb-toggle-track"></span></span>
                        </label>
                        <label class="tb-toggle-row">
                          <span class="tb-toggle-lbl">{{ $t('templates.totals.boldTotal') }}</span>
                          <span class="tb-toggle"><input type="checkbox" v-model="design.bold_total" /><span class="tb-toggle-track"></span></span>
                        </label>
                        <label class="tb-toggle-row">
                          <span class="tb-toggle-lbl">{{ $t('templates.totals.showPaymentNote') }}</span>
                          <span class="tb-toggle"><input type="checkbox" v-model="design.show_payment_note" /><span class="tb-toggle-track"></span></span>
                        </label>
                      </div>
                      <div v-if="design.show_payment_note" class="tb-field" style="margin-top:12px;">
                        <label class="tb-field-label">{{ $t('templates.totals.paymentNoteLabel') }}</label>
                        <textarea
                          class="tb-textarea"
                          rows="3"
                          v-model="design.payment_note"
                          :placeholder="$t('templates.totals.paymentNotePlaceholder')"
                        ></textarea>
                      </div>
                    </div>
                  </div>

                </div><!-- /advanced -->
              </div><!-- /scroll -->
            </aside><!-- /right -->

          </div><!-- /panels -->
        </div><!-- /editor -->
      </transition>

    </div><!-- /tb-root -->
  </div><!-- /content -->
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import axios from "axios";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

// ── Phase & preset state ───────────────────────────────────
// Starts at 'gallery'. loadTemplate() switches to 'editor' if a saved template exists.
const phase          = ref("gallery");
const activePresetId = ref("modern");
const showAdvanced   = ref(false);
const openSection    = ref(null);

const activePresetName = computed(
  () => PRESETS.find((p) => p.id === activePresetId.value)?.name ?? "Custom"
);

function toggleSection(key) {
  openSection.value = openSection.value === key ? null : key;
}

// Detect which preset best matches the current primary color (used after load)
function detectPreset(primaryColor) {
  const match = PRESETS.find((p) => p.design.primary === primaryColor);
  if (match) activePresetId.value = match.id;
  // If no match, leave it as "custom" — activePresetName returns "Custom"
}

// ── Template presets ───────────────────────────────────────
const PRESETS = [
  {
    id: "modern",
    name: "Modern",
    desc: "Bold & brand-forward",
    badge: "Default",
    design: {
      primary: "#E91E63",
      text: "#1f1c1a",
      muted: "#6b6764",
      table_header_bg: "#E91E63",
      table_header_text: "#ffffff",
      table_border: "#f0e8ed",
      font_family: "Arial, sans-serif",
    },
  },
  {
    id: "minimal",
    name: "Minimal",
    desc: "Clean & distraction-free",
    design: {
      primary: "#64748b",
      text: "#1e293b",
      muted: "#64748b",
      table_header_bg: "#f1f5f9",
      table_header_text: "#1e293b",
      table_border: "#e2e8f0",
      font_family: "Helvetica, Arial, sans-serif",
    },
  },
  {
    id: "elegant",
    name: "Elegant",
    desc: "Deep navy, timeless",
    design: {
      primary: "#1e3a5f",
      text: "#1e293b",
      muted: "#64748b",
      table_header_bg: "#1e3a5f",
      table_header_text: "#ffffff",
      table_border: "#dbeafe",
      font_family: "Georgia, serif",
    },
  },
  {
    id: "corporate",
    name: "Corporate",
    desc: "Professional & structured",
    design: {
      primary: "#0f4c81",
      text: "#1e293b",
      muted: "#475569",
      table_header_bg: "#0f4c81",
      table_header_text: "#ffffff",
      table_border: "#e0e7f0",
      font_family: "Arial, sans-serif",
    },
  },
  {
    id: "startup",
    name: "Startup",
    desc: "Modern tech aesthetic",
    design: {
      primary: "#7c3aed",
      text: "#1e1b4b",
      muted: "#6d28d9",
      table_header_bg: "#7c3aed",
      table_header_text: "#ffffff",
      table_border: "#ede9fe",
      font_family: "Helvetica, Arial, sans-serif",
    },
  },
  {
    id: "emerald",
    name: "Emerald",
    desc: "Fresh & sustainable",
    design: {
      primary: "#059669",
      text: "#064e3b",
      muted: "#047857",
      table_header_bg: "#059669",
      table_header_text: "#ffffff",
      table_border: "#d1fae5",
      font_family: "Arial, sans-serif",
    },
  },
  {
    id: "amber",
    name: "Amber",
    desc: "Warm & approachable",
    design: {
      primary: "#d97706",
      text: "#1c1917",
      muted: "#78716c",
      table_header_bg: "#d97706",
      table_header_text: "#ffffff",
      table_border: "#fef3c7",
      font_family: "Arial, sans-serif",
    },
  },
  {
    id: "crimson",
    name: "Crimson",
    desc: "Bold & high-contrast",
    design: {
      primary: "#dc2626",
      text: "#1c1917",
      muted: "#6b7280",
      table_header_bg: "#dc2626",
      table_header_text: "#ffffff",
      table_border: "#fee2e2",
      font_family: "Arial, sans-serif",
    },
  },
];

function selectPreset(preset) {
  activePresetId.value = preset.id;
  Object.keys(preset.design).forEach((k) => { design[k] = preset.design[k]; });
  isColorDark(design.primary)
    ? (design.table_header_text = "#ffffff")
    : (design.table_header_text = "#1f1c1a");
}

// ── Font sizes (reactive for i18n) ─────────────────────────
const fontSizes = computed(() => [
  { v: "small",  l: t("templates.typography.small") },
  { v: "medium", l: t("templates.typography.medium") },
  { v: "large",  l: t("templates.typography.large") },
]);

// ── Color presets ──────────────────────────────────────────
const colorPresets = [
  { primary: "#E91E63", name: "Rosa · Fakturalista" },
  { primary: "#2563eb", name: "Azul profesional" },
  { primary: "#0ea5e9", name: "Celeste" },
  { primary: "#7c3aed", name: "Violeta" },
  { primary: "#059669", name: "Verde esmeralda" },
  { primary: "#d97706", name: "Ámbar" },
  { primary: "#dc2626", name: "Rojo" },
  { primary: "#374151", name: "Grafito" },
];

// ── Logo upload ────────────────────────────────────────────
const logoInput       = ref(null);
const previewLogo     = ref(null);   // data URL (new pick) or HTTP URL (restored from DB)
const logoPendingFile = ref(null);   // raw File waiting to be uploaded on save
const logoPersistPath = ref(null);   // stored relative path (only populated after migration adds logo_path column)
const isDraggingLogo  = ref(false);

function handleLogoDrop(e) {
  isDraggingLogo.value = false;
  const file = e.dataTransfer.files?.[0];
  if (file && file.type.startsWith("image/")) readLogoFile(file);
}
function handleLogoFile(e) {
  const file = e.target.files?.[0];
  if (file) readLogoFile(file);
}
function readLogoFile(file) {
  logoPendingFile.value = file;
  const reader = new FileReader();
  reader.onload = (ev) => { previewLogo.value = ev.target.result; };
  reader.readAsDataURL(file);
}
function removeLogo() {
  previewLogo.value     = null;
  logoPendingFile.value = null;
  logoPersistPath.value = null;
  if (logoInput.value) logoInput.value.value = "";
}

// ── Design state ───────────────────────────────────────────
function defaultDesign() {
  return {
    primary: "#E91E63",
    text: "#1f1c1a",
    muted: "#6b6764",
    table_header_bg: "#E91E63",
    table_header_text: "#ffffff",
    table_border: "#e5e0db",
    font_family: "Arial, sans-serif",
    font_size: "medium",
    logo_width_mm: 50,
    logo_position: "left",
    show_payment_terms: true,
    show_customer_number: false,
    show_customer_phone: false,
    show_shipping_address: false,
    billing_address_right: false,
    show_discount: false,
    show_tax_column: true,
    show_subtotal: true,
    show_tax_breakdown: true,
    bold_total: true,
    payment_note: "",
    show_payment_note: false,
  };
}

const design  = reactive(defaultDesign());
const saving  = ref(false);
const saveMessage = ref("");
const saveError   = ref("");
const existingTemplateId = ref(null);

function resetDesign() {
  const d = defaultDesign();
  Object.keys(d).forEach((k) => { design[k] = d[k]; });
  previewLogo.value     = null;
  logoPendingFile.value = null;
  logoPersistPath.value = null;
}

// ── Computed styles (drive the live preview) ───────────────
// All values mirror the PHP expressions in pdf/document.blade.php exactly.
function fontSizePx(size) {
  if (size === "small") return 13;
  if (size === "large") return 16;
  return 14;
}

// Logo width: 3.78 px/mm matches the PDF renderer (1mm ≈ 3.78px at 96 dpi)
const logoWidthPx = computed(() => Math.min(design.logo_width_mm * 3.78, 280));

const paperBaseStyle = computed(() => ({
  fontFamily: design.font_family,
  fontSize:   `${fontSizePx(design.font_size)}px`,
  color:      design.text,
}));

// Header — left column (company info)
const companyNameStyle = computed(() => ({
  fontFamily:   design.font_family,
  fontSize:     `${fontSizePx(design.font_size) + 2}px`,
  fontWeight:   800,
  color:        design.primary,
  marginBottom: "4px",
}));
const companyDetailStyle = computed(() => ({
  fontFamily: design.font_family,
  fontSize:   `${fontSizePx(design.font_size) - 2}px`,
  color:      design.muted,
  lineHeight: "1.65",
}));

// Header — right column (doc type, reference, meta rows)
const docLabelStyle = computed(() => ({
  fontFamily:    design.font_family,
  fontSize:      "26px",
  fontWeight:    900,
  color:         design.primary,
  letterSpacing: "0.04em",
  textTransform: "uppercase",
  lineHeight:    1,
  marginBottom:  "6px",
}));
const docNumberStyle = computed(() => ({
  fontFamily:   design.font_family,
  fontSize:     `${fontSizePx(design.font_size)}px`,
  fontWeight:   700,
  color:        design.text,
  marginBottom: "12px",
}));
const metaValStyle = computed(() => ({
  fontFamily: design.font_family,
  fontSize:   `${fontSizePx(design.font_size) - 2}px`,
  fontWeight: 600,
  color:      design.text,
}));

// Address section
const addrLabelStyle = computed(() => ({
  fontFamily:    design.font_family,
  fontSize:      `${fontSizePx(design.font_size) - 4}px`,
  fontWeight:    800,
  textTransform: "uppercase",
  letterSpacing: "0.1em",
  color:         "#9ca3af",
  marginBottom:  "5px",
}));
const addrNameStyle = computed(() => ({
  fontFamily: design.font_family,
  fontSize:   `${fontSizePx(design.font_size)}px`,
  fontWeight: 800,
  color:      design.text,
}));
const addrSubStyle = computed(() => ({
  fontFamily: design.font_family,
  fontSize:   `${fontSizePx(design.font_size) - 2}px`,
  color:      design.muted,
  lineHeight: "1.65",
}));

// General text helpers (still used for payment note etc.)
const titleStyle = computed(() => ({
  fontFamily: design.font_family,
  fontWeight: 700,
  fontSize:   `${fontSizePx(design.font_size)}px`,
  color:      design.text,
}));
const textStyle = computed(() => ({
  fontFamily: design.font_family,
  fontSize:   `${fontSizePx(design.font_size)}px`,
  color:      design.text,
}));
const mutedStyle = computed(() => ({
  fontFamily: design.font_family,
  fontSize:   `${fontSizePx(design.font_size) - 2}px`,
  color:      design.muted,
}));

// Items table
const tableStyle = computed(() => ({
  fontFamily:     design.font_family,
  fontSize:       `${fontSizePx(design.font_size)}px`,
  borderColor:    design.table_border,
  width:          "100%",
  borderCollapse: "collapse",
}));
const tableHeadStyle = computed(() => ({
  background: design.table_header_bg,
  color:      design.table_header_text,
}));
const thStyle = computed(() => ({
  borderTop:     `1px solid ${design.table_border}`,
  borderBottom:  `1px solid ${design.table_border}`,
  padding:       "10px",
  fontWeight:    800,
  fontSize:      `${fontSizePx(design.font_size) - 3}px`,
  textTransform: "uppercase",
  letterSpacing: "0.06em",
}));
const tdStyle = computed(() => ({
  borderBottom:  `1px solid ${design.table_border}`,
  padding:       "10px",
  verticalAlign: "top",
  fontSize:      `${fontSizePx(design.font_size) - 2}px`,
  color:         design.text,
}));
const itemNameStyle = computed(() => ({
  fontFamily: design.font_family,
  fontWeight: 700,
  color:      design.text,
}));
const itemDescStyle = computed(() => ({
  fontFamily: design.font_family,
  fontSize:   `${fontSizePx(design.font_size) - 3}px`,
  color:      design.muted,
  marginTop:  "2px",
}));

// Totals — mirrors .totals-table td:first-child / td:last-child / .total-final in PDF CSS
const totalLabelStyle = computed(() => ({
  fontFamily:  design.font_family,
  fontSize:    `${fontSizePx(design.font_size) - 2}px`,
  textAlign:   "right",
  color:       design.muted,
  fontWeight:  600,
  padding:     "5px 24px 5px 10px",
}));
const totalValueStyle = computed(() => ({
  fontFamily: design.font_family,
  fontSize:   `${fontSizePx(design.font_size) - 2}px`,
  textAlign:  "right",
  fontWeight: 600,
  color:      design.text,
  padding:    "5px 10px",
}));
const totalFinalLabelStyle = computed(() => ({
  fontFamily:  design.font_family,
  fontSize:    `${fontSizePx(design.font_size)}px`,
  fontWeight:  design.bold_total ? 800 : 500,
  color:       design.text,
  textAlign:   "right",
  borderTop:   `2px solid ${design.text}`,
  padding:     "8px 24px 5px 10px",
}));
const totalFinalValueStyle = computed(() => ({
  fontFamily: design.font_family,
  fontSize:   `${fontSizePx(design.font_size) + 2}px`,
  fontWeight: 900,
  color:      design.primary,
  textAlign:  "right",
  borderTop:  `2px solid ${design.text}`,
  padding:    "8px 10px 5px",
}));

// ── Sample data ────────────────────────────────────────────
const sample = {
  customerPhone:  "657 985 633",
  customerNumber: "C-001",
  paymentTerms:   "Neto 30 días",
};
const currentDate = new Date().toLocaleDateString("es-ES");

// ── Color helpers ──────────────────────────────────────────
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
  return (0.299 * r + 0.587 * g + 0.114 * b) / 255 < 0.6;
}
function applyColorScheme(color) {
  design.primary          = color;
  design.table_header_bg  = color;
  design.table_border     = color;
  design.table_header_text = isColorDark(color) ? "#ffffff" : "#1f1c1a";
}

// ── Build save payload ─────────────────────────────────────
function buildPayload() {
  // logo_path is intentionally excluded: the column doesn't exist in the DB yet.
  // Approve and run the pending migration in database/migrations/tenant/ to enable logo persistence.
  return {
    name:           activePresetName.value || "Default invoice",
    slug:           "default-invoice",
    version:        1,
    is_default:     true,
    is_active:      true,
    document_type:  "invoice",
    engine:         "dompdf",
    paper_size:     "A4",
    orientation:    "portrait",
    locale:         "es",
    timezone:       "Europe/Madrid",
    ...design,
  };
}

// ── Load template ──────────────────────────────────────────
// On success: switch to editor, restore all design fields + logo, detect preset
async function loadTemplate() {
  try {
    const { data } = await axios.get("/invoice-templates");
    const tpl = data.templates?.[0];
    if (!tpl) return; // No template yet — stay in gallery

    existingTemplateId.value = tpl.id;

    // Restore all design fields from saved template
    Object.keys(defaultDesign()).forEach((key) => {
      if (tpl[key] !== undefined && tpl[key] !== null) {
        design[key] = tpl[key];
      }
    });

    // Restore logo display from persisted URL (requires logo_path migration to be non-null)
    if (tpl.logo_url) {
      previewLogo.value     = tpl.logo_url;
      logoPersistPath.value = tpl.logo_path;
    }

    // Highlight the matching preset in the sidebar
    detectPreset(design.primary);

    // Skip gallery — user has a saved template, go straight to editor
    phase.value = "editor";
  } catch (error) {
    console.error("No se pudo cargar plantilla", error);
  }
}

// ── Upload logo ────────────────────────────────────────────
// Uploads the pending file to the server, updates design.logo_path & previewLogo with the URL
async function uploadLogoIfNeeded() {
  if (!logoPendingFile.value) return; // No new file selected — nothing to do

  const formData = new FormData();
  formData.append("logo", logoPendingFile.value);

  const { data } = await axios.post("/invoice-templates/upload-logo", formData, {
    headers: { "Content-Type": "multipart/form-data" },
  });

  logoPersistPath.value = data.logo_path;
  previewLogo.value     = data.logo_url;
  logoPendingFile.value = null;
}

// ── Save template ──────────────────────────────────────────
async function saveTemplate() {
  saveMessage.value = "";
  saveError.value   = "";
  saving.value      = true;

  try {
    // Step 1: upload logo file first if user picked a new one
    await uploadLogoIfNeeded();

    // Step 2: save all design settings
    const payload = buildPayload();
    let response;
    if (existingTemplateId.value) {
      response = await axios.put(`/invoice-templates/${existingTemplateId.value}`, payload);
    } else {
      response = await axios.post("/invoice-templates", payload);
    }

    const { data } = response;
    saveMessage.value = data.message || t("templates.savedOk");

    // Always sync to the real ID returned by the server (fixes $incrementing bug)
    if (data.template?.id) {
      existingTemplateId.value = data.template.id;
    }

    setTimeout(() => { saveMessage.value = ""; }, 3000);
  } catch (error) {
    saveError.value =
      error.response?.data?.message ||
      error.response?.data?.error   ||
      t("templates.saveFailed");
    setTimeout(() => { saveError.value = ""; }, 4000);
  } finally {
    saving.value = false;
  }
}

onMounted(() => { loadTemplate(); });
</script>

<style scoped>
/* ══════════════════════════════════════════════════════════
   Template Builder — tb-* prefix. All scoped. No global leak.
   ══════════════════════════════════════════════════════════ */

.tb-root {
  min-height: calc(100vh - 80px);
  display: flex;
  flex-direction: column;
}

/* ── Phase transition ── */
.tb-phase-enter-active, .tb-phase-leave-active { transition: opacity 0.2s ease; }
.tb-phase-enter-from, .tb-phase-leave-to { opacity: 0; }

.tb-fade-enter-active, .tb-fade-leave-active { transition: opacity 0.22s; }
.tb-fade-enter-from, .tb-fade-leave-to { opacity: 0; }

/* ════════════════════════════════════════
   GALLERY PHASE
   ════════════════════════════════════════ */

.tb-gallery {
  padding: 32px 0 48px;
  max-width: 1200px;
  margin: 0 auto;
  width: 100%;
}

.tb-gallery-hd {
  margin-bottom: 32px;
}
.tb-gallery-title {
  margin: 0 0 6px;
  font-size: 22px;
  font-weight: 700;
  color: #111827;
}
.tb-gallery-sub {
  margin: 0;
  font-size: 14px;
  color: #6b7280;
}

/* Template grid */
.tb-preset-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 20px;
}

.tb-preset-card {
  position: relative;
  display: flex;
  flex-direction: column;
  background: #fff;
  border: 1.5px solid #e5e7eb;
  border-radius: 14px;
  padding: 0;
  cursor: pointer;
  text-align: left;
  transition: border-color 0.15s, box-shadow 0.15s, transform 0.12s;
  overflow: hidden;
}
.tb-preset-card:hover {
  border-color: #E91E63;
  box-shadow: 0 6px 24px rgba(233,30,99,0.10), 0 1px 4px rgba(0,0,0,0.06);
  transform: translateY(-2px);
}
.tb-preset-card--active {
  border-color: #E91E63;
  box-shadow: 0 0 0 3px rgba(233,30,99,0.15);
}

/* Mini doc thumbnail */
.tb-mini-doc {
  width: 100%;
  background: #f8f9fa;
  padding: 12px 12px 8px;
  overflow: hidden;
}
.tb-mini-accent {
  height: 4px;
  border-radius: 2px;
  margin-bottom: 10px;
}
.tb-mini-body {
  background: #fff;
  border-radius: 6px;
  padding: 10px;
  box-shadow: 0 1px 6px rgba(0,0,0,0.08);
  min-height: 130px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.tb-mini-header-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
}
.tb-mini-logo-block {
  width: 32px;
  height: 18px;
  background: #e5e7eb;
  border-radius: 3px;
  flex-shrink: 0;
}
.tb-mini-ref {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
}
.tb-mini-line {
  border-radius: 3px;
  background: #d1d5db;
  height: 5px;
}
.tb-mini-line--title { width: 70%; height: 7px; }
.tb-mini-line--sub   { width: 50%; background: #e5e7eb; }
.tb-mini-line--sm    { width: 55%; height: 4px; }
.tb-mini-line--md    { width: 70%; height: 4px; margin-top: 2px; }

.tb-mini-addr-row { padding-bottom: 4px; }
.tb-mini-addr-block { display: flex; flex-direction: column; gap: 2px; }

.tb-mini-table { display: flex; flex-direction: column; gap: 0; }
.tb-mini-thead {
  display: flex;
  gap: 3px;
  padding: 4px 5px;
  border-radius: 3px 3px 0 0;
}
.tb-mini-th {
  flex: 1;
  height: 4px;
  background: rgba(255,255,255,0.5);
  border-radius: 2px;
}
.tb-mini-th--wide { flex: 2.5; }
.tb-mini-trow {
  display: flex;
  gap: 3px;
  padding: 4px 5px;
  border-bottom: 1px solid #f0f0f0;
}
.tb-mini-td {
  flex: 1;
  height: 4px;
  background: #e5e7eb;
  border-radius: 2px;
}
.tb-mini-td--wide { flex: 2.5; }

.tb-mini-total-row {
  display: flex;
  justify-content: flex-end;
  padding-top: 4px;
}
.tb-mini-total-label {
  width: 40%;
  height: 5px;
  border-radius: 3px;
  opacity: 0.9;
}

/* Card meta */
.tb-preset-meta {
  padding: 12px 14px 14px;
  display: flex;
  flex-direction: column;
  gap: 3px;
  flex: 1;
}
.tb-preset-badge {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #E91E63;
  background: #fdf2f8;
  border-radius: 20px;
  padding: 2px 8px;
  display: inline-block;
  align-self: flex-start;
  margin-bottom: 2px;
}
.tb-preset-name {
  font-size: 14px;
  font-weight: 700;
  color: #111827;
}
.tb-preset-desc {
  font-size: 12px;
  color: #6b7280;
}

.tb-preset-check {
  position: absolute;
  top: 10px;
  right: 10px;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: #E91E63;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  box-shadow: 0 2px 6px rgba(233,30,99,0.35);
}

/* ════════════════════════════════════════
   EDITOR PHASE
   ════════════════════════════════════════ */

.tb-editor {
  display: flex;
  flex-direction: column;
  flex: 1;
}

/* Top bar */
.tb-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 0 10px;
  border-bottom: 1px solid #f0f0f0;
  gap: 12px;
  flex-wrap: wrap;
}
.tb-topbar-back {
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
}
.tb-topbar-back:hover { background: #f9fafb; border-color: #d1d5db; }

.tb-topbar-mid {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  justify-content: center;
}
.tb-topbar-preset-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  flex-shrink: 0;
}
.tb-topbar-preset-name {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
}

.tb-topbar-right {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

/* Feedback pills */
.tb-feedback {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 13px;
  font-weight: 500;
  padding: 5px 10px;
  border-radius: 20px;
}
.tb-feedback--ok  { background: #f0fdf4; color: #166534; }
.tb-feedback--err { background: #fff1f2; color: #9f1239; }

/* Buttons */
.tb-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.14s;
  white-space: nowrap;
}
.tb-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.tb-btn--primary { background: #E91E63; color: #fff; border-color: #E91E63; }
.tb-btn--primary:hover:not(:disabled) { background: #c2185b; border-color: #c2185b; }
.tb-btn--ghost { background: #fff; color: #374151; border-color: #e5e7eb; }
.tb-btn--ghost:hover:not(:disabled) { background: #f9fafb; border-color: #d1d5db; }

/* 3-panel layout */
.tb-panels {
  display: flex;
  flex: 1;
  align-items: flex-start;
  gap: 0;
  min-height: 0;
}

/* ── LEFT panel (template switcher) ── */
.tb-panel-left {
  width: 190px;
  flex-shrink: 0;
  border-right: 1px solid #f0f0f0;
  background: #fafafa;
  position: sticky;
  top: 0;
  align-self: flex-start;
  max-height: calc(100vh - 130px);
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: #e5e7eb transparent;
}
.tb-panel-left::-webkit-scrollbar { width: 3px; }
.tb-panel-left::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 3px; }

.tb-panel-left-head {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #9ca3af;
  padding: 14px 14px 8px;
}

.tb-template-list {
  display: flex;
  flex-direction: column;
  padding: 0 8px 12px;
  gap: 2px;
}

.tb-tpl-thumb {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 8px 8px;
  border-radius: 8px;
  background: none;
  border: none;
  cursor: pointer;
  text-align: left;
  transition: background 0.12s;
  width: 100%;
}
.tb-tpl-thumb:hover { background: #f0f0f0; }
.tb-tpl-thumb--active { background: #fdf2f8; }

.tb-tpl-swatch {
  width: 24px;
  height: 24px;
  border-radius: 6px;
  flex-shrink: 0;
}
.tb-tpl-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.tb-tpl-name {
  font-size: 12px;
  font-weight: 600;
  color: #374151;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.tb-tpl-desc {
  font-size: 10px;
  color: #9ca3af;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.tb-tpl-check {
  font-size: 10px;
  color: #E91E63;
  flex-shrink: 0;
}

/* ── CENTER panel (live preview) ── */
.tb-panel-center {
  flex: 1;
  min-width: 0;
  padding: 20px 20px;
  position: sticky;
  top: 0;
  align-self: flex-start;
  max-height: calc(100vh - 130px);
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: #e5e7eb transparent;
}

.tb-preview-label {
  font-size: 11px;
  font-weight: 600;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.tb-preview-stage {
  background: #e8eaed;
  border-radius: 12px;
  padding: 24px 20px;
  display: flex;
  justify-content: center;
  min-height: 580px;
}

.tb-paper {
  background: #fff;
  width: 100%;
  max-width: 560px;
  border-radius: 2px;
  box-shadow: 0 4px 24px rgba(0,0,0,0.14), 0 1px 4px rgba(0,0,0,0.06);
  padding: 36px 32px 40px; /* matches PDF body padding exactly */
  min-height: 520px;
}

/* ── Invoice preview elements — table-based layout matching pdf/document.blade.php ── */

/* Header */
.tb-header-table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
.tb-header-table td { vertical-align: top; padding: 0; }
.tb-col-company { width: 55%; padding-right: 24px; }
.tb-col-meta { width: 45%; text-align: right; }
.tb-logo-above-cell { text-align: center; padding-bottom: 14px; }
.tb-inv-logo { max-width: 100%; max-height: 64px; object-fit: contain; display: block; margin-bottom: 10px; }

/* Divider */
.tb-inv-divider { border: none; margin: 18px 0; }

/* Meta rows (right column) — display:table matches DomPDF rendering model */
.tb-meta-row { display: table; width: 100%; margin-bottom: 3px; }
.tb-meta-key { display: table-cell; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #9ca3af; width: 50%; text-align: left; }
.tb-meta-val { display: table-cell; text-align: right; }

/* Addresses */
.tb-addrs-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
.tb-addrs-table td { vertical-align: top; padding: 0; }
.tb-addr-label { margin-bottom: 5px; }

/* Items table */
.tb-inv-table { margin-top: 20px; border-collapse: collapse; width: 100%; }
.tb-th-main   { width: 40%; text-align: left; }
.tb-th-center { text-align: center; }
.tb-th-right  { text-align: right; }
.tb-td-center { text-align: center; }
.tb-td-right  { text-align: right; }

/* Totals — 50% spacer + 50% table (matches PDF's DomPDF-safe approach in _totals.blade.php) */
.tb-totals-outer  { width: 100%; border-collapse: collapse; margin-top: 8px; }
.tb-totals-spacer { width: 50%; }
.tb-totals-data   { width: 50%; vertical-align: top; }
.tb-totals-inner  { width: 100%; border-collapse: collapse; }

/* Payment note */
.tb-inv-note { margin-top: 28px; padding-top: 14px; border-top: 1px solid #f0f0f0; }

/* ── RIGHT panel (settings) ── */
.tb-panel-right {
  width: 300px;
  flex-shrink: 0;
  border-left: 1px solid #f0f0f0;
  background: #fff;
  position: sticky;
  top: 0;
  align-self: flex-start;
  max-height: calc(100vh - 130px);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.tb-settings-scroll {
  flex: 1;
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: #e5e7eb transparent;
}
.tb-settings-scroll::-webkit-scrollbar { width: 3px; }
.tb-settings-scroll::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 3px; }

/* Simple settings */
.tb-simple { padding: 16px; display: flex; flex-direction: column; gap: 0; }

.tb-setting-block {
  padding: 14px 0;
  border-bottom: 1px solid #f5f5f5;
}
.tb-setting-block:last-child { border-bottom: none; }

.tb-setting-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #374151;
  margin-bottom: 10px;
}

/* Logo drop */
.tb-logo-drop {
  border: 2px dashed #e5e7eb;
  border-radius: 10px;
  padding: 16px;
  text-align: center;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
  background: #fafafa;
  min-height: 76px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.tb-logo-drop:hover, .tb-logo-drop--over { border-color: #E91E63; background: #fdf2f8; }
.tb-logo-drop--has { border-style: solid; border-color: #e5e7eb; background: #fff; padding: 10px; }
.tb-logo-img { max-width: 100%; max-height: 60px; object-fit: contain; }
.tb-logo-placeholder { display: flex; flex-direction: column; align-items: center; gap: 4px; color: #9ca3af; }
.tb-logo-placeholder span { font-size: 12px; font-weight: 500; color: #6b7280; }
.tb-logo-placeholder small { font-size: 10px; color: #9ca3af; }
.tb-logo-icon { font-size: 18px; color: #d1d5db; }
.tb-file-hidden { display: none; }
.tb-logo-remove { text-align: right; margin-top: 6px; }
.tb-link-btn {
  background: none;
  border: none;
  color: #be123c;
  font-size: 12px;
  cursor: pointer;
  padding: 0;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.tb-link-btn:hover { text-decoration: underline; }

/* Color swatches */
.tb-swatches { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; }
.tb-swatch {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: 2px solid transparent;
  cursor: pointer;
  transition: transform 0.12s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}
.tb-swatch:hover { transform: scale(1.2); }
.tb-swatch--active { border-color: #fff; outline: 2px solid #374151; outline-offset: 1px; }

.tb-color-custom-row { display: flex; align-items: center; gap: 8px; }

/* Select */
.tb-select {
  width: 100%;
  padding: 7px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 7px;
  font-size: 13px;
  color: #374151;
  background: #fff;
  cursor: pointer;
  appearance: auto;
}
.tb-select:focus { outline: none; border-color: #E91E63; box-shadow: 0 0 0 3px rgba(233,30,99,0.08); }

/* Range */
.tb-range { width: 100%; accent-color: #E91E63; margin: 4px 0; }
.tb-range-ends { display: flex; justify-content: space-between; font-size: 10px; color: #9ca3af; margin-top: 2px; }

/* Color inputs */
.tb-cpicker {
  width: 34px;
  height: 30px;
  border: 1px solid #e5e7eb;
  border-radius: 7px;
  padding: 2px;
  cursor: pointer;
  background: none;
}
.tb-hex {
  font-size: 11px;
  color: #374151;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 5px;
  padding: 3px 7px;
  letter-spacing: 0.02em;
}

/* Advanced toggle */
.tb-advanced-toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 12px 16px;
  background: #f9fafb;
  border: none;
  border-top: 1px solid #f0f0f0;
  border-bottom: 1px solid #f0f0f0;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  cursor: pointer;
  text-align: left;
  transition: background 0.13s;
}
.tb-advanced-toggle:hover { background: #f3f4f6; }
.tb-adv-arrow { margin-left: auto; font-size: 11px; color: #9ca3af; }

/* Advanced section */
.tb-advanced { display: flex; flex-direction: column; }

/* Accordion */
.tb-acc { border-bottom: 1px solid #f0f0f0; }
.tb-acc-head {
  display: flex;
  align-items: center;
  gap: 9px;
  width: 100%;
  padding: 12px 16px;
  background: none;
  border: none;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  cursor: pointer;
  text-align: left;
  transition: background 0.12s;
}
.tb-acc-head:hover { background: #f9fafb; }
.tb-acc-icon {
  width: 24px;
  height: 24px;
  border-radius: 6px;
  background: #fdf2f8;
  color: #E91E63;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  flex-shrink: 0;
}
.tb-acc-arrow { margin-left: auto; font-size: 11px; color: #9ca3af; transition: transform 0.18s; }
.tb-acc--open .tb-acc-arrow { transform: rotate(180deg); }
.tb-acc-body { display: none; padding: 12px 16px 16px; }
.tb-acc--open .tb-acc-body { display: block; }

/* Shared field/form elements inside accordions */
.tb-field { margin-bottom: 12px; }
.tb-field:last-child { margin-bottom: 0; }
.tb-field-label {
  display: block;
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 6px;
}

.tb-chips { display: flex; gap: 5px; }
.tb-chip {
  flex: 1;
  padding: 6px 8px;
  border: 1px solid #e5e7eb;
  border-radius: 7px;
  font-size: 12px;
  font-weight: 500;
  color: #374151;
  background: #fff;
  cursor: pointer;
  transition: all 0.12s;
  text-align: center;
}
.tb-chip:hover { background: #f9fafb; }
.tb-chip--active { background: #fdf2f8; border-color: #E91E63; color: #E91E63; font-weight: 600; }

.tb-radio-row { display: flex; gap: 7px; }
.tb-radio-card {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 7px 10px;
  font-size: 12px;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
  transition: all 0.12s;
}
.tb-radio-card input[type="radio"] { display: none; }
.tb-radio-card:hover { background: #f9fafb; }
.tb-radio-card--active { border-color: #E91E63; background: #fdf2f8; color: #E91E63; font-weight: 600; }

.tb-color-grid { display: flex; flex-direction: column; gap: 10px; }
.tb-color-cell {}
.tb-color-label {
  display: block;
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 5px;
}
.tb-color-row { display: flex; align-items: center; gap: 8px; }

/* Toggles */
.tb-toggles { display: flex; flex-direction: column; gap: 0; }
.tb-toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 9px 0;
  border-bottom: 1px solid #f5f5f5;
  cursor: pointer;
  user-select: none;
}
.tb-toggle-row:last-child { border-bottom: none; }
.tb-toggle-lbl { font-size: 13px; color: #374151; }
.tb-toggle {
  position: relative;
  display: inline-block;
  width: 36px;
  height: 20px;
  flex-shrink: 0;
}
.tb-toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
.tb-toggle-track {
  position: absolute;
  inset: 0;
  background: #e5e7eb;
  border-radius: 20px;
  transition: background 0.2s;
  cursor: pointer;
}
.tb-toggle-track::after {
  content: '';
  position: absolute;
  left: 2px;
  top: 2px;
  width: 16px;
  height: 16px;
  background: #fff;
  border-radius: 50%;
  transition: transform 0.2s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.tb-toggle input:checked + .tb-toggle-track { background: #E91E63; }
.tb-toggle input:checked + .tb-toggle-track::after { transform: translateX(16px); }

.tb-textarea {
  width: 100%;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 13px;
  font-family: inherit;
  color: #374151;
  resize: vertical;
  transition: border-color 0.15s;
}
.tb-textarea:focus { outline: none; border-color: #E91E63; box-shadow: 0 0 0 3px rgba(233,30,99,0.08); }

/* ── Responsive ── */
@media (max-width: 1024px) {
  .tb-panel-left { display: none; }
}
@media (max-width: 768px) {
  .tb-panels { flex-direction: column; }
  .tb-panel-center, .tb-panel-right {
    width: 100%;
    max-height: none;
    position: static;
  }
  .tb-panel-right { border-left: none; border-top: 1px solid #f0f0f0; }
  .tb-preset-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 14px; }
}
</style>
