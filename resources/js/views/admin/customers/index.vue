<template>
  <div class="content">

    <BaseBlock :title="$t('customers.title')">
      <template #options>
        <div class="block-options-item">
          <router-link to="customers/new" class="btn btn-primary">
            {{ $t("customers.newTitle") }}
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
        :rows="customers"
        :server-side="false"
        :status-options="[]"
        :show-date-filter="false"
        :search-fields="['name', 'email', 'phone', 'reference']"
        :search-placeholder="$t('customers.searchPlaceholder')"
      >
        <template #head="{ sortField, sortDir, setSort }">
          <tr>
            <th class="dt-cb-col">
              <input type="checkbox" class="form-check-input" @change="clickedAll">
            </th>
            <th class="dt-sortable" @click="setSort('name')">
              {{ $t("customers.table.name") }}
              <i class="fa fa-fw ms-1"
                :class="sortField === 'name' ? (sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'"></i>
            </th>
            <th>{{ $t("customers.table.address") }}</th>
            <th class="dt-sortable" @click="setSort('email')">
              {{ $t("customers.table.email") }}
              <i class="fa fa-fw ms-1"
                :class="sortField === 'email' ? (sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'"></i>
            </th>
            <th class="dt-sortable" @click="setSort('phone')">
              {{ $t("customers.table.phone") }}
              <i class="fa fa-fw ms-1"
                :class="sortField === 'phone' ? (sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'"></i>
            </th>
            <th class="dt-sortable" @click="setSort('reference')">
              {{ $t("customers.table.customerNumber") }}
              <i class="fa fa-fw ms-1"
                :class="sortField === 'reference' ? (sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'"></i>
            </th>
            <th class="dt-ac-col">{{ $t("common.actions") }}</th>
          </tr>
        </template>

        <template #body="{ items }">
          <tr v-for="customer in items" :key="customer.uuid">
            <td class="dt-cb-col">
              <input type="checkbox" class="form-check-input" v-model="customer.checked">
            </td>
            <td>
              <router-link :to="'customers/' + customer.uuid">{{ customer.name }}</router-link>
            </td>
            <td>{{ customer.address_billing }}</td>
            <td>{{ customer.email }}</td>
            <td>{{ customer.phone }}</td>
            <td>{{ customer.reference }}</td>
            <td class="dt-ac-col">
              <RowActionMenu>
                <router-link class="dropdown-item" :to="'customers/edit/' + customer.uuid">
                  <i class="fa fa-pencil fa-fw me-1"></i>{{ $t("common.edit") }}
                </router-link>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-danger" href="javascript:void(0)" @click.prevent="deleteCustomer(customer)">
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

const toaster   = createToaster();
const { t }     = useI18n();
const customers = ref([]);

const showBulkDeleteModal = ref(false);
const bulkDeleting        = ref(false);
const selectedIds         = computed(() => customers.value.filter(c => c.checked).map(c => c.uuid));

onMounted(async () => {
  const response = await axios.get('/customers');
  customers.value = response.data.customers;
});

const deleteCustomer = (customer) => {
  if (confirm(t('customers.confirmDelete'))) {
    axios.delete('/customers/' + customer.uuid)
      .then(() => {
        customers.value = customers.value.filter(c => c.uuid !== customer.uuid);
      })
      .catch(error => {
        console.error('Error deleting customer:', error);
      });
  }
};

function clickedAll(e) {
  const checked = e.target.checked;
  customers.value = customers.value.map(c => ({ ...c, checked }));
}

function clearSelection() {
  customers.value = customers.value.map(c => ({ ...c, checked: false }));
}

async function bulkDelete() {
  const ids = selectedIds.value.slice();
  if (!ids.length) return;
  bulkDeleting.value = true;
  try {
    await axios.post('/customers/bulk-delete', { ids });
    customers.value = customers.value.filter(c => !ids.includes(c.uuid));
    showBulkDeleteModal.value = false;
    toaster.success(t('common.bulkDeleteSuccess', { count: ids.length }));
  } catch (e) {
    toaster.error(e.response?.data?.message ?? t('customers.errorGeneric'));
  } finally {
    bulkDeleting.value = false;
  }
}
</script>
