import { nextTick } from 'vue';

/**
 * Faster line entry on Create Invoice / Create Quote (keyboard + duplicate).
 *
 *  - Enter in a line's description / quantity / price field:
 *      last line   -> adds a line (the form's own addNewItem: qty 1, unit,
 *                     workspace tax) and focuses its product field;
 *      other lines -> focuses the next line's product field.
 *    Enter never submits the document (the handlers prevent it).
 *  - "+ Ajouter une ligne" also focuses the new line.
 *  - duplicateLine(i): an independent copy (item, description, qty, unit,
 *    price, discount, tax) inserted right below line i.
 *
 * Native tab order is untouched (it already follows the visual order).
 *
 * @param getCarts   () => state.carts
 * @param addLine    the form's existing addNewItem()
 * @param linesRoot  template ref of the <tbody> holding the <tr class="inv-line"> rows
 * @param onDuplicated optional (sourceIndex) => void, e.g. to shift index-based UI state
 */
export function useLineEditing({ getCarts, addLine, linesRoot, onDuplicated = () => {} }) {
  async function focusLine(index) {
    await nextTick();
    const row = linesRoot.value?.querySelectorAll('tr.inv-line')[index];
    // First useful field: the product/service selector's search input.
    const target = row?.querySelector('.vs__search') ?? row?.querySelector('input, select, textarea');
    target?.focus();
  }

  function onLineEnter(index) {
    const isLast = index === getCarts().length - 1;
    if (isLast) addLine();
    return focusLine(index + 1);
  }

  function addLineAndFocus() {
    addLine();
    return focusLine(getCarts().length - 1);
  }

  function duplicateLine(index) {
    const source = getCarts()[index];
    if (!source) return;
    getCarts().splice(index + 1, 0, { ...source }); // flat line object -> fully independent copy
    onDuplicated(index);
    return focusLine(index + 1);
  }

  return { onLineEnter, addLineAndFocus, duplicateLine, focusLine };
}
