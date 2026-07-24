<template>
  <div class="content settings-page">

    <!-- ── Page header ──────────────────────────────────────────── -->
    <div class="sp-header">
      <div>
        <h1 class="sp-title">{{ $t('settings.pageTitle') }}</h1>
        <p class="sp-sub">{{ $t('settings.pageSubtitle') }}</p>
      </div>
    </div>

    <!-- ── Loading ─────────────────────────────────────────────── -->
    <div v-if="isLoading" class="sp-loading">
      <span class="sp-loading-dot"></span>
      <span class="sp-loading-dot"></span>
      <span class="sp-loading-dot"></span>
    </div>

    <form v-else @submit.prevent="saveAll" novalidate>
      <div class="sp-layout">

        <!-- ════════════════════════════════════════════
             Sidebar
        ════════════════════════════════════════════ -->
        <aside class="sp-sidebar">
          <nav class="sp-nav">

            <div class="sp-nav-group">{{ $t('settings.navGroups.business') }}</div>
            <button
              v-for="s in businessSections"
              :key="s.id"
              type="button"
              class="sp-nav-btn"
              :class="{ 'sp-nav-btn--active': activeSection === s.id }"
              @click="scrollTo(s.id)"
            >
              <i :class="['sp-nav-ico', s.icon]"></i>
              <span>{{ s.label }}</span>
            </button>

            <div class="sp-nav-group">{{ $t('settings.navGroups.billing') }}</div>
            <button
              v-for="s in billingSections"
              :key="s.id"
              type="button"
              class="sp-nav-btn"
              :class="{ 'sp-nav-btn--active': activeSection === s.id }"
              @click="scrollTo(s.id)"
            >
              <i :class="['sp-nav-ico', s.icon]"></i>
              <span>{{ s.label }}</span>
            </button>

            <div class="sp-nav-group">{{ $t('settings.navGroups.design') }}</div>
            <button
              v-for="s in designSections"
              :key="s.id"
              type="button"
              class="sp-nav-btn"
              :class="{ 'sp-nav-btn--active': activeSection === s.id }"
              @click="scrollTo(s.id)"
            >
              <i :class="['sp-nav-ico', s.icon]"></i>
              <span>{{ s.label }}</span>
            </button>

            <div class="sp-nav-group">{{ $t('settings.navGroups.account') }}</div>
            <button
              v-for="s in accountSections"
              :key="s.id"
              type="button"
              class="sp-nav-btn"
              :class="{ 'sp-nav-btn--active': activeSection === s.id }"
              @click="scrollTo(s.id)"
            >
              <i :class="['sp-nav-ico', s.icon]"></i>
              <span>{{ s.label }}</span>
            </button>

          </nav>
        </aside>

        <!-- ════════════════════════════════════════════
             Main content
        ════════════════════════════════════════════ -->
        <div class="sp-main">

          <!-- ═══ Company Profile ═══════════════════════════════ -->
          <section id="company" class="sc">
            <div class="sc-head">
              <span class="sc-tag">{{ $t('settings.companyProfile.tag') }}</span>
              <h2 class="sc-title">{{ $t('settings.companyProfile.title') }}</h2>
              <p class="sc-desc">{{ $t('settings.companyProfile.desc') }}</p>
            </div>

            <!-- Logo -->
            <div class="sr">
              <div class="sr-lbl">
                <span class="sr-name">{{ $t('settings.companyProfile.logoName') }}</span>
                <span class="sr-hint">{{ $t('settings.companyProfile.logoHint') }}</span>
              </div>
              <div class="sr-inp">
                <div class="logo-area">
                  <div class="logo-box" @click="$refs.logoInput.click()" title="Click to upload">
                    <img v-if="logoPreview" :src="logoPreview" alt="Logo" class="logo-img" />
                    <div v-else class="logo-empty">
                      <i class="fa fa-image"></i>
                    </div>
                  </div>
                  <div class="logo-btns">
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="$refs.logoInput.click()" :disabled="isSaving">
                      <i class="fa fa-upload me-1"></i>{{ $t('settings.upload') }}
                    </button>
                    <button v-if="logoPreview" type="button" class="btn btn-sm btn-outline-secondary" @click="clearLogo" :disabled="isSaving">
                      {{ $t('settings.remove') }}
                    </button>
                    <input ref="logoInput" type="file" accept="image/*" class="d-none" @change="onFileChange($event, 'logo')" />
                    <span class="sr-hint mt-1">{{ $t('settings.companyProfile.logoFileHint') }}</span>
                  </div>
                </div>
                <div v-if="errors.logo" class="sc-error">{{ errors.logo[0] }}</div>
              </div>
            </div>

            <div class="sc-line"></div>

            <!-- Legal name -->
            <div class="sr">
              <div class="sr-lbl">
                <label for="legal_name" class="sr-name">{{ $t('settings.companyProfile.legalName') }} <span class="sr-req">*</span></label>
                <span class="sr-hint">{{ $t('settings.companyProfile.legalNameHint') }}</span>
              </div>
              <div class="sr-inp">
                <input id="legal_name" type="text" class="form-control" :class="{ 'is-invalid': errors.legal_name }" v-model="form.legal_name" :disabled="isSaving" placeholder="Acme Solutions S.L." required />
                <div v-if="errors.legal_name" class="invalid-feedback">{{ errors.legal_name[0] }}</div>
              </div>
            </div>

            <div class="sc-line"></div>

            <!-- Trade name -->
            <div class="sr">
              <div class="sr-lbl">
                <label for="trade_name" class="sr-name">{{ $t('settings.companyProfile.tradeName') }}</label>
                <span class="sr-hint">{{ $t('settings.companyProfile.tradeNameHint') }}</span>
              </div>
              <div class="sr-inp">
                <input id="trade_name" type="text" class="form-control" v-model="form.trade_name" :disabled="isSaving" placeholder="Acme" />
              </div>
            </div>

            <div class="sc-line"></div>

            <!-- Industry + country -->
            <div class="sr">
              <div class="sr-lbl">
                <label class="sr-name">{{ $t('settings.companyProfile.industryCountry') }}</label>
                <span class="sr-hint">{{ $t('settings.companyProfile.industryCountryHint') }}</span>
              </div>
              <div class="sr-inp">
                <div class="row g-2">
                  <div class="col-8">
                    <input type="text" class="form-control" v-model="form.industry" :disabled="isSaving" placeholder="Software, Consulting, Retail…" />
                  </div>
                  <div class="col-4">
                    <input type="text" class="form-control" :class="{ 'is-invalid': errors.country_code }" v-model="form.country_code" :disabled="isSaving" maxlength="2" placeholder="ES" />
                    <div v-if="errors.country_code" class="invalid-feedback">{{ errors.country_code[0] }}</div>
                  </div>
                </div>
                <p class="sc-ftext">{{ $t('settings.companyProfile.isoHint') }}</p>
              </div>
            </div>
          </section>

          <!-- ═══ Contact & Address ══════════════════════════════ -->
          <section id="contact" class="sc">
            <div class="sc-head">
              <span class="sc-tag">{{ $t('settings.contact.tag') }}</span>
              <h2 class="sc-title">{{ $t('settings.contact.title') }}</h2>
              <p class="sc-desc">{{ $t('settings.contact.desc') }}</p>
            </div>

            <div class="sr">
              <div class="sr-lbl">
                <span class="sr-name">{{ $t('settings.contact.detailsName') }}</span>
              </div>
              <div class="sr-inp">
                <div class="sr-stack">
                  <div>
                    <label class="sc-ftext mb-1">{{ $t('settings.contact.email') }}</label>
                    <input type="email" class="form-control" :class="{ 'is-invalid': errors.email }" v-model="form.email" :disabled="isSaving" placeholder="company@example.com" />
                    <div v-if="errors.email" class="invalid-feedback">{{ errors.email[0] }}</div>
                  </div>
                  <div>
                    <label class="sc-ftext mb-1">{{ $t('settings.contact.phone') }}</label>
                    <input type="text" class="form-control" v-model="form.phone" :disabled="isSaving" placeholder="+34 600 000 000" />
                  </div>
                  <div>
                    <label class="sc-ftext mb-1">{{ $t('settings.contact.website') }}</label>
                    <input type="text" class="form-control" v-model="form.website" :disabled="isSaving" placeholder="https://example.com" />
                  </div>
                </div>
              </div>
            </div>

            <div class="sc-line"></div>

            <div class="sr">
              <div class="sr-lbl">
                <span class="sr-name">{{ $t('settings.contact.addressName') }}</span>
                <span class="sr-hint">{{ $t('settings.contact.addressHint') }}</span>
              </div>
              <div class="sr-inp">
                <div class="sr-stack">
                  <div>
                    <label class="sc-ftext mb-1">{{ $t('settings.contact.addressLine1') }}</label>
                    <input type="text" class="form-control" v-model="form.address_line1" :disabled="isSaving" :placeholder="$t('settings.contact.streetPlaceholder')" />
                  </div>
                  <div>
                    <label class="sc-ftext mb-1">{{ $t('settings.contact.addressLine2') }}</label>
                    <input type="text" class="form-control" v-model="form.address_line2" :disabled="isSaving" :placeholder="$t('settings.contact.floorPlaceholder')" />
                  </div>
                  <div class="row g-2">
                    <div class="col-6">
                      <label class="sc-ftext mb-1">{{ $t('settings.contact.city') }}</label>
                      <input type="text" class="form-control" v-model="form.city" :disabled="isSaving" placeholder="Barcelona" />
                    </div>
                    <div class="col-6">
                      <label class="sc-ftext mb-1">{{ $t('settings.contact.state') }}</label>
                      <input type="text" class="form-control" v-model="form.state" :disabled="isSaving" placeholder="Catalonia" />
                    </div>
                    <div class="col-5">
                      <label class="sc-ftext mb-1">{{ $t('settings.contact.postalCode') }}</label>
                      <input type="text" class="form-control" v-model="form.postal_code" :disabled="isSaving" placeholder="08001" />
                    </div>
                    <div class="col-7">
                      <label class="sc-ftext mb-1">{{ $t('settings.contact.country') }}</label>
                      <input type="text" class="form-control" v-model="form.country" :disabled="isSaving" placeholder="Spain" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- ═══ Tax & Legal ════════════════════════════════════ -->
          <section id="tax" class="sc">
            <div class="sc-head">
              <span class="sc-tag">{{ $t('settings.tax.tag') }}</span>
              <h2 class="sc-title">{{ $t('settings.tax.title') }}</h2>
              <p class="sc-desc">{{ $t('settings.tax.desc') }}</p>
            </div>

            <div class="sr">
              <div class="sr-lbl">
                <label for="tax_id" class="sr-name">{{ $t('settings.tax.nifName') }}</label>
                <span class="sr-hint">{{ $t('settings.tax.nifHint') }}</span>
              </div>
              <div class="sr-inp">
                <input id="tax_id" type="text" class="form-control" v-model="form.tax_id" :disabled="isSaving" placeholder="B12345678" />
              </div>
            </div>

            <div class="sc-line"></div>

            <div class="sr">
              <div class="sr-lbl">
                <label for="vat_number" class="sr-name">{{ $t('settings.tax.vatName') }}</label>
                <span class="sr-hint">{{ $t('settings.tax.vatHint') }}</span>
              </div>
              <div class="sr-inp">
                <input id="vat_number" type="text" class="form-control" v-model="form.vat_number" :disabled="isSaving" placeholder="ES00000000" />
              </div>
            </div>

            <div class="sc-line"></div>

            <div class="sr">
              <div class="sr-lbl">
                <label for="registration_number" class="sr-name">{{ $t('settings.tax.regName') }}</label>
                <span class="sr-hint">{{ $t('settings.tax.regHint') }}</span>
              </div>
              <div class="sr-inp">
                <input id="registration_number" type="text" class="form-control" v-model="form.registration_number" :disabled="isSaving" placeholder="RM Madrid T-12345" />
              </div>
            </div>
          </section>

          <!-- ═══ Invoice Settings ═══════════════════════════════ -->
          <section id="invoicing" class="sc">
            <div class="sc-head">
              <span class="sc-tag">{{ $t('settings.invoicing.tag') }}</span>
              <h2 class="sc-title">{{ $t('settings.invoicing.title') }}</h2>
              <p class="sc-desc">{{ $t('settings.invoicing.desc') }}</p>
            </div>

            <!-- Numbering -->
            <div class="sr">
              <div class="sr-lbl">
                <span class="sr-name">{{ $t('settings.invoicing.numberingName') }}</span>
                <span class="sr-hint">{{ $t('settings.invoicing.numberingHint') }}</span>
              </div>
              <div class="sr-inp">
                <div class="row g-2 mb-2">
                  <div class="col-4">
                    <label class="sc-ftext mb-1">{{ $t('settings.invoicing.prefix') }} <span class="sr-req">*</span></label>
                    <input type="text" class="form-control" :class="{ 'is-invalid': errors.invoice_prefix }" v-model="form.invoice_prefix" :disabled="isSaving" maxlength="20" placeholder="INV" required />
                    <div v-if="errors.invoice_prefix" class="invalid-feedback">{{ errors.invoice_prefix[0] }}</div>
                  </div>
                  <div class="col-8">
                    <label class="sc-ftext mb-1">{{ $t('settings.invoicing.format') }} <span class="sr-req">*</span></label>
                    <input type="text" class="form-control" :class="{ 'is-invalid': errors.invoice_number_format }" v-model="form.invoice_number_format" :disabled="isSaving" placeholder="{PREFIX}-{YYYY}-{NUMBER}" required />
                    <div v-if="errors.invoice_number_format" class="invalid-feedback">{{ errors.invoice_number_format[0] }}</div>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="sc-ftext mb-1">{{ $t('settings.invoicing.nextNumber') }} <span class="sr-req">*</span></label>
                  <input type="number" class="form-control" style="max-width:170px;" :class="{ 'is-invalid': errors.invoice_next_number }" v-model.number="form.invoice_next_number" :disabled="isSaving" min="1" required />
                  <div v-if="errors.invoice_next_number" class="invalid-feedback">{{ errors.invoice_next_number[0] }}</div>
                </div>
                <div class="num-pill">
                  <span class="sc-ftext">{{ $t('settings.invoicing.preview') }}</span>
                  <code>{{ invoiceNumberPreview }}</code>
                </div>
                <p class="sc-ftext mt-2">{{ $t('settings.invoicing.variablesHint') }} <code>{PREFIX}</code> <code>{YYYY}</code> <code>{MM}</code> <code>{NUMBER}</code></p>
              </div>
            </div>

            <div class="sc-line"></div>

            <!-- Currency & locale -->
            <div class="sr">
              <div class="sr-lbl">
                <span class="sr-name">{{ $t('settings.invoicing.currencyLocaleName') }}</span>
                <span class="sr-hint">{{ $t('settings.invoicing.currencyLocaleHint') }}</span>
              </div>
              <div class="sr-inp">
                <div class="row g-2">
                  <div class="col-4">
                    <label class="sc-ftext mb-1">{{ $t('settings.invoicing.currency') }}</label>
                    <input type="text" class="form-control" v-model="form.currency" :disabled="isSaving" maxlength="3" placeholder="EUR" />
                  </div>
                  <div class="col-4">
                    <label class="sc-ftext mb-1">{{ $t('settings.invoicing.locale') }}</label>
                    <input type="text" class="form-control" v-model="form.locale" :disabled="isSaving" placeholder="es" />
                  </div>
                  <div class="col-4">
                    <label class="sc-ftext mb-1">{{ $t('settings.invoicing.timezone') }}</label>
                    <input type="text" class="form-control" v-model="form.timezone" :disabled="isSaving" placeholder="Europe/Madrid" />
                  </div>
                </div>
              </div>
            </div>

            <div class="sc-line"></div>

            <!-- Footer note -->
            <div class="sr">
              <div class="sr-lbl">
                <label for="invoice_footer_note" class="sr-name">{{ $t('settings.invoicing.footerNoteName') }}</label>
                <span class="sr-hint">{{ $t('settings.invoicing.footerNoteHint') }}</span>
              </div>
              <div class="sr-inp">
                <textarea id="invoice_footer_note" class="form-control" rows="3" v-model="form.invoice_footer_note" :disabled="isSaving" placeholder="Thank you for your business. Payment due within 30 days." maxlength="1000"></textarea>
                <div class="d-flex justify-content-end">
                  <span class="sc-ftext">{{ (form.invoice_footer_note || '').length }} / 1000</span>
                </div>
              </div>
            </div>
          </section>

          <!-- ═══ Banking ════════════════════════════════════════ -->
          <section id="banking" class="sc">
            <div class="sc-head">
              <span class="sc-tag">{{ $t('settings.banking.tag') }}</span>
              <h2 class="sc-title">{{ $t('settings.banking.title') }}</h2>
              <p class="sc-desc">{{ $t('settings.banking.desc') }}</p>
            </div>

            <div class="sr">
              <div class="sr-lbl">
                <label for="bank_name" class="sr-name">{{ $t('settings.banking.bankName') }}</label>
              </div>
              <div class="sr-inp">
                <input id="bank_name" type="text" class="form-control" v-model="form.bank_name" :disabled="isSaving" placeholder="Santander" />
              </div>
            </div>

            <div class="sc-line"></div>

            <div class="sr">
              <div class="sr-lbl">
                <label for="iban" class="sr-name">{{ $t('settings.banking.iban') }}</label>
              </div>
              <div class="sr-inp">
                <input id="iban" type="text" class="form-control" :class="{ 'is-invalid': errors.iban }" v-model="form.iban" :disabled="isSaving" placeholder="ES00 0000 0000 0000 0000 0000" />
                <div v-if="errors.iban" class="invalid-feedback">{{ errors.iban[0] }}</div>
              </div>
            </div>

            <div class="sc-line"></div>

            <div class="sr">
              <div class="sr-lbl">
                <label for="swift" class="sr-name">{{ $t('settings.banking.swift') }}</label>
              </div>
              <div class="sr-inp">
                <input id="swift" type="text" class="form-control" v-model="form.swift" :disabled="isSaving" placeholder="BSCHESMMXXX" />
              </div>
            </div>

            <!-- Stripe Connect -->
            <div class="int-shell">

              <!-- State 1: Not connected -->
              <template v-if="!stripeConnected">
                <div class="stripe-block">
                  <div class="int-row" style="margin-bottom:.875rem;">
                    <div class="int-icon int-stripe"><i class="fa fa-bolt"></i></div>
                    <div class="flex-grow-1">
                      <div class="int-name">{{ $t('settings.stripe.name') }}</div>
                      <div class="int-desc">{{ $t('settings.stripe.desc') }}</div>
                    </div>
                  </div>
                  <a
                    href="/settings/payments/stripe/connect"
                    class="btn btn-sm stripe-connect-btn"
                    :class="{ disabled: stripeLoading }"
                  >
                    <span v-if="stripeLoading" class="spinner-border spinner-border-sm me-2" role="status"></span>
                    <i v-else class="fa fa-link me-2"></i>
                    {{ $t('settings.stripe.connect') }}
                  </a>
                </div>
              </template>

              <!-- State 2: Connected + onboarding complete -->
              <template v-else-if="stripeOnboarded">
                <div class="stripe-block stripe-block--connected">
                  <div class="int-row" style="margin-bottom:.75rem;">
                    <div class="int-icon int-stripe"><i class="fa fa-bolt"></i></div>
                    <div class="flex-grow-1">
                      <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="int-name">{{ $t('settings.stripe.name') }}</div>
                        <span class="stripe-badge stripe-badge--green">
                          <i class="fa fa-check-circle"></i> {{ $t('settings.stripe.connected') }}
                        </span>
                      </div>
                      <div class="int-desc mt-1 stripe-account-id">{{ stripeStatus.account_id }}</div>
                    </div>
                  </div>
                  <div class="stripe-caps">
                    <span class="stripe-cap-item" :class="stripeStatus.charges_enabled ? 'stripe-cap-item--ok' : 'stripe-cap-item--no'">
                      <i :class="stripeStatus.charges_enabled ? 'fa fa-check-circle' : 'fa fa-times-circle'"></i>
                      {{ stripeStatus.charges_enabled ? $t('settings.stripe.chargesEnabled') : $t('settings.stripe.chargesDisabled') }}
                    </span>
                    <span class="stripe-cap-item" :class="stripeStatus.payouts_enabled ? 'stripe-cap-item--ok' : 'stripe-cap-item--no'">
                      <i :class="stripeStatus.payouts_enabled ? 'fa fa-check-circle' : 'fa fa-times-circle'"></i>
                      {{ stripeStatus.payouts_enabled ? $t('settings.stripe.payoutsEnabled') : $t('settings.stripe.payoutsDisabled') }}
                    </span>
                  </div>
                  <div class="stripe-actions">
                    <a href="https://dashboard.stripe.com/" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary">
                      <i class="fa fa-external-link-alt me-1"></i>{{ $t('settings.stripe.dashboard') }}
                    </a>
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-danger"
                      @click="showDisconnectModal = true"
                      :disabled="stripeDisconnecting"
                    >
                      <span v-if="stripeDisconnecting" class="spinner-border spinner-border-sm me-1"></span>
                      <i v-else class="fa fa-unlink me-1"></i>
                      {{ $t('settings.stripe.disconnect') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="loadStripeStatus" :disabled="stripeLoading">
                      <span v-if="stripeLoading" class="spinner-border spinner-border-sm me-1"></span>
                      <i v-else class="fa fa-sync me-1"></i>
                      {{ $t('settings.stripe.refresh') }}
                    </button>
                  </div>
                </div>
              </template>

              <!-- State 3: Connected but incomplete onboarding -->
              <template v-else>
                <div class="stripe-block stripe-block--warn">
                  <div class="int-row" style="margin-bottom:.75rem;">
                    <div class="stripe-warn-icon"><i class="fa fa-exclamation-triangle"></i></div>
                    <div class="flex-grow-1">
                      <div class="d-flex align-items-center gap-2">
                        <div class="int-name">{{ $t('settings.stripe.setupIncomplete') }}</div>
                        <span class="stripe-badge stripe-badge--amber">{{ $t('settings.stripe.pending') }}</span>
                      </div>
                      <div class="int-desc mt-1">{{ $t('settings.stripe.pendingDesc') }}</div>
                    </div>
                  </div>
                  <div class="stripe-actions">
                    <a href="/settings/payments/stripe/connect" class="btn btn-sm stripe-connect-btn">
                      <i class="fa fa-arrow-right me-1"></i>{{ $t('settings.stripe.completeSetup') }}
                    </a>
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-danger"
                      @click="showDisconnectModal = true"
                      :disabled="stripeDisconnecting"
                    >
                      <span v-if="stripeDisconnecting" class="spinner-border spinner-border-sm me-1"></span>
                      {{ $t('settings.stripe.disconnect') }}
                    </button>
                  </div>
                </div>
              </template>

              <!-- PayPal - coming soon -->
              <div class="int-row int-row--divider">
                <div class="int-icon int-paypal"><i class="fa fa-paypal"></i></div>
                <div class="flex-grow-1">
                  <div class="int-name">{{ $t('settings.paypal.name') }}</div>
                  <div class="int-desc">{{ $t('settings.paypal.desc') }}</div>
                </div>
                <span class="int-pill">{{ $t('settings.comingSoon') }}</span>
              </div>
            </div>
          </section>

          <!-- ═══ Branding ═══════════════════════════════════════ -->
          <section id="branding" class="sc">
            <div class="sc-head">
              <span class="sc-tag">{{ $t('settings.branding.tag') }}</span>
              <h2 class="sc-title">{{ $t('settings.branding.title') }}</h2>
              <p class="sc-desc">{{ $t('settings.branding.desc') }}</p>
            </div>

            <!-- Brand color -->
            <div class="sr">
              <div class="sr-lbl">
                <span class="sr-name">{{ $t('settings.branding.brandColorName') }}</span>
                <span class="sr-hint">{{ $t('settings.branding.brandColorHint') }}</span>
              </div>
              <div class="sr-inp">
                <div class="color-row">
                  <input type="color" class="form-control form-control-color color-native" v-model="form.brand_color" :disabled="isSaving" />
                  <input type="text" class="form-control color-hex" v-model="form.brand_color" :disabled="isSaving" placeholder="#E91E63" maxlength="7" />
                  <div class="color-dot" :style="{ background: form.brand_color }"></div>
                </div>
              </div>
            </div>

            <div class="sc-line"></div>

            <!-- Stamp -->
            <div class="sr">
              <div class="sr-lbl">
                <span class="sr-name">{{ $t('settings.branding.stampName') }}</span>
                <span class="sr-hint">{{ $t('settings.branding.stampHint') }}</span>
              </div>
              <div class="sr-inp">
                <div class="logo-area">
                  <div class="logo-box" @click="$refs.stampInput.click()" title="Click to upload">
                    <img v-if="stampPreview" :src="stampPreview" alt="Stamp" class="logo-img" />
                    <div v-else class="logo-empty">
                      <i class="fa fa-stamp"></i>
                    </div>
                  </div>
                  <div class="logo-btns">
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="$refs.stampInput.click()" :disabled="isSaving">
                      <i class="fa fa-upload me-1"></i>{{ $t('settings.upload') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="openSignaturePad" :disabled="isSaving">
                      <i class="fa fa-pen me-1"></i>{{ $t('settings.draw') }}
                    </button>
                    <button v-if="stampPreview" type="button" class="btn btn-sm btn-outline-secondary" @click="clearStamp" :disabled="isSaving">{{ $t('settings.remove') }}</button>
                    <input ref="stampInput" type="file" accept="image/*" class="d-none" @change="onFileChange($event, 'stamp')" />
                    <span class="sr-hint mt-1">{{ $t('settings.branding.stampFileHint') }}</span>
                  </div>
                </div>
                <div v-if="errors.stamp" class="sc-error">{{ errors.stamp[0] }}</div>
              </div>
            </div>

            <div class="sc-line"></div>

            <!-- Templates link -->
            <div class="sr">
              <div class="sr-lbl">
                <span class="sr-name">{{ $t('settings.branding.templatesName') }}</span>
                <span class="sr-hint">{{ $t('settings.branding.templatesHint') }}</span>
              </div>
              <div class="sr-inp">
                <router-link to="/admin/templates" class="btn btn-sm btn-outline-primary">
                  <i class="fa fa-palette me-1"></i>{{ $t('settings.branding.manageTemplates') }}
                  <i class="fa fa-arrow-right ms-1 fa-xs"></i>
                </router-link>
                <p class="sc-ftext mt-2">{{ $t('settings.branding.templatesNote') }}</p>
              </div>
            </div>
          </section>

          <!-- ═══ Notifications ══════════════════════════════════ -->
          <section id="notifications" class="sc">
            <div class="sc-head">
              <span class="sc-tag">{{ $t('settings.notifications.tag') }}</span>
              <h2 class="sc-title">{{ $t('settings.notifications.title') }}</h2>
              <p class="sc-desc">{{ $t('settings.notifications.desc') }}</p>
            </div>
            <div class="soon-block">
              <div class="soon-icon">
                <i class="fa fa-bell"></i>
              </div>
              <div class="soon-title">{{ $t('settings.comingSoon') }}</div>
              <p class="soon-desc">{{ $t('settings.notifications.soonDesc') }}</p>
            </div>
          </section>

          <!-- ═══ Language ═══════════════════════════════════════ -->
          <section id="language" class="sc">
            <div class="sc-head">
              <span class="sc-tag">{{ $t('settings.language.tag') }}</span>
              <h2 class="sc-title">{{ $t('settings.language.title') }}</h2>
              <p class="sc-desc">{{ $t('settings.language.desc') }}</p>
            </div>
            <div class="sr">
              <div class="sr-lbl">
                <label for="lang-select" class="sr-name">{{ $t('settings.language.label') }}</label>
              </div>
              <div class="sr-inp">
                <select id="lang-select" class="form-select" style="max-width:220px;" v-model="userLocale" @change="saveLocale" :disabled="langSaving">
                  <option value="es">{{ $t('languages.es') }}</option>
                  <option value="fr">{{ $t('languages.fr') }}</option>
                  <option value="en">{{ $t('languages.en') }}</option>
                </select>
              </div>
            </div>
          </section>

          <!-- ═══ Account ════════════════════════════════════════ -->
          <section id="account" class="sc">
            <div class="sc-head">
              <span class="sc-tag">{{ $t('settings.account.tag') }}</span>
              <h2 class="sc-title">{{ $t('settings.account.title') }}</h2>
              <p class="sc-desc">{{ $t('settings.account.desc') }}</p>
            </div>
            <div class="acct-row">
              <div>
                <div class="sr-name">{{ $t('settings.account.profileName') }}</div>
                <span class="sr-hint">{{ $t('settings.account.profileHint') }}</span>
              </div>
              <router-link to="/admin/profile" class="btn btn-sm btn-outline-primary">
                {{ $t('settings.account.goToProfile') }} <i class="fa fa-arrow-right ms-1 fa-xs"></i>
              </router-link>
            </div>
          </section>

        </div><!-- /.sp-main -->
      </div><!-- /.sp-layout -->

      <!-- ── Sticky save bar ──────────────────────────────────── -->
      <div class="sp-savebar">
        <Transition name="badge-pop">
          <span v-if="saveStatus === 'saved'" class="sp-saved">
            <i class="fa fa-check-circle"></i> {{ $t('settings.saved') }}
          </span>
        </Transition>
        <button type="submit" class="btn btn-primary sp-savebtn" :disabled="isSaving">
          <span v-if="isSaving" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
          <i v-else class="fa fa-save me-2"></i>{{ isSaving ? $t('settings.saving') : $t('settings.saveChanges') }}
        </button>
      </div>
    </form>

    <!-- ── Signature pad ─────────────────────────────────────── -->
    <div v-if="showSignaturePad" class="sig-overlay" @click.self="showSignaturePad = false">
      <div class="sig-dialog">
        <div class="sig-header">
          <h5 class="sig-htitle">{{ $t('settings.signature.title') }}</h5>
          <button type="button" class="btn-close" @click="showSignaturePad = false"></button>
        </div>
        <div class="sig-body">
          <p class="sc-ftext mb-3">{{ $t('settings.signature.hint') }}</p>
          <div class="sig-canvas-wrap">
            <canvas
              ref="signatureCanvas"
              @mousedown="startSignature"
              @mousemove="drawSignature"
              @mouseup="endSignature"
              @mouseleave="endSignature"
              @touchstart.prevent="startSignature"
              @touchmove.prevent="drawSignature"
              @touchend="endSignature"
              @touchcancel="endSignature"
            ></canvas>
          </div>
        </div>
        <div class="sig-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" @click="clearSignature">
            <i class="fa fa-eraser me-1"></i>{{ $t('settings.signature.clear') }}
          </button>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" @click="showSignaturePad = false">{{ $t('common.cancel') }}</button>
            <button type="button" class="btn btn-primary" @click="saveSignature">{{ $t('settings.signature.use') }}</button>
          </div>
        </div>
      </div>
    </div>

  </div>

    <!-- ── Stripe disconnect confirmation modal ────────────────── -->
    <div v-if="showDisconnectModal" class="sig-overlay" @click.self="showDisconnectModal = false">
      <div class="sig-dialog" style="max-width:420px;">
        <div class="sig-header">
          <h5 class="sig-htitle">{{ $t('settings.stripe.disconnectTitle') }}</h5>
          <button type="button" class="btn-close" @click="showDisconnectModal = false"></button>
        </div>
        <div class="sig-body">
          <p style="font-size:.875rem;color:var(--sp-text);margin:0 0 .75rem;">
            {{ $t('settings.stripe.disconnectDesc1') }}
          </p>
          <p style="font-size:.8rem;color:var(--sp-muted);margin:0;">
            {{ $t('settings.stripe.disconnectDesc2') }}
          </p>
        </div>
        <div class="sig-footer">
          <button type="button" class="btn btn-outline-secondary" @click="showDisconnectModal = false">{{ $t('common.cancel') }}</button>
          <button type="button" class="btn btn-danger" @click="disconnectStripe" :disabled="stripeDisconnecting">
            <span v-if="stripeDisconnecting" class="spinner-border spinner-border-sm me-2"></span>
            {{ $t('settings.stripe.disconnectConfirm') }}
          </button>
        </div>
      </div>
    </div>

</template>

<script setup>
import { reactive, ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useRoute }  from 'vue-router';
import { useI18n }   from 'vue-i18n';
import axios from 'axios';
import { createToaster } from '@meforma/vue-toaster';
import { setLocale } from '@/i18n';

const toaster = createToaster({});
const route   = useRoute();
const { t }   = useI18n();

// ── Nav groups (computed so labels re-render on locale change) ──
const businessSections = computed(() => [
  { id: 'company', label: t('settings.nav.company'),     icon: 'fa fa-building' },
  { id: 'contact', label: t('settings.nav.contact'),     icon: 'fa fa-map-marker-alt' },
  { id: 'tax',     label: t('settings.nav.taxLegal'),    icon: 'fa fa-receipt' },
]);
const billingSections = computed(() => [
  { id: 'invoicing', label: t('settings.nav.invoiceSettings'), icon: 'fa fa-file-invoice' },
  { id: 'banking',   label: t('settings.nav.banking'),          icon: 'fa fa-university' },
]);
const designSections = computed(() => [
  { id: 'branding', label: t('settings.nav.branding'), icon: 'fa fa-palette' },
]);
const accountSections = computed(() => [
  { id: 'notifications', label: t('settings.nav.notifications'), icon: 'fa fa-bell' },
  { id: 'language',      label: t('settings.nav.language'),      icon: 'fa fa-globe' },
  { id: 'account',       label: t('settings.nav.account'),       icon: 'fa fa-user-circle' },
]);

// ── State ──────────────────────────────────────────────────────
const isLoading     = ref(false);
const isSaving      = ref(false);
const errors        = ref({});
const saveStatus    = ref('');
const activeSection = ref('company');

const logoPreview    = ref('');
const stampPreview   = ref('');
const logoFile       = ref(null);
const stampFile      = ref(null);

const showSignaturePad = ref(false);
const signatureCanvas  = ref(null);
const isDrawing        = ref(false);
const signatureCtx     = ref(null);

// ── Language selector state ────────────────────────────────────
const userLocale = ref('es');
const langSaving = ref(false);

// ── Stripe Connect state ───────────────────────────────────────
const stripeLoading         = ref(false);
const stripeDisconnecting   = ref(false);
const showDisconnectModal   = ref(false);
const stripeStatus = reactive({
  account_id:           null,
  connection_status:    null,
  onboarding_completed: false,
  charges_enabled:      false,
  payouts_enabled:      false,
  connected_at:         null,
});

const stripeConnected  = computed(() => !!stripeStatus.account_id);
const stripeOnboarded  = computed(() => stripeStatus.onboarding_completed);
const stripeIncomplete = computed(() => stripeConnected.value && !stripeOnboarded.value);

let saveTimer    = null;
let spyObserver  = null;

const form = reactive({
  legal_name:            '',
  trade_name:            '',
  industry:              '',
  country_code:          'ES',
  tax_id:                '',
  vat_number:            '',
  registration_number:   '',
  email:                 '',
  phone:                 '',
  website:               '',
  address_line1:         '',
  address_line2:         '',
  city:                  '',
  state:                 '',
  postal_code:           '',
  country:               '',
  brand_color:           '#E91E63',
  invoice_footer_note:   '',
  invoice_prefix:        'INV',
  invoice_next_number:   1,
  invoice_number_format: '{PREFIX}-{YYYY}-{NUMBER}',
  timezone:              'Europe/Madrid',
  locale:                'es',
  currency:              'EUR',
  bank_name:             '',
  iban:                  '',
  swift:                 '',
});

// ── Computed ───────────────────────────────────────────────────
const invoiceNumberPreview = computed(() => {
  const now = new Date();
  return (form.invoice_number_format || '{PREFIX}-{YYYY}-{NUMBER}')
    .replace('{PREFIX}',  form.invoice_prefix || 'INV')
    .replace('{YYYY}',    now.getFullYear())
    .replace('{MM}',      String(now.getMonth() + 1).padStart(2, '0'))
    .replace('{NUMBER}',  String(form.invoice_next_number || 1).padStart(4, '0'));
});

// ── Lifecycle ──────────────────────────────────────────────────
onMounted(() => {
  fetchSettings();
  loadUserLocale();
  setupScrollSpy();
  handleOAuthRedirect();
});

onUnmounted(() => {
  spyObserver?.disconnect();
  clearTimeout(saveTimer);
});

// ── Fetch ──────────────────────────────────────────────────────
async function fetchSettings() {
  isLoading.value = true;
  errors.value    = {};
  try {
    const { data } = await axios.get('/settings');
    fillForm(data?.settings ?? data ?? {});
  } catch {
    toaster.error(t('settings.errors.load'));
  } finally {
    isLoading.value = false;
  }
}

async function loadUserLocale() {
  try {
    const { data } = await axios.get('/user');
    userLocale.value = data.locale || 'es';
  } catch { /* non-critical */ }
}

function fillForm(data = {}) {
  Object.keys(form).forEach(key => {
    if (data[key] !== undefined && data[key] !== null) form[key] = data[key];
  });
  if (data.logo_path)  logoPreview.value  = data.logo_path;
  if (data.stamp_path) stampPreview.value = data.stamp_path;
  fillStripeStatus(data);
}

function fillStripeStatus(data = {}) {
  if (data.stripe_account_id        !== undefined) stripeStatus.account_id           = data.stripe_account_id        ?? null;
  if (data.stripe_connection_status !== undefined) stripeStatus.connection_status     = data.stripe_connection_status ?? null;
  if (data.onboarding_completed     !== undefined) stripeStatus.onboarding_completed  = !!data.onboarding_completed;
  if (data.charges_enabled          !== undefined) stripeStatus.charges_enabled       = !!data.charges_enabled;
  if (data.payouts_enabled          !== undefined) stripeStatus.payouts_enabled        = !!data.payouts_enabled;
  if (data.stripe_connected_at      !== undefined) stripeStatus.connected_at          = data.stripe_connected_at ?? null;
}

// ── Save ───────────────────────────────────────────────────────
async function saveAll() {
  isSaving.value = true;
  errors.value   = {};
  try {
    const payload = new FormData();
    payload.append('_method', 'put');
    Object.entries(form).forEach(([k, v]) => payload.append(k, v ?? ''));
    if (logoFile.value)  payload.append('logo',  logoFile.value);
    if (stampFile.value) payload.append('stamp', stampFile.value);

    const { data } = await axios.post('/settings', payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    fillForm(data?.settings ?? {});
    logoFile.value  = null;
    stampFile.value = null;

    saveStatus.value = 'saved';
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => { saveStatus.value = ''; }, 2800);
  } catch (err) {
    if (err.response?.status === 422) {
      errors.value = err.response.data?.errors ?? {};
      toaster.error(t('settings.errors.validation'));
      return;
    }
    toaster.error(t('settings.errors.save'));
  } finally {
    isSaving.value = false;
  }
}

// ── Language selector ──────────────────────────────────────────
async function saveLocale() {
  langSaving.value = true;
  try {
    await axios.put('/user', { locale: userLocale.value });
    setLocale(userLocale.value);
    toaster.success(t('settings.language.savedSuccess'));
  } catch {
    toaster.error(t('settings.language.error'));
  } finally {
    langSaving.value = false;
  }
}

// ── Files ──────────────────────────────────────────────────────
function onFileChange(event, type) {
  const [file] = event.target.files || [];
  if (type === 'logo') {
    logoFile.value    = file || null;
    logoPreview.value = file ? URL.createObjectURL(file) : '';
  } else {
    stampFile.value    = file || null;
    stampPreview.value = file ? URL.createObjectURL(file) : '';
  }
}
function clearLogo()  { logoFile.value  = null; logoPreview.value  = ''; }
function clearStamp() { stampFile.value = null; stampPreview.value = ''; }

// ── Stripe Connect ─────────────────────────────────────────────

function handleOAuthRedirect() {
  const q = route?.query ?? {};
  if (q.stripe_connected) {
    loadStripeStatus();
    toaster.success(t('settings.stripe.connectedSuccess'));
    window.history.replaceState({}, '', window.location.pathname);
  } else if (q.stripe_error) {
    toaster.error(t('settings.stripe.connectionFailed', { err: decodeURIComponent(q.stripe_error) }));
    window.history.replaceState({}, '', window.location.pathname);
  }
}

async function loadStripeStatus() {
  stripeLoading.value = true;
  try {
    const { data } = await axios.get('/settings/payments/stripe/status');
    fillStripeStatus(data);
  } catch {
    // Status endpoint failed - non-critical
  } finally {
    stripeLoading.value = false;
  }
}

async function disconnectStripe() {
  stripeDisconnecting.value = true;
  showDisconnectModal.value = false;
  try {
    await axios.post('/settings/payments/stripe/disconnect');
    fillStripeStatus({
      stripe_account_id:        null,
      stripe_connection_status: null,
      onboarding_completed:     false,
      charges_enabled:          false,
      payouts_enabled:          false,
      stripe_connected_at:      null,
    });
    toaster.success(t('settings.stripe.disconnectedSuccess'));
  } catch (err) {
    toaster.error(err.response?.data?.message ?? t('settings.errors.stripeDisconnect'));
  } finally {
    stripeDisconnecting.value = false;
  }
}

// ── Scroll spy ─────────────────────────────────────────────────
const allSections = ['company','contact','tax','invoicing','banking','branding','notifications','language','account'];

function setupScrollSpy() {
  spyObserver = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) activeSection.value = e.target.id; });
  }, { rootMargin: '-15% 0px -75% 0px', threshold: 0 });

  allSections.forEach(id => {
    const el = document.getElementById(id);
    if (el) spyObserver.observe(el);
  });
}

