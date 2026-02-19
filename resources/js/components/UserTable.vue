<template>
    <div class="card mt-4">
        <DataTable
            :value="pagination.data"
            :loading="loading"
            responsiveLayout="scroll"
            stripedRows
            class="p-datatable-sm"
            selectionMode="single"
            @row-click="onRowClick"
            style="cursor: pointer"
        >
            <template #header>
                <div class="d-flex justify-content-between align-items-center">
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

        <Dialog
            v-model:visible="displayModal"
            :header="selectedUser ? 'Modifica Utente' : 'Nuovo Utente'"
            :style="{ width: '50vw' }"
            :modal="true"
        >
            <UserForm selectedUser />
        </Dialog>
    </div>
</template>

<script>
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Paginator from "primevue/paginator";
import IconField from "primevue/iconfield";
import InputText from "primevue/inputtext";
import Dialog from "primevue/dialog"; // Nuova importazione
import Button from "primevue/button";
import UserForm from "./UserForm.vue";

export default {
    components: { DataTable, Column, Paginator, IconField, InputText, Dialog, Button, UserForm },
    data() {
        return {
            filter: "",
            loading: false,
            pagination: { data: [], total: 0, per_page: 10 },
            displayModal: false, // Stato visibilità modale
            selectedUser: null, // Dati dell'utente cliccato
        };
    },
    // ... watcher e altri metodi precedenti ...
    methods: {
        // ... loadUsers e onPage ...

        onRowClick(event) {
            // event.data contiene l'oggetto user della riga cliccata
            this.selectedUser = event.data;
            this.displayModal = true;
        },

        onPage(event) {
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
                    },
                })
                .then((res) => {
                    this.pagination = res.data;
                })
                .finally(() => (this.loading = false));
        },
    },
    mounted() {
        this.loadUsers();
    },
};
</script>
