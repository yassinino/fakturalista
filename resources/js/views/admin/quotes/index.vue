<template>
    <div class="content">    
      <BaseBlock title="Quotes">
              <template #options>
                <div class="block-options-item">
                  <router-link to="quotes/new" class="btn btn-primary">New Quote</router-link>
                </div>
              </template>
    
              <table class="table table-vcenter">
                <thead>
                  <tr>
                    <th class="text-center">
                      <input type="checkbox" class="form-check-input" @change="clickedAll">
                    </th>
                    <th class="d-none d-sm-table-cell">
                      Reference
                    </th>
                    <th class="d-none d-sm-table-cell">
                      Customer
                    </th>
                    <th class="d-none d-sm-table-cell">
                      Date
                    </th>
                    <th class="d-none d-sm-table-cell">
                      Expiration Date
                    </th>
                    <th class="d-none d-sm-table-cell">
                      Status
                    </th>
                    <th class="text-center" style="width: 100px">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="quote in quotes" :key="quote.id">
                    <th class="text-center" scope="row">
                      <input type="checkbox" name="" class="form-check-input" v-model="quote.checked">
                    </th>
                    <td class="d-none d-sm-table-cell">
                      <router-link class="" :to="'quotes/edit/' + quote.uuid">{{ quote.reference }}</router-link>
                    </td>
                    <td class="d-none d-sm-table-cell">
                      {{ quote.customer }}
                    </td>
                    <td class="d-none d-sm-table-cell">
                        {{ quote.date }}
                    </td>
                    <td class="d-none d-sm-table-cell">
                      {{ quote.expiration_date }}
                    </td>
                    <td class="d-none d-sm-table-cell">
                      {{ quote.status }}
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
                          @click.prevent="printDoc(quote)">Print</a>
                          <a class="dropdown-item" @click.prevent="deleteQuote(quote)">Delete</a>
                        </div>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </BaseBlock>
    
            <printDocument ref="showPrint" :quote="selectedQuote"/>

    </div>
    
    </template>
    
    
    <script setup>
    import { ref, onMounted } from "vue";
    import axios from 'axios'
    import $ from 'jquery'

    // Input state variables
    const quotes = ref([])
    const selectedQuote = ref({})
    const showPrint = ref(null)

    
    onMounted(async () => {
            
            let response = await axios.get('/quotes');
            quotes.value = response.data.quotes
    });
    
    
    const deleteQuote = (quote) => {
      alert(quote.reference)
    }

    const printDoc = (quote) => {
      selectedQuote.value = quote;
      console.log(quote)
      showPrint.value.$refs.myModal.close()
    }
    
    const clickedAll = (value) => {
      quotes.value = quotes.value.map(quote => {
                  quote.checked = value
                  return quote;
      })
    }
    
    
    
    </script>
    
    