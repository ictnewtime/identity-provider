<template>
    <div class="web-role-container p-4">
        <div class="mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h1 class="text-3xl font-bold m-0">Gestione Sessioni</h1>
                <p class="text-gray-600 mt-1 mb-0">Crea e assegna sessioni ai provider</p>
            </div>
            <Button label="Nuovo Ruolo" icon="pi pi-plus" @click="openCreateModal" />
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
                        <h3 class="m-0">Lista Sessioni</h3>
                        <IconField iconPosition="left">
                            <InputText
                                v-model="filter"
                                placeholder="Cerca per username o dominio"
                                size="small"
                                @input="onFilterChange"
                            />
                        </IconField>
                    </div>
                </template>

                <Column field="id" header="ID" style="width: 5%"></Column>
                <Column field="username" header="Username"></Column>
                <Column header="Provider (Dominio)">
                    <template #body="slotProps">
                        {{ slotProps.data.provider ? slotProps.data.provider.domain : "Nessun Provider" }}
                    </template>
                </Column>

                <Column header="Azioni" :exportable="false" style="min-width: 8rem">
                    <template #body="slotProps">
                        <Button icon="pi pi-trash" outlined severity="danger" @click="confirmDelete(slotProps.data)" />
                    </template>
                </Column>

                <template #empty> Nessuna sessione trovata. </template>
            </DataTable>

            <Paginator :rows="pagination.per_page" :totalRecords="pagination.total" @page="onPage" class="mt-2" />
        </div>
        <Dialog
            v-model:visible="displayDeleteModal"
            header="Conferma Eliminazione"
            :style="{ width: '450px' }"
            :modal="true"
        >
            <div class="d-flex align-items-center">
                <i class="pi pi-exclamation-triangle me-3 text-warning" style="font-size: 2rem" />
                <span v-if="sessionToDelete">
                    Sei sicuro di voler eliminare la sessione di <b>{{ sessionToDelete.username }}</b> per il dominio
                    <b>{{ sessionToDelete.provider.domain }}</b
                    >?
                </span>
            </div>
            <template #footer>
                <Button label="Annulla" icon="pi pi-times" text @click="displayDeleteModal = false" />
                <Button label="Elimina" icon="pi pi-check" severity="danger" @click="deleteSession" />
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

export default {
    name: "UserPage",
    components: {
        DataTable,
        Column,
        Paginator,
        IconField,
        InputText,
        Dialog,
        Button,
    },
    data() {
        return {
            filter: "",
            loading: false,
            pagination: { data: [], total: 0, per_page: 10 },
            displayModal: false,
            selectedSession: null,
            searchTimeout: null, // Per gestire il debounce sulla ricerca
            displayDeleteModal: false,
            sessionToDelete: null,
        };
    },
    methods: {
        openCreateModal() {
            this.displayModal = true;
        },
        confirmDelete(session) {
            this.sessionToDelete = session;
            this.displayDeleteModal = true;
        },
        deleteSession() {
            if (!this.sessionToDelete) return;
            axios
                .delete(`/admin/v1/sessions/${this.sessionToDelete.id}`)
                .then(() => {
                    this.displayDeleteModal = false;
                    this.sessionToDelete = null;
                    this.loadSessions();
                    this.$toast.add({
                        severity: "success",
                        summary: "Operazione completata",
                        detail: "Sessione eliminata correttamente",
                        life: 3000,
                    });
                })
                .catch((error) => {
                    console.error(error);
                    this.$toast.add({
                        severity: "error",
                        summary: "Errore",
                        detail: "Errore eliminazione sessione",
                        life: 3000,
                    });
                });
        },
    },
};
</script>
