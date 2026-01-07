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

          <table class="table table-vcenter">
            <thead>
              <tr>
                <th class="text-center">
                  <input type="checkbox" class="form-check-input" @change="clickedAll">
                </th>
                <th class="d-none d-sm-table-cell">
                  {{ $t("customers.table.name") }}
                </th>
                <th class="d-none d-sm-table-cell">
                  {{ $t("customers.table.address") }}
                </th>
                <th class="d-none d-sm-table-cell">
                  {{ $t("customers.table.email") }}
                </th>
                <th class="d-none d-sm-table-cell">
                  {{ $t("customers.table.phone") }}
                </th>
                <th class="d-none d-sm-table-cell">
                  {{ $t("customers.table.customerNumber") }}
                </th>
                <th class="text-center" style="width: 100px">{{ $t("common.actions") }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="customer in customers" :key="customer.id">
                <th class="text-center" scope="row">
                  <input type="checkbox" name="" class="form-check-input" v-model="customer.checked">
                </th>
                <td class="d-none d-sm-table-cell">
                  <router-link :to="'customers/' + customer.uuid">{{ customer.name }}</router-link>
                </td>
                <td class="d-none d-sm-table-cell">
                  {{ customer.address_billing }}
                </td>
                <td class="d-none d-sm-table-cell">
                    {{ customer.email }}
                </td>
                <td class="d-none d-sm-table-cell">
                  {{ customer.phone }}
                </td>
                <td class="d-none d-sm-table-cell">
                  {{ customer.reference }}
                </td>
                <td class="text-center">
                  <div class="dropdown dropstart push">
                    <button
                      type="button"
                      class="btn btn-outline-primary dropdown-toggle"
                      id="dropdown-dropleft-dark"
                      data-bs-toggle="dropdown"
                      aria-haspopup="true"
                      aria-expanded="false"
                    >
                      <i class="fa fa-fw fa-ellipsis-v"></i>
                    </button>
                    <div
                      class="dropdown-menu fs-sm"
                      aria-labelledby="dropdown-dropleft-dark"
                    >
                      <router-link class="dropdown-item danger" :to="'customers/edit/' + customer.uuid">
                        {{ $t("common.edit") }}
                      </router-link>
                      <a class="dropdown-item danger" href="javascript:void(0)" @click.prevent="deleteCustomer(customer)">
                        {{ $t("common.delete") }}
                      </a>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </BaseBlock>

</div>

</template>


<script setup>
import { ref, onMounted } from "vue";
import axios from 'axios'
import { useI18n } from "vue-i18n";


// Input state variables
const customers = ref([])
const { t } = useI18n();

onMounted(async () => {
        
        let response = await axios.get('/customers');
        customers.value = response.data.customers
});


const deleteCustomer = (customer) => {
  if (confirm(t('customers.confirmDelete'))) {
        axios.delete('/customers/' + customer.uuid)
        .then(response => {
            customers.value = customers.value.filter(c => c.uuid !== customer.uuid)
        })
        .catch(error => {
            console.error('There was an error!', error);
        });
    }
}

const clickedAll = (value) => {
  customers.value = customers.value.map(customer => {
              customer.checked = value
              return customer;
  })
}



</script>
