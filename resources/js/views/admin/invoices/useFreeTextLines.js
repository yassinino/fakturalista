/**
 * Free-text invoice/quote lines - a line doesn't need a catalog item.
 *
 * The product/service selector (vue-select) discards whatever was typed in
 * it when it loses focus without a pick. With this, text typed there and
 * NOT turned into a selection (e.g. "Consultation octobre", then clicking
 * Price) is kept as the line's description - the field saved with the line
 * (carts.description) and printed on the PDF - instead of being lost.
 *
 *  - never creates a catalog item, never sets item_id;
 *  - a line that already has a picked item is left alone (normal autofill);
 *  - an existing description the user wrote is never overwritten;
 *  - picking a suggestion clears the search first, so nothing is copied.
 *
 * Usage on the line's <VueSelect>:
 *   @search="q => onLineSearch(index, q)"  @search:blur="onLineSearchBlur(index)"
 */
export function useFreeTextLines(getCarts) {
  const typed = new Map(); // line index -> current search text

  function onLineSearch(index, query) {
    typed.set(index, typeof query === 'string' ? query : '');
  }

  function onLineSearchBlur(index) {
    const text = (typed.get(index) ?? '').trim();
    typed.delete(index);

    const line = getCarts()[index];
    if (!text || !line || line.item_id) return;

    if (!line.description || !String(line.description).trim()) {
      line.description = text;
    }
  }

  return { onLineSearch, onLineSearchBlur };
}