function scrollTo(id) {
  const el = document.getElementById(id);
  if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); activeSection.value = id; }
}

// ── Signature ──────────────────────────────────────────────────
function openSignaturePad() {
  showSignaturePad.value = true;
  nextTick(() => {
    const canvas = signatureCanvas.value;
    if (!canvas) return;
    const dpr = window.devicePixelRatio || 1;
    const w = 500, h = 200;
    canvas.width  = w * dpr; canvas.height = h * dpr;
    canvas.style.width  = `${w}px`; canvas.style.height = `${h}px`;
    const ctx = canvas.getContext('2d');
    ctx.scale(dpr, dpr);
    ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#111';
    signatureCtx.value = ctx;
    ctx.clearRect(0, 0, w, h);
  });
}

function getPos(event) {
  const canvas = signatureCanvas.value;
  if (!canvas) return { x: 0, y: 0 };
  const rect = canvas.getBoundingClientRect();
  const cx = event.touches ? event.touches[0].clientX : event.clientX;
  const cy = event.touches ? event.touches[0].clientY : event.clientY;
  return { x: cx - rect.left, y: cy - rect.top };
}

function startSignature(e) {
  if (!signatureCtx.value) return;
  isDrawing.value = true;
  const { x, y } = getPos(e);
  signatureCtx.value.beginPath(); signatureCtx.value.moveTo(x, y);
}
function drawSignature(e) {
  if (!isDrawing.value || !signatureCtx.value) return;
  const { x, y } = getPos(e);
  signatureCtx.value.lineTo(x, y); signatureCtx.value.stroke();
}
function endSignature() {
  if (!isDrawing.value || !signatureCtx.value) return;
  signatureCtx.value.closePath(); isDrawing.value = false;
}
function clearSignature() {
  const c = signatureCanvas.value;
  if (!c || !signatureCtx.value) return;
  const dpr = window.devicePixelRatio || 1;
  signatureCtx.value.clearRect(0, 0, c.width / dpr, c.height / dpr);
}
function saveSignature() {
  const c = signatureCanvas.value;
  if (!c) return;
  c.toBlob(blob => {
    if (!blob) return;
    stampFile.value    = new File([blob], 'signature.png', { type: 'image/png' });
    stampPreview.value = URL.createObjectURL(blob);
    showSignaturePad.value = false;
  });
}
</script>

