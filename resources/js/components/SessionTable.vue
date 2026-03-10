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

const toast = useToast();

const filter = ref("");
const loading = ref(false);
const pagination = ref({ data: [], total: 0, per_page: 10 });
const displayDeleteModal = ref(false);
const sessionToDelete = ref(null);
let searchTimeout = null;

const loadSessions = (page = 1) => {
    loading.value = true;

    window.axios
        .get("/admin/v1/sessions", {
            params: { page: page, per_page: pagination.value.per_page, q: filter.value },
        })
        .then((res) => {
            console.log(res.data);
            pagination.value = res.data;
        })
        .catch((err) => {
            console.error(err);
            toast.add({ severity: "error", summary: "Errore", detail: "Impossibile caricare le sessioni", life: 3000 });
        })
        .finally(() => {
            loading.value = false;
        });
};

const onPage = (event) => {
    loadSessions(event.page + 1);
};

const onFilterChange = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadSessions(1);
    }, 500);
};

const confirmDelete = (session) => {
    sessionToDelete.value = session;
    displayDeleteModal.value = true;
};

const deleteSession = () => {
    if (!sessionToDelete.value) return;

    window.axios
        .delete(`/admin/v1/sessions/${sessionToDelete.value.id}`)
        .then(() => {
            displayDeleteModal.value = false;
            sessionToDelete.value = null;
            loadSessions();
            toast.add({
                severity: "success",
                summary: "Fatto",
                detail: "Sessione terminata correttamente",
                life: 3000,
            });
        })
        .catch((error) => {
            console.error(error);
            toast.add({ severity: "error", summary: "Errore", detail: "Errore durante l'eliminazione", life: 3000 });
        });
};

onMounted(() => {
    loadSessions();
});
</script>

<template>
    <div class="max-w-7xl mx-auto">
        <div class="bg-surface-0 border border-surface-200 rounded-xl shadow-sm p-4">
            <DataTable :value="pagination.data" :loading="loading" responsiveLayout="scroll" stripedRows size="small">
                <template #header>
                    <div class="flex justify-between items-center pb-2">
                        <h3 class="text-lg font-semibold m-0">Lista Sessioni Attive</h3>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-search" />
                            <InputText v-model="filter" placeholder="Cerca" @input="onFilterChange" />
                        </IconField>
                    </div>
                </template>

                <Column header="ID">
                    <template #body="slotProps">
                        <span
                            class="inline-block max-w-[100px] sm:max-w-[150px] md:max-w-none truncate md:overflow-visible md:whitespace-nowrap text-surface-500"
                            v-tooltip.top="slotProps.data.id"
                        >
                            {{ slotProps.data.id }}
                        </span>
                    </template>
                </Column>
                <Column header="Username">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.user" class="text-surface-700 font-medium">
                            {{ slotProps.data.user.username }}
                        </span>
                        <span v-else class="text-surface-400 italic">Nessun Provider</span>
                    </template>
                </Column>

                <Column header="Provider (Dominio)">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.provider" class="text-surface-700 font-medium">
                            {{ slotProps.data.provider.domain }}
                        </span>
                        <span v-else class="text-surface-400 italic">Nessun Provider</span>
                    </template>
                </Column>

                <Column header="Azioni" :exportable="false" style="min-width: 8rem">
                    <template #body="slotProps">
                        <Button
                            icon="pi pi-trash"
                            outlined
                            severity="danger"
                            @click="confirmDelete(slotProps.data)"
                            v-tooltip.top="'Termina Sessione'"
                        />
                    </template>
                </Column>

                <template #empty>
                    <div class="text-center p-4 text-surface-500">Nessuna sessione attiva trovata.</div>
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
            v-model:visible="displayDeleteModal"
            header="Conferma Chiusura Sessione"
            :style="{ width: '450px' }"
            modal
            :draggable="false"
        >
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle text-amber-500 text-3xl"></i>
                <span v-if="sessionToDelete" class="text-surface-700">
                    Sei sicuro di voler chiudere la sessione di
                    <b class="text-surface-900">{{ sessionToDelete.username }}</b>
                    <span v-if="sessionToDelete.provider">
                        sul dominio <b class="text-surface-900">{{ sessionToDelete.provider.domain }}</b> </span
                    >?
                </span>
            </div>
            <template #footer>
                <Button label="Annulla" icon="pi pi-times" outlined @click="displayDeleteModal = false" />
                <Button label="Termina" icon="pi pi-power-off" severity="danger" @click="deleteSession" />
            </template>
        </Dialog>
    </div>
</template>
