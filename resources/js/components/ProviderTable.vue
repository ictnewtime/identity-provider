<template>
    <div class="web-provider-container p-4">
        <div class="mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h1 class="text-3xl font-bold m-0">Gestione Provider</h1>
                <p class="text-gray-600 mt-1 mb-0">Visualizza e crea nuovi Identity Provider</p>
            </div>
            <Button label="Nuovo Provider" icon="pi pi-plus" @click="openCreateModal" />
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
                        <h3 class="m-0">Lista Provider</h3>
                        <IconField iconPosition="left">
                            <InputText
                                v-model="filter"
                                placeholder="Cerca dominio..."
                                size="small"
                                @input="onFilterChange"
                            />
                        </IconField>
                    </div>
                </template>

                <Column field="id" header="ID" style="width: 5%"></Column>
                <Column field="domain" header="Dominio"></Column>
                <Column field="logoutUrl" header="Logout URL">
                    <template #body="slotProps">
                        {{ slotProps.data.logoutUrl || "Default" }}
                    </template>
                </Column>
                <Column header="Azioni" :exportable="false" style="min-width: 8rem">
                    <template #body="slotProps">
                        <Button icon="pi pi-pencil" outlined class="me-2" @click="editProvider(slotProps.data)" />
                        <Button icon="pi pi-trash" outlined severity="danger" @click="confirmDelete(slotProps.data)" />
                    </template>
                </Column>

                <template #empty> Nessun provider trovato. </template>
            </DataTable>

            <Paginator :rows="pagination.per_page" :totalRecords="pagination.total" @page="onPage" class="mt-2" />
        </div>

        <Dialog
            v-model:visible="displayModal"
            :header="selectedProvider ? 'Modifica Provider' : 'Nuovo Provider'"
            :style="{ width: '60vw' }"
            :modal="true"
        >
            <ProviderForm :selectedProvider="selectedProvider" @provider-saved="onProviderSaved" />
        </Dialog>

        <Dialog
            v-model:visible="displayDeleteModal"
            header="Conferma Eliminazione"
            :style="{ width: '450px' }"
            :modal="true"
        >
            <div class="d-flex align-items-center">
                <i class="pi pi-exclamation-triangle me-3 text-warning" style="font-size: 2rem" />
                <span v-if="providerToDelete">
                    Sei sicuro di voler eliminare il provider <b>{{ providerToDelete.domain }}</b
                    >?
                </span>
            </div>
            <template #footer>
                <Button label="Annulla" icon="pi pi-times" text @click="displayDeleteModal = false" />
                <Button label="Elimina" icon="pi pi-check" severity="danger" @click="deleteProvider" />
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
import ProviderForm from "./ProviderForm.vue";

export default {
    name: "ProviderPage",
    components: { DataTable, Column, Paginator, IconField, InputText, Dialog, Button, ProviderForm },
    data() {
        return {
            filter: "",
            loading: false,
            pagination: { data: [], total: 0, per_page: 10 },
            displayModal: false,
            selectedProvider: null,
            searchTimeout: null,
            displayDeleteModal: false,
            providerToDelete: null,
        };
    },
    methods: {
        loadProviders(page = 1) {
            this.loading = true;
            const url = window.location.origin + "/admin/v1/providers";

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
                .catch((err) => console.error(err))
                .finally(() => (this.loading = false));
        },

        onPage(event) {
            this.loadProviders(event.page + 1);
        },

        onFilterChange() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.loadProviders(1);
            }, 500);
        },

        editProvider(provider) {
            this.selectedProvider = provider;
            this.displayModal = true;
        },

        openCreateModal() {
            this.selectedProvider = null;
            this.displayModal = true;
        },

        onProviderSaved() {
            this.displayModal = false;
            this.loadProviders();
        },

        confirmDelete(provider) {
            this.providerToDelete = provider;
            this.displayDeleteModal = true;
        },

        deleteProvider() {
            axios
                .delete("/admin/v1/providers/" + this.providerToDelete.id)
                .then((res) => {
                    this.displayDeleteModal = false;
                    this.loadProviders();
                    this.$toast.add({
                        severity: "success",
                        summary: "Successful",
                        detail: "Provider eliminato con successo",
                        life: 3000,
                    });
                })
                .catch((err) => {
                    console.error(err);
                    this.$toast.add({
                        severity: "error",
                        summary: "Errore",
                        detail: "Errore eliminazione provider",
                        life: 3000,
                    });
                });
        },
    },
    mounted() {
        this.loadProviders();
    },
};
</script>