<style scoped>
/* ─────────────────────────────────────────────────────────────
   Design tokens
───────────────────────────────────────────────────────────── */
.settings-page {
  /* Light */
  --sp-page:      #F0F2FA;
  --sp-surface:   #FFFFFF;
  --sp-border:    #E3E6F3;
  --sp-shadow:    0 1px 2px rgba(14,18,50,.04), 0 4px 14px rgba(14,18,50,.06);
  --sp-text:      #111827;
  --sp-muted:     #64708A;
  --sp-field-bg:  #F8F9FE;
  --sp-accent:    #E91E63;
  --sp-accent2:   #c2185b;
  --sp-nav-bg:    #1A1D2E;
  --sp-nav-text:  rgba(255,255,255,.55);
  --sp-nav-hover: rgba(255,255,255,.08);
  --sp-radius:    10px;
  --sp-row-px:    2rem;
  --sp-row-py:    1.375rem;
}

@media (prefers-color-scheme: dark) {
  .settings-page {
    --sp-page:     #0C0E1C;
    --sp-surface:  #13162A;
    --sp-border:   rgba(255,255,255,.07);
    --sp-shadow:   0 1px 2px rgba(0,0,0,.3), 0 4px 14px rgba(0,0,0,.25);
    --sp-text:     #E1E5FA;
    --sp-muted:    #8290B8;
    --sp-field-bg: #191C30;
    --sp-nav-bg:   #090B17;
  }
}
:root[data-theme="dark"]  .settings-page {
  --sp-page:     #0C0E1C;
  --sp-surface:  #13162A;
  --sp-border:   rgba(255,255,255,.07);
  --sp-shadow:   0 1px 2px rgba(0,0,0,.3), 0 4px 14px rgba(0,0,0,.25);
  --sp-text:     #E1E5FA;
  --sp-muted:    #8290B8;
  --sp-field-bg: #191C30;
  --sp-nav-bg:   #090B17;
}
:root[data-theme="light"] .settings-page {
  --sp-page:     #F0F2FA;
  --sp-surface:  #FFFFFF;
  --sp-border:   #E3E6F3;
  --sp-shadow:   0 1px 2px rgba(14,18,50,.04), 0 4px 14px rgba(14,18,50,.06);
  --sp-text:     #111827;
  --sp-muted:    #64708A;
  --sp-field-bg: #F8F9FE;
  --sp-nav-bg:   #1A1D2E;
}

