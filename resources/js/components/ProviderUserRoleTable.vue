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

import ProviderUserRoleForm from "./ProviderUserRoleForm.vue";

const toast = useToast();

const filter = ref("");
const loading = ref(false);
const pagination = ref({ data: [], total: 0, per_page: 10 });
const displayModal = ref(false);
const selectedItem = ref(null);
const displayDeleteModal = ref(false);
const itemToDelete = ref(null);
let searchTimeout = null;

const loadItems = (page = 1) => {
    loading.value = true;

    window.axios
        .get("/admin/v1/provider-user-roles", {
            params: { page: page, per_page: pagination.value.per_page, q: filter.value },
        })
        .then((res) => {
            pagination.value = res.data;
        })
        .catch((err) => {
            console.error(err);
            toast.add({
                severity: "error",
                summary: "Errore",
                detail: "Impossibile caricare le associazioni",
                life: 3000,
            });
        })
        .finally(() => {
            loading.value = false;
        });
};

const onPage = (event) => {
    loadItems(event.page + 1);
};

const onFilterChange = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadItems(1);
    }, 500);
};

const openCreateModal = () => {
    selectedItem.value = null;
    displayModal.value = true;
};

// Esponiamo la funzione al componente padre
defineExpose({
    openCreateModal,
});

const onItemSaved = () => {
    displayModal.value = false;
    loadItems();
};

const editItem = (item) => {
    selectedItem.value = item;
    displayModal.value = true;
};

const confirmDelete = (item) => {
    itemToDelete.value = item;
    displayDeleteModal.value = true;
};

const deleteItem = () => {
    if (!itemToDelete.value) return;

    window.axios
        .delete(`/admin/v1/provider-user-roles/${itemToDelete.value.id}`)
        .then(() => {
            displayDeleteModal.value = false;
            itemToDelete.value = null;
            loadItems();
            toast.add({
                severity: "success",
                summary: "Fatto",
                detail: "Associazione eliminata correttamente",
                life: 3000,
            });
        })
        .catch((error) => {
            console.error(error);
            toast.add({ severity: "error", summary: "Errore", detail: "Errore durante l'eliminazione", life: 3000 });
        });
};

onMounted(() => {
    loadItems();
});
</script>

<template>
    <div class="max-w-7xl mx-auto">
        <div class="bg-surface-0 border border-surface-200 rounded-xl shadow-sm p-4">
            <DataTable :value="pagination.data" :loading="loading" responsiveLayout="scroll" stripedRows size="small">
                <template #header>
                    <div class="flex justify-between items-center pb-2">
                        <h3 class="text-lg font-semibold m-0">Lista Associazioni</h3>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-search" />
                            <InputText
                                v-model="filter"
                                placeholder="Cerca email o dominio..."
                                @input="onFilterChange"
                            />
                        </IconField>
                    </div>
                </template>

                <Column field="id" header="ID" style="width: 5%"></Column>

                <Column header="Utente">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.user" class="font-medium text-surface-900">
                            {{ slotProps.data.user.email }}
                        </span>
                        <span v-else class="text-surface-400 italic">Utente mancante</span>
                    </template>
                </Column>

                <Column header="Provider">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.provider" class="text-surface-700">
                            {{ slotProps.data.provider.domain }}
                        </span>
                        <span v-else class="text-surface-400 italic">Provider mancante</span>
                    </template>
                </Column>

                <Column header="Ruolo">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.role" class="text-surface-700">
                            {{ slotProps.data.role.name }}
                        </span>
                        <span v-else class="text-surface-400 italic">Ruolo mancante</span>
                    </template>
                </Column>

                <Column header="Azioni" :exportable="false" style="min-width: 8rem">
                    <template #body="slotProps">
                        <Button
                            icon="pi pi-pencil"
                            outlined
                            severity="warn"
                            class="mr-2"
                            @click="editItem(slotProps.data)"
                        />
                        <Button icon="pi pi-trash" outlined severity="danger" @click="confirmDelete(slotProps.data)" />
                    </template>
                </Column>

                <template #empty>
                    <div class="text-center p-4 text-surface-500">Nessuna associazione trovata.</div>
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
            :header="selectedItem ? 'Modifica Associazione' : 'Nuova Associazione'"
            :style="{ width: '60vw' }"
            modal
            :draggable="false"
        >
            <ProviderUserRoleForm :selectedItem="selectedItem" @item-saved="onItemSaved" />
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
                <span v-if="itemToDelete" class="text-surface-700">
                    Sei sicuro di voler rimuovere l'associazione per l'utente
                    <b class="text-surface-900">{{ itemToDelete.user ? itemToDelete.user.email : "Selezionato" }}</b
                    >?
                </span>
            </div>
            <template #footer>
                <Button label="Annulla" icon="pi pi-times" outlined @click="displayDeleteModal = false" />
                <Button label="Elimina" icon="pi pi-check" severity="danger" @click="deleteItem" />
            </template>
        </Dialog>
    </div>
</template>
