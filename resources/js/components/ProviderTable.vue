<script setup>
import { ref, onMounted } from "vue";
import { useToast } from "primevue/usetoast";

import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Paginator from "primevue/paginator";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import InputText from "primevue/inputtext";
import Dialog from "primevue/dialog";
import Button from "primevue/button";

import ProviderForm from "./ProviderForm.vue";

const toast = useToast();

const filter = ref("");
const loading = ref(false);
const pagination = ref({ data: [], total: 0, per_page: 10 });
const displayModal = ref(false);
const selectedProvider = ref(null);
const displayDeleteModal = ref(false);
const providerToDelete = ref(null);
let searchTimeout = null;

const loadProviders = (page = 1) => {
    loading.value = true;

    window.axios
        .get("/admin/v1/providers", {
            params: { page: page, per_page: pagination.value.per_page, q: filter.value },
        })
        .then((res) => {
            pagination.value = res.data;
        })
        .catch((err) => {
            console.error(err);
            toast.add({ severity: "error", summary: "Errore", detail: "Impossibile caricare i provider", life: 3000 });
        })
        .finally(() => {
            loading.value = false;
        });
};

const onPage = (event) => {
    loadProviders(event.page + 1);
};

const onFilterChange = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadProviders(1);
    }, 500);
};

const openCreateModal = () => {
    selectedProvider.value = null;
    displayModal.value = true;
};

// Esponiamo la funzione al padre (Providers.vue)
defineExpose({
    openCreateModal,
});

const onProviderSaved = () => {
    displayModal.value = false;
    loadProviders();
};

const editProvider = (provider) => {
    selectedProvider.value = provider;
    displayModal.value = true;
};

const confirmDelete = (provider) => {
    providerToDelete.value = provider;
    displayDeleteModal.value = true;
};

const deleteProvider = () => {
    if (!providerToDelete.value) return;

    window.axios
        .delete(`/admin/v1/providers/${providerToDelete.value.id}`)
        .then(() => {
            displayDeleteModal.value = false;
            providerToDelete.value = null;
            loadProviders();
            toast.add({
                severity: "success",
                summary: "Fatto",
                detail: "Provider eliminato correttamente",
                life: 3000,
            });
        })
        .catch((error) => {
            console.error(error);
            toast.add({ severity: "error", summary: "Errore", detail: "Errore durante l'eliminazione", life: 3000 });
        });
};

onMounted(() => {
    loadProviders();
});
</script>

<template>
    <div class="max-w-7xl mx-auto">
        <div class="bg-surface-0 border border-surface-200 rounded-xl shadow-sm p-4">
            <DataTable :value="pagination.data" :loading="loading" responsiveLayout="scroll" stripedRows size="small">
                <template #header>
                    <div class="flex justify-between items-center pb-2">
                        <h3 class="text-lg font-semibold m-0">Lista Provider</h3>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-search" />
                            <InputText v-model="filter" placeholder="Cerca dominio..." @input="onFilterChange" />
                        </IconField>
                    </div>
                </template>

                <Column field="id" header="ID" style="width: 5%"></Column>
                <Column field="domain" header="Dominio"></Column>
                <Column field="logoutUrl" header="Logout URL">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.logoutUrl">{{ slotProps.data.logoutUrl }}</span>
                        <span v-else class="text-surface-400 italic">Default</span>
                    </template>
                </Column>

                <Column header="Azioni" :exportable="false" style="min-width: 8rem">
                    <template #body="slotProps">
                        <Button
                            icon="pi pi-pencil"
                            outlined
                            severity="warn"
                            class="mr-2"
                            @click="editProvider(slotProps.data)"
                        />
                        <Button icon="pi pi-trash" outlined severity="danger" @click="confirmDelete(slotProps.data)" />
                    </template>
                </Column>

                <template #empty>
                    <div class="text-center p-4 text-surface-500">Nessun provider trovato.</div>
                </template>
            </DataTable>

            <Paginator
                v-if="pagination.total > 0"
                :rows="pagination.per_page"
                :totalRecords="pagination.total"
                @page="onPage"
                class="mt-4"
            />
        </div>

        <Dialog
            v-model:visible="displayModal"
            :header="selectedProvider ? 'Modifica Provider' : 'Nuovo Provider'"
            :style="{ width: '60vw' }"
            modal
            :draggable="false"
        >
            <ProviderForm :selectedProvider="selectedProvider" @provider-saved="onProviderSaved" />
        </Dialog>

        <Dialog
            v-model:visible="displayDeleteModal"
            header="Conferma Eliminazione"
            :style="{ width: '450px' }"
            modal
            :draggable="false"
        >
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle text-amber-500 text-3xl"></i>
                <span v-if="providerToDelete" class="text-surface-700">
                    Sei sicuro di voler eliminare il provider
                    <b class="text-surface-900">{{ providerToDelete.domain }}</b
                    >?
                </span>
            </div>
            <template #footer>
                <Button label="Annulla" icon="pi pi-times" outlined @click="displayDeleteModal = false" />
                <Button label="Elimina" icon="pi pi-check" severity="danger" @click="deleteProvider" />
            </template>
        </Dialog>
    </div>
</template>