/* ─────────────────────────────────────────────────────────────
   Page shell
───────────────────────────────────────────────────────────── */
.settings-page {
  background: var(--sp-page);
  color: var(--sp-text);
  min-height: calc(100vh - 4.5rem);
  padding-bottom: 6rem;
}

/* ─────────────────────────────────────────────────────────────
   Page header
───────────────────────────────────────────────────────────── */
.sp-header { margin-bottom: 1.75rem; }

.sp-title {
  font-size: 1.35rem;
  font-weight: 800;
  letter-spacing: -0.025em;
  color: var(--sp-text);
  margin: 0 0 0.25rem;
  line-height: 1.2;
}
.sp-sub {
  font-size: 0.875rem;
  color: var(--sp-muted);
  margin: 0;
}

/* ─────────────────────────────────────────────────────────────
   Loading dots
───────────────────────────────────────────────────────────── */
.sp-loading {
  display: flex;
  gap: 6px;
  align-items: center;
  justify-content: center;
  padding: 5rem;
}
.sp-loading-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: var(--sp-accent);
  animation: dot-pulse 1.2s ease-in-out infinite;
}
.sp-loading-dot:nth-child(2) { animation-delay: .2s; opacity: .7; }
.sp-loading-dot:nth-child(3) { animation-delay: .4s; opacity: .4; }
@keyframes dot-pulse {
  0%, 80%, 100% { transform: scale(.7); opacity: .4; }
  40%           { transform: scale(1);  opacity: 1;  }
}

