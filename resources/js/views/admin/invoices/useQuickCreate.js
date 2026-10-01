import { reactive } from 'vue';
import axios from 'axios';

/**
 * Quick create ("+ Nouveau client" / "+ Nouveau produit ou service") for the
 * invoice and quote forms. Shared state + handlers for QuickCustomerModal /
 * QuickItemModal (which only POST to the existing endpoints and emit the new
 * record's id).
 *
 * Selection always goes through the form's OWN existing handlers - exactly
 * the code path of picking from the dropdown - so nothing else in the form
 * state changes:
 *   - selectCustomer(created): e.g. set customer_id + onClientSelect(created)
 *   - selectItem(lineIndex, created): the form's selectProduct()
 *
 * `isLocked` (optional): when it returns true, nothing can be opened (e.g. an
 * accepted quote, whose dropdowns are already disabled).
 */
export function useQuickCreate({ customers, items, selectCustomer, selectItem, isLocked = () => false }) {
  const quickCustomer = reactive({ open: false, search: '' });
  const quickItem = reactive({ open: false, search: '', line: null });

  function openQuickCustomer(search = '') {
    if (isLocked()) return;
    quickCustomer.search = search || '';
    quickCustomer.open = true;
  }

  function openQuickItem(index, search = '') {
    if (isLocked()) return;
    quickItem.line = index;
    quickItem.search = search || '';
    quickItem.open = true;
  }

  async function onQuickCustomerCreated(uuid) {
    try {
      const { data } = await axios.get('/customers');
      customers.value = data.customers;
      const created = customers.value.find((c) => c.uuid === uuid);
      if (created) selectCustomer(created);
    } finally {
      quickCustomer.open = false;
    }
  }

  async function onQuickItemCreated(id) {
    const line = quickItem.line;
    try {
      const { data } = await axios.get('/items');
      items.value = data.items;
      const created = items.value.find((i) => String(i.id) === String(id));
      if (created && line !== null) selectItem(line, created);
    } finally {
      quickItem.open = false;
    }
  }

  return {
    quickCustomer,
    quickItem,
    openQuickCustomer,
    openQuickItem,
    onQuickCustomerCreated,
    onQuickItemCreated,
  };
}
