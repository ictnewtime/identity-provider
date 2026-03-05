<template>
    <div class="web-role-container p-4">
        <div class="mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h1 class="text-3xl font-bold m-0">Gestione Ruoli</h1>
                <p class="text-gray-600 mt-1 mb-0">Crea e assegna ruoli ai provider</p>
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
                        <h3 class="m-0">Lista Ruoli</h3>
                        <IconField iconPosition="left">
                            <InputText
                                v-model="filter"
                                placeholder="Cerca ruolo o dominio..."
                                size="small"
                                @input="onFilterChange"
                            />
                        </IconField>
                    </div>
                </template>

                <Column field="id" header="ID" style="width: 5%"></Column>
                <Column field="name" header="Nome Ruolo"></Column>
                <Column header="Provider (Dominio)">
                    <template #body="slotProps">
                        {{ slotProps.data.provider ? slotProps.data.provider.domain : "Nessun Provider" }}
                    </template>
                </Column>

                <Column header="Azioni" :exportable="false" style="min-width: 8rem">
                    <template #body="slotProps">
                        <Button icon="pi pi-pencil" outlined class="me-2" @click="editRole(slotProps.data)" />
                        <Button icon="pi pi-trash" outlined severity="danger" @click="confirmDelete(slotProps.data)" />
                    </template>
                </Column>

                <template #empty> Nessun ruolo trovato. </template>
            </DataTable>

            <Paginator :rows="pagination.per_page" :totalRecords="pagination.total" @page="onPage" class="mt-2" />
        </div>

        <Dialog
            v-model:visible="displayModal"
            :header="selectedRole ? 'Modifica Ruolo' : 'Nuovo Ruolo'"
            :style="{ width: '50vw' }"
            :modal="true"
        >
            <RoleForm :selectedRole="selectedRole" @role-saved="onRoleSaved" />
        </Dialog>

        <Dialog
            v-model:visible="displayDeleteModal"
            header="Conferma Eliminazione"
            :style="{ width: '450px' }"
            :modal="true"
        >
            <div class="d-flex align-items-center">
                <i class="pi pi-exclamation-triangle me-3 text-warning" style="font-size: 2rem" />
                <span v-if="roleToDelete">
                    Sei sicuro di voler eliminare il ruolo <b>{{ roleToDelete.name }}</b
                    >?
                </span>
            </div>
            <template #footer>
                <Button label="Annulla" icon="pi pi-times" text @click="displayDeleteModal = false" />
                <Button label="Elimina" icon="pi pi-check" severity="danger" @click="deleteRole" />
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
import RoleForm from "./RoleForm.vue";

export default {
    name: "RolePage",
    components: { DataTable, Column, Paginator, IconField, InputText, Dialog, Button, RoleForm },
    data() {
        return {
            filter: "",
            loading: false,
            pagination: { data: [], total: 0, per_page: 10 },

            // Gestione Modale Form
            displayModal: false,
            selectedRole: null,

            // Gestione Modale Eliminazione
            displayDeleteModal: false,
            roleToDelete: null,

            searchTimeout: null,
        };
    },
    methods: {
        loadRoles(page = 1) {
            this.loading = true;
            axios
                .get("/admin/v1/roles", {
                    params: { page: page, per_page: this.pagination.per_page, q: this.filter },
                })
                .then((res) => (this.pagination = res.data))
                .finally(() => (this.loading = false));
        },
        onPage(event) {
            this.loadRoles(event.page + 1);
        },
        onFilterChange() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => this.loadRoles(1), 500);
        },

        // Apre la modale per un NUOVO ruolo
        openCreateModal() {
            this.selectedRole = null;
            this.displayModal = true;
        },

        // Apre la modale per MODIFICARE un ruolo
        editRole(role) {
            this.selectedRole = role;
            this.displayModal = true;
        },

        // Chiude la modale form e ricarica i dati
        onRoleSaved() {
            this.displayModal = false;
            this.loadRoles();
        },

        // Apre la modale di CONFERMA eliminazione
        confirmDelete(role) {
            this.roleToDelete = role;
            this.displayDeleteModal = true;
        },

        // Esegue l'eliminazione effettiva via API
        deleteRole() {
            if (!this.roleToDelete) return;

            axios
                .delete(`/admin/v1/roles/${this.roleToDelete.id}`)
                .then(() => {
                    this.$toast.add({
                        severity: "success",
                        summary: "Operazione completata",
                        detail: "Ruolo eliminato correttamente",
                        life: 3000,
                    });
                    this.displayDeleteModal = false;
                    this.roleToDelete = null;
                    this.loadRoles();
                })
                .catch((error) => {
                    console.error(error);
                    this.$toast.add({
                        severity: "error",
                        summary: "Errore",
                        detail: "Errore eliminazione ruolo",
                        life: 3000,
                    });
                });
        },
    },
    mounted() {
        this.loadRoles();
    },
};
</script>
