<template>
    <div class="content">    
    
      <BaseBlock title="Productos o Servicios">
              <template #options>
                <div class="block-options-item">
                  <router-link to="items/new" class="btn btn-primary">Nuevo producto o servicio</router-link>
                </div>
              </template>
    
              <table class="table table-vcenter">
                <thead>
                  <tr>
                    <th class="text-center">
                      <input type="checkbox" class="form-check-input" @change="clickedAll">
                    </th>
                    <th class="d-none d-sm-table-cell">
                      Nombre
                    </th>
                    <th class="d-none d-sm-table-cell">
                      Descripción
                    </th>
                    <th class="d-none d-sm-table-cell">
                      Referencia
                    </th>
                    <th class="d-none d-sm-table-cell">
                      Precio de venta
                    </th>
                    <th class="d-none d-sm-table-cell">
                      Precio de compra
                    </th>
                    <th class="text-center" style="width: 100px">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="product in items" :key="product.id">
                    <th class="text-center" scope="row">
                      <input type="checkbox" name="" class="form-check-input" v-model="product.checked">
                    </th>
                    <td class="d-none d-sm-table-cell">
                      <router-link :to="'items/edit/' + product.uuid">{{ product.name }}</router-link>
                    </td>
                    <td class="d-none d-sm-table-cell">
                      {{ product.description }}
                    </td>
                    <td class="d-none d-sm-table-cell">
                      {{ product.reference }}
                    </td>
                    <td class="d-none d-sm-table-cell">
                        {{ $toComma(product.sales_price) }}
                    </td>
                    <td class="d-none d-sm-table-cell">
                      {{ $toComma(product.purchase_price) }}
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
                          <router-link class="dropdown-item danger" :to="'items/edit/' + product.uuid">Editar</router-link>
                          <a class="dropdown-item danger" href="javascript:void(0)" @click.prevent="deleteProduct(product)">Eliminar</a>
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
    
    
    // Input state variables
    const items = ref([])
    
    onMounted(async () => {
            
            let response = await axios.get('/items');
            items.value = response.data.items
    });
    
    
    const deleteProduct = (product) => {
      if (confirm('¿Estás seguro de que deseas eliminar este producto o servicio?')) {
          axios.delete('/items/' + product.uuid)
          .then(response => {
              items.value = items.value.filter(item => item.uuid !== product.uuid);
          })
          .catch(error => {
              console.error('There was an error deleting the item!', error);
          });
      }
    }
    
    const clickedAll = (value) => {
      items.value = items.value.map(product => {
                  product.checked = value
                  return product;
      })
    }
    
    
    
    </script>
    
    