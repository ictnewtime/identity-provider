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

import RoleForm from "./RoleForm.vue";

const toast = useToast();

const filter = ref("");
const loading = ref(false);
const pagination = ref({ data: [], total: 0, per_page: 10 });
const displayModal = ref(false);
const selectedRole = ref(null);
const displayDeleteModal = ref(false);
const roleToDelete = ref(null);
let searchTimeout = null;

const loadRoles = (page = 1) => {
    loading.value = true;

    // Usiamo window.axios per sfruttare il token CSRF globale
    window.axios
        .get("/admin/v1/roles", {
            params: { page: page, per_page: pagination.value.per_page, q: filter.value },
        })
        .then((res) => {
            pagination.value = res.data;
        })
        .catch((err) => {
            toast.add({ severity: "error", summary: "Errore", detail: "Impossibile caricare i ruoli", life: 3000 });
        })
        .finally(() => {
            loading.value = false;
        });
};

const onPage = (event) => {
    loadRoles(event.page + 1);
};

const onFilterChange = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadRoles(1);
    }, 500);
};

// Funzione esposta al padre (es. Roles.vue) per aprire la modale
const openCreateModal = () => {
    selectedRole.value = null;
    displayModal.value = true;
};

// ESPONIAMO LA FUNZIONE
defineExpose({
    openCreateModal,
});

const onRoleSaved = () => {
    displayModal.value = false;
    loadRoles();
};

const editRole = (role) => {
    selectedRole.value = role;
    displayModal.value = true;
};

const confirmDelete = (role) => {
    roleToDelete.value = role;
    displayDeleteModal.value = true;
};

const deleteRole = () => {
    if (!roleToDelete.value) return;
    window.axios
        .delete(`/admin/v1/roles/${roleToDelete.value.id}`)
        .then(() => {
            displayDeleteModal.value = false;
            roleToDelete.value = null;
            loadRoles();
            toast.add({ severity: "success", summary: "Fatto", detail: "Ruolo eliminato correttamente", life: 3000 });
        })
        .catch((error) => {
            toast.add({ severity: "error", summary: "Errore", detail: "Errore durante l'eliminazione", life: 3000 });
        });
};

onMounted(() => {
    loadRoles();
});
</script>

<template>
    <div class="max-w-7xl mx-auto">
        <div class="bg-surface-0 border border-surface-200 rounded-xl shadow-sm p-4">
            <DataTable :value="pagination.data" :loading="loading" responsiveLayout="scroll" stripedRows size="small">
                <template #header>
                    <div class="flex justify-between items-center pb-2">
                        <h3 class="text-lg font-semibold m-0">Lista Ruoli</h3>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-search" />
                            <InputText
                                v-model="filter"
                                placeholder="Cerca ruolo o dominio..."
                                @input="onFilterChange"
                            />
                        </IconField>
                    </div>
                </template>

                <Column field="id" header="ID" style="width: 5%"></Column>
                <Column field="name" header="Nome Ruolo"></Column>
                <Column header="Provider (Dominio)">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.provider" class="text-surface-700 font-medium">
                            {{ slotProps.data.provider.domain }}
                        </span>
                        <span v-else class="text-surface-400 italic"> Nessun Provider </span>
                    </template>
                </Column>

                <Column header="Azioni" :exportable="false" style="min-width: 8rem">
                    <template #body="slotProps">
                        <Button
                            icon="pi pi-pencil"
                            outlined
                            severity="warn"
                            class="mr-2"
                            @click="editRole(slotProps.data)"
                        />
                        <Button icon="pi pi-trash" outlined severity="danger" @click="confirmDelete(slotProps.data)" />
                    </template>
                </Column>

                <template #empty>
                    <div class="text-center p-4 text-surface-500">Nessun ruolo trovato.</div>
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
            :header="selectedRole ? 'Modifica Ruolo' : 'Nuovo Ruolo'"
            :style="{ width: '60vw' }"
            modal
            :draggable="false"
        >
            <RoleForm :selectedRole="selectedRole" @role-saved="onRoleSaved" />
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
                <span v-if="roleToDelete" class="text-surface-700">
                    Sei sicuro di voler eliminare il ruolo <b class="text-surface-900">{{ roleToDelete.name }}</b
                    >?
                </span>
            </div>

            <template #footer>
                <Button label="Annulla" icon="pi pi-times" @click="displayDeleteModal = false" outlined />
                <Button label="Elimina" icon="pi pi-check" severity="danger" @click="deleteRole" />
            </template>
        </Dialog>
    </div>
</template>