/* ─────────────────────────────────────────────────────────────
   Layout
───────────────────────────────────────────────────────────── */
.sp-layout {
  display: flex;
  gap: 1.625rem;
  align-items: flex-start;
}
.sp-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1.125rem;
}

/* ─────────────────────────────────────────────────────────────
   Sidebar
───────────────────────────────────────────────────────────── */
.sp-sidebar {
  width: 190px;
  flex-shrink: 0;
  position: sticky;
  top: 4.75rem;
}

.sp-nav {
  background: var(--sp-nav-bg);
  border-radius: 12px;
  padding: 0.5rem;
  display: flex;
  flex-direction: column;
  gap: 1px;
  /* subtle inner shadow so the nav has depth */
  box-shadow: inset 0 1px 0 rgba(255,255,255,.04),
              0 4px 20px rgba(0,0,0,.22);
}

.sp-nav-group {
  padding: 0.875rem 0.75rem 0.3rem;
  font-size: 0.59rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: rgba(255,255,255,.25);
  user-select: none;
}

.sp-nav-btn {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  width: 100%;
  text-align: left;
  padding: 0.575rem 0.75rem;
  border-radius: 7px;
  border: none;
  background: transparent;
  color: var(--sp-nav-text);
  font-size: 0.845rem;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.12s, color 0.12s;
  line-height: 1.25;
}
.sp-nav-btn:hover {
  background: var(--sp-nav-hover);
  color: rgba(255,255,255,.85);
}
.sp-nav-btn--active {
  background: var(--sp-accent);
  color: #fff;
  font-weight: 500;
}
.sp-nav-btn--active .sp-nav-ico { opacity: 1; }
.sp-nav-ico {
  font-size: 0.8rem;
  width: 14px;
  flex-shrink: 0;
  opacity: 0.6;
  text-align: center;
}

