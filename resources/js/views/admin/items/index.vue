<template>
  <div class="content">

    <BaseBlock :title="$t('items.title')">
      <template #options>
        <div class="block-options-item">
          <router-link to="items/new" class="btn btn-primary">
            <i class="fa fa-plus me-1"></i>
            {{ $t("items.newTitle") }}
          </router-link>
        </div>
      </template>

      <BulkActionBar
        :count="selectedIds.length"
        :loading="bulkDeleting"
        @delete="showBulkDeleteModal = true"
        @clear="clearSelection"
      />

      <DataTableShell
        :rows="items"
        :server-side="false"
        :status-options="[]"
        :show-date-filter="false"
        :search-fields="['name', 'reference', 'description']"
        :search-placeholder="$t('items.searchPlaceholder')"
      >
        <template #head="{ sortField, sortDir, setSort }">
          <tr>
            <th class="dt-cb-col">
              <input type="checkbox" class="form-check-input" @change="clickedAll">
            </th>
            <th class="dt-sortable" @click="setSort('name')">
              {{ $t("items.fields.name") }}
              <i class="fa fa-fw ms-1"
                :class="sortField === 'name' ? (sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'"></i>
            </th>
            <th>{{ $t("items.fields.description") }}</th>
            <th class="dt-sortable" @click="setSort('reference')">
              {{ $t("items.fields.reference") }}
              <i class="fa fa-fw ms-1"
                :class="sortField === 'reference' ? (sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'"></i>
            </th>
            <th class="dt-sortable" @click="setSort('sales_price')">
              {{ $t("items.fields.salesPrice") }}
              <i class="fa fa-fw ms-1"
                :class="sortField === 'sales_price' ? (sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'"></i>
            </th>
            <th class="dt-sortable" @click="setSort('purchase_price')">
              {{ $t("items.fields.purchasePrice") }}
              <i class="fa fa-fw ms-1"
                :class="sortField === 'purchase_price' ? (sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'"></i>
            </th>
            <th class="dt-ac-col">{{ $t("common.actions") }}</th>
          </tr>
        </template>

        <template #body="{ items: rows }">
          <tr v-for="product in rows" :key="product.uuid">
            <td class="dt-cb-col">
              <input type="checkbox" class="form-check-input" v-model="product.checked">
            </td>
            <td>
              <router-link :to="'items/edit/' + product.uuid">{{ product.name }}</router-link>
            </td>
            <td>{{ product.description }}</td>
            <td>{{ product.reference }}</td>
            <td>{{ $toComma(product.sales_price) }}</td>
            <td>{{ $toComma(product.purchase_price) }}</td>
            <td class="dt-ac-col">
              <RowActionMenu>
                <router-link class="dropdown-item" :to="'items/edit/' + product.uuid">
                  <i class="fa fa-pencil fa-fw me-1"></i>{{ $t("common.edit") }}
                </router-link>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-danger" href="javascript:void(0)" @click.prevent="deleteProduct(product)">
                  <i class="fa fa-trash fa-fw me-1"></i>{{ $t("common.delete") }}
                </a>
              </RowActionMenu>
            </td>
          </tr>
        </template>
      </DataTableShell>
    </BaseBlock>

  </div>

  <BulkDeleteModal
    :show="showBulkDeleteModal"
    :count="selectedIds.length"
    :loading="bulkDeleting"
    @confirm="bulkDelete"
    @cancel="showBulkDeleteModal = false"
  />
</template>


<script setup>
import { ref, computed, onMounted } from "vue";
import axios from 'axios';
import { useI18n } from "vue-i18n";
import DataTableShell  from '@/views/admin/layouts/DataTableShell.vue';
import BulkActionBar   from '@/views/admin/layouts/BulkActionBar.vue';
import BulkDeleteModal from '@/views/admin/layouts/BulkDeleteModal.vue';
import RowActionMenu   from '@/views/admin/layouts/RowActionMenu.vue';
import { createToaster } from '@meforma/vue-toaster';

const toaster = createToaster();
const { t }   = useI18n();
const items   = ref([]);

const showBulkDeleteModal = ref(false);
const bulkDeleting        = ref(false);
const selectedIds         = computed(() => items.value.filter(p => p.checked).map(p => p.uuid));

onMounted(async () => {
  const response = await axios.get('/items');
  items.value = response.data.items;
});

const deleteProduct = (product) => {
  if (confirm(t('items.confirmDelete'))) {
    axios.delete('/items/' + product.uuid)
      .then(() => {
        items.value = items.value.filter(item => item.uuid !== product.uuid);
      })
      .catch(error => {
        console.error('Error deleting item:', error);
      });
  }
};

function clickedAll(e) {
  const checked = e.target.checked;
  items.value = items.value.map(p => ({ ...p, checked }));
}

function clearSelection() {
  items.value = items.value.map(p => ({ ...p, checked: false }));
}

async function bulkDelete() {
  const ids = selectedIds.value.slice();
  if (!ids.length) return;
  bulkDeleting.value = true;
  try {
    await axios.post('/items/bulk-delete', { ids });
    items.value = items.value.filter(p => !ids.includes(p.uuid));
    showBulkDeleteModal.value = false;
    toaster.success(t('common.bulkDeleteSuccess', { count: ids.length }));
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t('items.errorGeneric'));
  } finally {
    bulkDeleting.value = false;
  }
}
</script>
