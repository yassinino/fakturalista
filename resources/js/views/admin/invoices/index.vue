<template>
    <div class="content">
    
      <BaseBlock :title="$t('invoices.title')">
              <template #options>
                <div class="block-options-item">
                  <router-link to="invoices/new" class="btn btn-primary">
                    {{ $t("invoices.newTitle") }}
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
                      {{ $t("invoices.table.number") }}
                    </th>
                    <th class="d-none d-sm-table-cell">
                      {{ $t("invoices.table.customer") }}
                    </th>
                    <th class="d-none d-sm-table-cell">
                      {{ $t("invoices.table.date") }}
                    </th>
                    <th class="d-none d-sm-table-cell">
                      {{ $t("invoices.table.subtotal") }}
                    </th>
                    <th class="d-none d-sm-table-cell">
                      {{ $t("invoices.table.total") }}
                    </th>
                    <th class="d-none d-sm-table-cell">
                      {{ $t("invoices.table.status") }}
                    </th>
                    <th class="text-center" style="width: 100px">{{ $t("common.actions") }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="invoice in invoices" :key="invoice.id">
                    <th class="text-center" scope="row">
                      <input type="checkbox" name="" class="form-check-input" v-model="invoice.checked">
                    </th>
                    <td class="d-none d-sm-table-cell">
                      <router-link :to="'invoices/edit/' + invoice.uuid">{{ invoice.reference }}</router-link>
                    </td>
                    <td class="d-none d-sm-table-cell">
                      {{ invoice.customer }}
                    </td>
                    <td class="d-none d-sm-table-cell">
                        {{ invoice.date }}
                    </td>
                    <td class="d-none d-sm-table-cell">
                      {{ $toComma(invoice.sub_total) }}
                    </td>
                    <td class="d-none d-sm-table-cell">
                      {{ $toComma(invoice.total) }}
                    </td>
                    <td class="d-none d-sm-table-cell">
                      <span 
                      class="fs-xs fw-semibold d-inline-block py-1 px-3 rounded-pill"
                      :class="invoice.status == 1 ? 'bg-success-light text-success' : 'bg-danger-light text-danger'">
                      {{ invoice.status == 1 ? $t('invoices.statusPaid') : $t('invoices.statusUnpaid') }}
                      </span>
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
                          <a class="dropdown-item"                
                          href="javascript:void(0)" 
                          @click.prevent="printInvoice(invoice)">{{ $t("invoices.document") }}</a>
                          <a class="dropdown-item" href="javascript:void(0)" @click.prevent="deleteInvoice(invoice)">{{ $t("common.delete") }}</a>
                        </div>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>

              <Pagination
                :data="invoices"
                :meta="{
                  currentPage: meta.current_page,
                  lastPage: meta.last_page,
                  perPage: meta.per_page,
                  total: meta.total
                }"
                @update:page="onUpdatePage"
                @update:perPage="onUpdatePerPage"
              />
              
            </BaseBlock>
    
            
    </div>
    
    </template>
    
    
    <script setup>
    import { ref, reactive, onMounted } from "vue";
    import axios from 'axios'
    import Pagination from '@/views/admin/layouts/Pagination.vue';
    import { createToaster } from '@meforma/vue-toaster';
    const toaster = createToaster({ /* options */ });
    import { useI18n } from "vue-i18n";
    // Input state variables
    const invoices = ref([])
    const { t } = useI18n();
    const meta = reactive({
      current_page: 1,
      last_page: 1,
      per_page: 10,
      total: 0
    });

    async function fetchInvoices(page = meta.current_page, perPage = meta.per_page) {
      const { data } = await axios.get('/invoices', {
        params: { page, per_page: perPage }
      });
      invoices.value = data.invoices;
      meta.current_page = data.meta.current_page;
      meta.last_page    = data.meta.last_page;
      meta.per_page     = data.meta.per_page ?? perPage;
      meta.total        = data.meta.total;
    }

    onMounted(() => fetchInvoices());
    

    function onUpdatePage(n) {
      if (n < 1 || n > meta.last_page) return;
      meta.current_page = n;
      fetchInvoices(n, meta.per_page);
    }

    function onUpdatePerPage(n) {
      meta.per_page = n;
      meta.current_page = 1;
      fetchInvoices(1, n);
    }
    
    const deleteInvoice = (invoice) => {
      if (confirm(t('invoices.confirmDelete'))) {
          axios.delete('/invoices/' + invoice.uuid)
          .then(response => {
              invoices.value = invoices.value.filter(inv => inv.uuid !== invoice.uuid);
          })
          .catch(error => {
              console.error('There was an error deleting the invoice!', error);
          });
      }
    }
    
    const clickedAll = (value) => {
      invoices.value = invoices.value.map(invoice => {
                  invoice.checked = value
                  return invoice;
      })
    }

    async function printInvoice(state) {

      axios.post('/invoices/print', state).then(res => {
                                
        toaster.success(res.data.message);
        window.open(res.data.pdf_url, '_blank');
    
      })

    }

    
    
    </script>
    
    