/* ─────────────────────────────────────────────────────────────
   Section cards
───────────────────────────────────────────────────────────── */
.sc {
  background: var(--sp-surface);
  border: 1px solid var(--sp-border);
  border-radius: var(--sp-radius);
  box-shadow: var(--sp-shadow);
  overflow: hidden;
  scroll-margin-top: 5.5rem;
}

.sc-head {
  padding: 1.5rem var(--sp-row-px) 1.25rem;
  border-bottom: 1px solid var(--sp-border);
  /* Top accent stripe */
  border-top: 3px solid var(--sp-accent);
}

.sc-tag {
  display: block;
  font-size: 0.62rem;
  font-weight: 800;
  letter-spacing: 0.13em;
  text-transform: uppercase;
  color: var(--sp-accent);
  margin-bottom: 0.4rem;
}

.sc-title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--sp-text);
  margin: 0 0 0.2rem;
  line-height: 1.3;
}

.sc-desc {
  font-size: 0.8rem;
  color: var(--sp-muted);
  margin: 0;
}

.sc-line {
  height: 1px;
  background: var(--sp-border);
}

.sc-ftext {
  font-size: 0.74rem;
  color: var(--sp-muted);
  margin: 0;
}

.sc-error {
  font-size: 0.78rem;
  color: #dc3545;
  margin-top: 0.3rem;
}

