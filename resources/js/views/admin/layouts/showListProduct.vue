<template>
    <!-- Extra Large Block Modal -->
<div
class="modal"
id="modal-block-extra-large"
tabindex="-1"
ref="listProduct"
role="dialog"
aria-labelledby="modal-block-extra-large"
aria-hidden="true"
>
<div class="modal-dialog modal-xl" role="document">
<div class="modal-content">
<BaseBlock title="ADD PRODUCTS AND SERVICES" transparent class="mb-0">
  <template #options>
    <button
      type="button"
      class="btn-block-option"
      data-bs-dismiss="modal"
      aria-label="Close"
    >
      <i class="fa fa-fw fa-times"></i>
    </button>
  </template>

  <template #content>
    <div class="block-content">

      <BaseBlock>
              <template #options>
                <div class="block-options-item">
                  <router-link to="/backend/items/new" class="btn btn-primary">New Product or Service</router-link>
                </div>
              </template>

              <template #content>
              <div class="block-content fs-sm">
    
              <table class="table table-vcenter">
                <thead>
                  <tr>
                    <th class="d-none d-sm-table-cell">
                      Product Code
                    </th>
                    <th class="d-none d-sm-table-cell">
                      Name
                    </th>
                    <th class="d-none d-sm-table-cell">
                      Description
                    </th>
                    <th class="d-none d-sm-table-cell">
                      Sales Price
                    </th>
                    <th class="d-none d-sm-table-cell">
                      Purchase price
                    </th>
                    <th class="d-none d-sm-table-cell">
                      Type
                    </th>
                    <th class="d-none d-sm-table-cell">
                      Family
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in items" :key="item.id">

                    <td class="d-none d-sm-table-cell">
                        <input type="checkbox" :id="'item-name' + index" class="form-check-input" v-model="item.checked" @change="selectProducts(item)">
                        <label :for="'item-name' + index" class="pl-6">{{ item.reference }}</label>
                    </td>
                    <td class="d-none d-sm-table-cell">
                      {{ item.name }}
                    </td>
                    <td class="d-none d-sm-table-cell">
                      {{ item.description }}
                    </td>
                    <td class="d-none d-sm-table-cell">
                        {{ item.sales_price }}
                    </td>
                    <td class="d-none d-sm-table-cell">
                        {{ item.purchase_price }}
                    </td>
                    <td class="d-none d-sm-table-cell">
                      {{ item.type_name }}
                    </td>
                    <td class="d-none d-sm-table-cell">
                      {{ item.family_name }}
                    </td>
                  </tr>
                </tbody>
              </table>
              </div>

              <div class="block-content block-content-full text-end bg-body">
                <button
                  type="button"
                  class="btn btn-sm btn-alt-secondary me-1"
                  data-bs-dismiss="modal"
                >
                  Close
                </button>
                <button
                  @click.prevent="sendProducts"
                  data-bs-dismiss="modal"
                  type="button"
                  class="btn btn-sm btn-primary"
                >
                  Done
                </button>
              </div>
            </template>

        </BaseBlock>

          </div>
        </template>
      </BaseBlock>
    </div>
  </div>
</div>
<!-- END Extra Large Block Modal -->
</template>
    
    <script setup>
    import { ref, onMounted } from "vue";
    import axios from 'axios'
    
    const emit = defineEmits(['sendCarts'])
    // Input state variables
    const items = ref([])
    const carts = ref([])
    const listProduct = ref(null)

    
    onMounted(async () => {
            
            let response = await axios.get('/items');
            items.value = response.data.items
    });
    
    const selectProducts = (item) => {
      if(item.checked == true){
        carts.value.push(item)
      }else{
        carts.value = carts.value.filter(ct => ct.uuid != item.uuid);
      }
    }

    const sendProducts = () => {
      emit('sendCarts', carts.value)
      items.value = items.value.map(element => {
        element.checked = false
        return element;
      });
      carts.value = []
    }
    
    
    </script>
    
    