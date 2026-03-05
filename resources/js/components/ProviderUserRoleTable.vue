<template>
    <div class="web-association-container p-4">
        <div class="mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h1 class="text-3xl font-bold m-0">Associazioni Utente-Provider-Ruolo</h1>
                <p class="text-gray-600 mt-1 mb-0">Gestisci i permessi e i ruoli degli utenti sui provider</p>
            </div>
            <Button label="Nuova Associazione" icon="pi pi-plus" @click="openCreateModal" />
        </div>

        <div class="card mt-4">
            <DataTable
                :value="pagination.data"
                :loading="loading"
                responsiveLayout="scroll"
                stripedRows
                class="p-datatable-sm"
            >
                <template #header>
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="m-0">Lista Associazioni</h3>
                        <IconField iconPosition="left">
                            <InputText
                                v-model="filter"
                                placeholder="Cerca email o dominio..."
                                size="small"
                                @input="onFilterChange"
                            />
                        </IconField>
                    </div>
                </template>

                <Column field="id" header="ID" style="width: 5%"></Column>

                <Column header="Utente">
                    <template #body="slotProps">
                        {{ slotProps.data.user ? slotProps.data.user.email : "Utente mancante" }}
                    </template>
                </Column>

                <Column header="Provider">
                    <template #body="slotProps">
                        {{ slotProps.data.provider ? slotProps.data.provider.domain : "Provider mancante" }}
                    </template>
                </Column>

                <Column header="Ruolo">
                    <template #body="slotProps">
                        {{ slotProps.data.role ? slotProps.data.role.name : "Ruolo mancante" }}
                    </template>
                </Column>

                <Column header="Azioni" :exportable="false" style="min-width: 8rem">
                    <template #body="slotProps">
                        <Button icon="pi pi-pencil" outlined class="me-2" @click="editItem(slotProps.data)" />
                        <Button icon="pi pi-trash" outlined severity="danger" @click="confirmDelete(slotProps.data)" />
                    </template>
                </Column>

                <template #empty> Nessuna associazione trovata. </template>
            </DataTable>

            <Paginator :rows="pagination.per_page" :totalRecords="pagination.total" @page="onPage" class="mt-2" />
        </div>

        <Dialog
            v-model:visible="displayModal"
            :header="selectedItem ? 'Modifica Associazione' : 'Nuova Associazione'"
            :style="{ width: '50vw' }"
            :modal="true"
        >
            <ProviderUserRoleForm :selectedItem="selectedItem" @item-saved="onItemSaved" />
        </Dialog>

        <Dialog
            v-model:visible="displayDeleteModal"
            header="Conferma Eliminazione"
            :style="{ width: '450px' }"
            :modal="true"
        >
            <div class="d-flex align-items-center">
                <i class="pi pi-exclamation-triangle me-3 text-warning" style="font-size: 2rem" />
                <span v-if="itemToDelete">
                    Sei sicuro di voler rimuovere l'associazione per l'utente
                    <b>{{ itemToDelete.user ? itemToDelete.user.email : "Selezionato" }}</b
                    >?
                </span>
            </div>
            <template #footer>
                <Button label="Annulla" icon="pi pi-times" text @click="displayDeleteModal = false" />
                <Button label="Elimina" icon="pi pi-check" severity="danger" @click="deleteItem" />
            </template>
        </Dialog>
    </div>
</template>

<script>
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Paginator from "primevue/paginator";
import IconField from "primevue/iconfield";
import InputText from "primevue/inputtext";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import ProviderUserRoleForm from "./ProviderUserRoleForm.vue";

export default {
    name: "ProviderUserRolePage",
    components: { DataTable, Column, Paginator, IconField, InputText, Dialog, Button, ProviderUserRoleForm },
    data() {
        return {
            filter: "",
            loading: false,
            pagination: { data: [], total: 0, per_page: 10 },

            displayModal: false,
            selectedItem: null,

            displayDeleteModal: false,
            itemToDelete: null,

            searchTimeout: null,
        };
    },
    methods: {
        loadItems(page = 1) {
            this.loading = true;
            // Endpoint per questa tabella (aggiorna la rotta in base alle tue API)
            axios
                .get("/admin/v1/provider-user-roles", {
                    params: { page: page, per_page: this.pagination.per_page, q: this.filter },
                })
                .then((res) => (this.pagination = res.data))
                .finally(() => (this.loading = false));
        },
        onPage(event) {
            this.loadItems(event.page + 1);
        },
        onFilterChange() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => this.loadItems(1), 500);
        },
        openCreateModal() {
            this.selectedItem = null;
            this.displayModal = true;
        },
        editItem(item) {
            this.selectedItem = item;
            this.displayModal = true;
        },
        onItemSaved() {
            this.displayModal = false;
            this.loadItems();
        },
        confirmDelete(item) {
            this.itemToDelete = item;
            this.displayDeleteModal = true;
        },
        deleteItem() {
            if (!this.itemToDelete) return;
            axios
                .delete(`/admin/v1/provider-user-roles/${this.itemToDelete.id}`)
                .then(() => {
                    this.$toast.add({
                        severity: "success",
                        summary: "Successo",
                        detail: "Associazione eliminata",
                        life: 3000,
                    });
                    this.displayDeleteModal = false;
                    this.itemToDelete = null;
                    this.loadItems();
                })
                .catch((error) => {
                    console.error(error);
                    this.$toast.add({
                        severity: "error",
                        summary: "Errore",
                        detail: "Errore durante l'eliminazione",
                        life: 3000,
                    });
                });
        },
    },
    mounted() {
        this.loadItems();
    },
};
</script>