/* ─────────────────────────────────────────────────────────────
   Field rows
───────────────────────────────────────────────────────────── */
.sr {
  display: grid;
  grid-template-columns: 175px 1fr;
  gap: 1rem 1.75rem;
  padding: var(--sp-row-py) var(--sp-row-px);
  align-items: flex-start;
}

.sr-lbl { padding-top: 0.35rem; }

.sr-name {
  display: block;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--sp-text);
  margin-bottom: 0.2rem;
}
.sr-hint {
  display: block;
  font-size: 0.73rem;
  color: var(--sp-muted);
  line-height: 1.45;
}
.sr-req { color: var(--sp-accent); }

/* Sub-label for inputs within a group */
.sc-ftext.mb-1 { margin-bottom: 0.25rem !important; }

/* Vertically stacked fields within one row */
.sr-stack { display: flex; flex-direction: column; gap: 0.875rem; }
.sr-inp   { min-width: 0; }

/* ─────────────────────────────────────────────────────────────
   Form controls (scoped override of global border-radius: 2px)
───────────────────────────────────────────────────────────── */
.sr-inp .form-control,
.sr-inp .form-select {
  background: var(--sp-field-bg);
  border-color: var(--sp-border);
  color: var(--sp-text);
  border-radius: 7px !important;
  font-size: 0.875rem;
  transition: border-color .15s, box-shadow .15s, background .15s;
}
.sr-inp .form-control::placeholder { color: var(--sp-muted); opacity: .65; }
.sr-inp .form-control:focus,
.sr-inp .form-select:focus {
  background: var(--sp-surface);
  border-color: var(--sp-accent);
  box-shadow: 0 0 0 3px rgba(233,30,99,.14);
  color: var(--sp-text);
}
.sr-inp .form-control:disabled {
  opacity: .55;
}
.sr-inp code {
  font-size: 0.78rem;
  color: var(--sp-accent);
}

/* ─────────────────────────────────────────────────────────────
   Logo / stamp uploader
───────────────────────────────────────────────────────────── */
.logo-area {
  display: flex;
  gap: 1.25rem;
  align-items: flex-start;
}
.logo-box {
  width: 72px;
  height: 72px;
  border-radius: 9px;
  border: 1.5px dashed var(--sp-border);
  background: var(--sp-field-bg);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  overflow: hidden;
  transition: border-color .15s, background .15s;
}
.logo-box:hover { border-color: var(--sp-accent); background: rgba(233,30,99,.04); }
.logo-img       { width: 100%; height: 100%; object-fit: contain; padding: 6px; }
.logo-empty i   { font-size: 1.3rem; color: var(--sp-muted); opacity: .4; }
.logo-btns {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.5rem;
  padding-top: 0.25rem;
}

/* ─────────────────────────────────────────────────────────────
   Invoice number preview pill
───────────────────────────────────────────────────────────── */
.num-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.45rem 0.875rem;
  background: var(--sp-field-bg);
  border: 1px solid var(--sp-border);
  border-radius: 7px;
  font-size: 0.8rem;
  color: var(--sp-muted);
}
.num-pill code {
  font-family: ui-monospace, 'SF Mono', Menlo, monospace;
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--sp-accent);
}

/* ─────────────────────────────────────────────────────────────
   Color picker
───────────────────────────────────────────────────────────── */
.color-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.color-native {
  width: 44px !important;
  height: 38px !important;
  padding: 2px 3px !important;
  border-radius: 7px !important;
  flex-shrink: 0;
  cursor: pointer;
}
.color-hex { max-width: 130px; font-family: ui-monospace, monospace; }
.color-dot {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 2px solid var(--sp-border);
  flex-shrink: 0;
  transition: background .18s, transform .12s;
  cursor: default;
}
.color-dot:hover { transform: scale(1.1); }

/* ─────────────────────────────────────────────────────────────
   Integrations (banking section)
───────────────────────────────────────────────────────────── */
.int-shell {
  padding: var(--sp-row-py) var(--sp-row-px);
  background: var(--sp-field-bg);
  border-top: 1px solid var(--sp-border);
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.int-row {
  display: flex;
  align-items: center;
  gap: 1rem;
}
.int-icon {
  width: 38px;
  height: 38px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  flex-shrink: 0;
}
.int-stripe { background: rgba(99,91,255,.13); color: #635bff; }
.int-paypal { background: #003087; color: #fff; }
.int-name   { font-size: 0.875rem; font-weight: 600; color: var(--sp-text); }
.int-desc   { font-size: 0.75rem; color: var(--sp-muted); }
.int-pill {
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: var(--sp-muted);
  background: var(--sp-border);
  padding: 0.22rem 0.65rem;
  border-radius: 20px;
  white-space: nowrap;
}

/* ─────────────────────────────────────────────────────────────
   Coming soon (notifications)
───────────────────────────────────────────────────────────── */
.soon-block {
  text-align: center;
  padding: 3rem 2rem;
}
.soon-icon {
  width: 54px;
  height: 54px;
  border-radius: 14px;
  background: rgba(233,30,99,.08);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  color: var(--sp-accent);
  margin-bottom: 1rem;
}
.soon-title {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--sp-text);
  margin-bottom: 0.4rem;
}
.soon-desc {
  font-size: 0.82rem;
  color: var(--sp-muted);
  max-width: 340px;
  margin: 0 auto;
}

/* ─────────────────────────────────────────────────────────────
   Account row
───────────────────────────────────────────────────────────── */
.acct-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--sp-row-py) var(--sp-row-px);
}

