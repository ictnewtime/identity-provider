<template>
    <div class="card mt-4">
        <DataTable
            :value="pagination.data"
            :loading="loading"
            responsiveLayout="scroll"
            stripedRows
            class="p-datatable-sm"
        >
            <template #header>
                <div class="d-flex justify-content-between align-items-center justify-content-between">
                    <h3 class="m-0">Lista Utenti</h3>
                    <IconField iconPosition="left">
                        <InputText v-model="filter" placeholder="Cerca email..." size="small" />
                    </IconField>
                </div>
            </template>

            <Column field="username" header="Username"></Column>
            <Column field="email" header="Email"></Column>
            <Column field="name" header="Nome"></Column>
            <Column field="surname" header="Cognome"></Column>

            <template #empty> Nessun utente trovato. </template>
        </DataTable>

        <Paginator :rows="pagination.per_page" :totalRecords="pagination.total" @page="onPage" class="mt-2" />
    </div>
</template>

<script>
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Paginator from "primevue/paginator";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import InputText from "primevue/inputtext";

export default {
    components: { DataTable, Column, Paginator, IconField, InputIcon, InputText },
    data() {
        return {
            filter: "",
            loading: false,
            pagination: { data: [], total: 0, per_page: 10 },
        };
    },
    watch: {
        filter: _.debounce(function () {
            this.loadUsers();
        }, 500),
    },
    methods: {
        onPage(event) {
            // event.page è 0-indexed in PrimeVue, quindi aggiungiamo 1 per Laravel
            this.loadUsers(event.page + 1);
        },
        loadUsers(page = 1) {
            this.loading = true;
            const url = window.location.origin + "/admin/v1/users";
            axios
                .get(url, {
                    params: {
                        page: page,
                        per_page: this.pagination.per_page,
                        q: this.filter,
                        order: this.order,
                    },
                })
                .then((res) => {
                    this.pagination = res.data;
                    console.log("res.data", res.data);
                })
                .finally(() => (this.loading = false));
        },
    },

    mounted() {
        this.loadUsers();
    },
};
</script>
