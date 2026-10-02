<template>
  <span class="pt-badge" :class="'pt-badge--' + status">{{ label }}</span>
</template>

<script setup>
/**
 * Small presentational status pill for the Client Portal (invoices and
 * quotes). Never decides *what* a document's status is - the caller
 * (ClientPortalView.vue) derives the semantic status (including
 * "overdue", which isn't a real backend status but a derived one, same
 * logic InvoicePaymentsController already uses server-side) and just
 * hands this component a status key + its translated label.
 */
defineProps({
  status: {
    type: String,
    required: true,
    validator: (v) => ['paid', 'pending', 'overdue', 'cancelled', 'sent', 'converted', 'accepted', 'rejected'].includes(v),
  },
  label: { type: String, required: true },
});
</script>

<style scoped>
.pt-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 11px;
  border-radius: 100px;
  font-size: 12.5px;
  font-weight: 700;
  line-height: 1.4;
  white-space: nowrap;
}

.pt-badge--paid      { background: #ECFDF5; color: #15803D; }
.pt-badge--pending    { background: #EFF6FF; color: #1D4ED8; }
.pt-badge--sent       { background: #EFF6FF; color: #1D4ED8; }
.pt-badge--converted  { background: #ECFDF5; color: #15803D; }
.pt-badge--accepted   { background: #ECFDF5; color: #15803D; }
.pt-badge--overdue    { background: #FEF2F2; color: #B91C1C; }
.pt-badge--cancelled  { background: #F3F4F6; color: #6B7280; }
.pt-badge--rejected   { background: #FEF2F2; color: #B91C1C; }

.dark-mode .pt-badge--paid      { background: rgba(21, 128, 61, 0.18);  color: #4ADE80; }
.dark-mode .pt-badge--pending   { background: rgba(29, 78, 216, 0.2);   color: #93C5FD; }
.dark-mode .pt-badge--sent      { background: rgba(29, 78, 216, 0.2);   color: #93C5FD; }
.dark-mode .pt-badge--converted { background: rgba(21, 128, 61, 0.18);  color: #4ADE80; }
.dark-mode .pt-badge--accepted  { background: rgba(21, 128, 61, 0.18);  color: #4ADE80; }
.dark-mode .pt-badge--overdue   { background: rgba(185, 28, 28, 0.2);   color: #FCA5A5; }
.dark-mode .pt-badge--cancelled { background: rgba(107, 114, 128, 0.25); color: #D1D5DB; }
.dark-mode .pt-badge--rejected  { background: rgba(185, 28, 28, 0.2);   color: #FCA5A5; }
</style>