/* ─────────────────────────────────────────────────────────────
   Sticky save bar
───────────────────────────────────────────────────────────── */
.sp-savebar {
  position: fixed;
  bottom: 1.5rem;
  right: 1.75rem;
  display: flex;
  align-items: center;
  gap: 0.875rem;
  z-index: 950;
}

.sp-saved {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  background: #14532d;
  color: #bbf7d0;
  font-size: 0.82rem;
  font-weight: 600;
  padding: 0.425rem 0.9rem;
  border-radius: 20px;
  box-shadow: 0 2px 10px rgba(0,0,0,.18);
}

.sp-savebtn {
  padding: 8px 20px !important;
  border-radius: 10px !important;
  font-weight: 500 !important;
  font-size: 0.875rem !important;
}
.sp-savebtn:disabled { opacity: .45; cursor: not-allowed; transform: none !important; box-shadow: none !important; }

/* badge animation */
.badge-pop-enter-active { animation: bpop .25s cubic-bezier(.34,1.56,.64,1); }
.badge-pop-leave-active { animation: bpop .18s ease reverse; }
@keyframes bpop {
  from { opacity: 0; transform: scale(.8) translateY(4px); }
  to   { opacity: 1; transform: scale(1)  translateY(0);   }
}

/* ─────────────────────────────────────────────────────────────
   Signature modal
───────────────────────────────────────────────────────────── */
.sig-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.56);
  z-index: 1055;
  display: flex; align-items: center; justify-content: center;
  padding: 1.5rem;
}
.sig-dialog {
  background: var(--sp-surface);
  border: 1px solid var(--sp-border);
  border-radius: 12px;
  box-shadow: 0 24px 64px rgba(0,0,0,.32);
  overflow: hidden;
  width: 100%; max-width: 580px;
  display: flex; flex-direction: column;
}
.sig-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--sp-border);
}
.sig-htitle { font-size: 0.95rem; font-weight: 700; color: var(--sp-text); margin: 0; }
.sig-body   { padding: 1.5rem; }
.sig-canvas-wrap {
  border: 1.5px dashed var(--sp-border);
  border-radius: 8px;
  overflow: hidden; cursor: crosshair; touch-action: none;
  background: var(--sp-field-bg);
}
.sig-canvas-wrap canvas { display: block; max-width: 100%; }
.sig-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--sp-border);
  display: flex; align-items: center; justify-content: space-between;
}

/* ─────────────────────────────────────────────────────────────
   Responsive
───────────────────────────────────────────────────────────── */
@media (max-width: 880px) {
  .sp-layout    { flex-direction: column; }
  .sp-sidebar   { width: 100%; position: static; }
  .sp-nav       { flex-direction: row; flex-wrap: wrap; gap: 2px; padding: 0.3rem; border-radius: 8px; }
  .sp-nav-group { display: none; }
  .sp-nav-btn   { flex: 0 0 auto; padding: 0.375rem 0.7rem; font-size: 0.78rem; gap: 0.375rem; border-radius: 6px; }
}

@media (max-width: 580px) {
  .sr           { grid-template-columns: 1fr; gap: 0.5rem; padding: 1.125rem 1.125rem; }
  .sr-lbl       { padding-top: 0; }
  .sc-head      { padding: 1.125rem 1.125rem 1rem; }
  --sp-row-px:  1.125rem;
  .int-shell    { padding: 1.125rem; }
  .acct-row     { padding: 1.125rem; flex-wrap: wrap; gap: 0.75rem; }
  .sp-savebar   { bottom: 1rem; right: 1rem; left: 1rem; justify-content: flex-end; }
  .soon-block   { padding: 2rem 1.25rem; }
}

/* ─────────────────────────────────────────────────────────────
   Stripe Connect UI
───────────────────────────────────────────────────────────── */
.int-row--divider {
  padding-top: 1rem;
  margin-top: 0.5rem;
  border-top: 1px solid var(--sp-border);
}

/* Stripe Connect card block */
.stripe-block {
  padding: 1.125rem;
  border-radius: 8px;
  background: var(--sp-field-bg);
  border: 1px solid var(--sp-border);
  margin-bottom: 0.25rem;
}

.stripe-block--connected {
  border-color: rgba(34,197,94,.25);
  background: rgba(34,197,94,.04);
}

@media (prefers-color-scheme: dark) {
  .stripe-block--connected {
    background: rgba(34,197,94,.07);
    border-color: rgba(34,197,94,.18);
  }
}
:root[data-theme="dark"]  .stripe-block--connected { background: rgba(34,197,94,.07); border-color: rgba(34,197,94,.18); }
:root[data-theme="light"] .stripe-block--connected { background: rgba(34,197,94,.04); border-color: rgba(34,197,94,.25); }

.stripe-block--warn {
  border-color: rgba(245,158,11,.3);
  background: rgba(245,158,11,.05);
}

@media (prefers-color-scheme: dark) {
  .stripe-block--warn { background: rgba(245,158,11,.08); border-color: rgba(245,158,11,.2); }
}
:root[data-theme="dark"]  .stripe-block--warn { background: rgba(245,158,11,.08); border-color: rgba(245,158,11,.2); }
:root[data-theme="light"] .stripe-block--warn { background: rgba(245,158,11,.05); border-color: rgba(245,158,11,.3); }

/* Status badges */
.stripe-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  padding: 0.2rem 0.6rem;
  border-radius: 20px;
}
.stripe-badge--green {
  background: rgba(34,197,94,.15);
  color: #16a34a;
}
.stripe-badge--amber {
  background: rgba(245,158,11,.15);
  color: #d97706;
}

@media (prefers-color-scheme: dark) {
  .stripe-badge--green { color: #4ade80; }
  .stripe-badge--amber { color: #fbbf24; }
}
:root[data-theme="dark"] .stripe-badge--green { color: #4ade80; }
:root[data-theme="dark"] .stripe-badge--amber { color: #fbbf24; }

/* Capability pills */
.stripe-caps {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-bottom: 0.875rem;
}
.stripe-cap-item {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.78rem;
  font-weight: 500;
}
.stripe-cap-item--ok  { color: #16a34a; }
.stripe-cap-item--no  { color: #dc2626; }

@media (prefers-color-scheme: dark) {
  .stripe-cap-item--ok { color: #4ade80; }
  .stripe-cap-item--no { color: #f87171; }
}
:root[data-theme="dark"] .stripe-cap-item--ok { color: #4ade80; }
:root[data-theme="dark"] .stripe-cap-item--no { color: #f87171; }

/* Action row */
.stripe-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

/* Account ID display */
.stripe-account-id {
  font-family: ui-monospace, 'SF Mono', Menlo, monospace;
  font-size: 0.73rem !important;
  opacity: .75;
}

/* Warning icon */
.stripe-warn-icon {
  width: 38px;
  height: 38px;
  border-radius: 8px;
  background: rgba(245,158,11,.15);
  color: #d97706;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  flex-shrink: 0;
  margin-right: 0.75rem;
}

/* Connect button */
.stripe-connect-btn {
  background: #E91E63 !important;
  color: #fff !important;
  border: 1px solid #E91E63 !important;
  border-radius: 10px !important;
  font-weight: 500 !important;
  font-size: 0.875rem !important;
  padding: 8px 16px !important;
  transition: background .15s ease-in-out, border-color .15s ease-in-out,
              box-shadow .15s ease-in-out, transform .15s ease-in-out !important;
}
.stripe-connect-btn:hover:not(.disabled) {
  background: #c2185b !important;
  border-color: #c2185b !important;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(233, 30, 99, 0.22);
  color: #fff !important;
}
.stripe-connect-btn.disabled { opacity: .45; pointer-events: none; }
</style>
