<template>
  <!-- Rendered in a vue-select #list-footer slot. mousedown.prevent keeps the
       dropdown from closing (blur) before the click registers. -->
  <li class="inv-qc-action">
    <button type="button" @mousedown.prevent @click="$emit('activate')">
      <i class="fa fa-plus"></i> {{ label }}
    </button>
  </li>
</template>

<script setup>
/**
 * The "+ Nouveau client" / "+ Nouveau produit ou service" row at the bottom
 * of the customer / product dropdowns on the invoice and quote forms.
 */
defineProps({ label: { type: String, required: true } });
defineEmits(['activate']);
</script>

<style scoped>
.inv-qc-action {
  list-style: none;
  border-top: 1px solid #e2e8f0;
  margin-top: 4px;
  padding: 4px;
}
.inv-qc-action button {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 9px 10px;
  border: none;
  border-radius: 6px;
  background: transparent;
  color: var(--brand-primary);
  font-size: 0.85rem;
  font-weight: 600;
  text-align: start;
  cursor: pointer;
}
.inv-qc-action button:hover,
.inv-qc-action button:focus-visible { background: rgba(233, 30, 99, 0.08); outline: none; }
:global(.dark-mode) .inv-qc-action { border-top-color: var(--dark-border-subtle); }
:global(.dark-mode) .inv-qc-action button:hover,
:global(.dark-mode) .inv-qc-action button:focus-visible { background: rgba(233, 30, 99, 0.16); }
</style>
