<template>
    <div class="web-user-container p-4">
        <div class="mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h1 class="text-3xl font-bold m-0">Gestione Utenti</h1>
                <p class="text-gray-600 mt-1 mb-0">Crea nuovi account e gestisci quelli esistenti</p>
            </div>
            <Button label="Nuovo Utente" icon="pi pi-plus" @click="openCreateModal" />
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
                        <h3 class="m-0">Lista Utenti</h3>
                        <IconField iconPosition="left">
                            <InputText
                                v-model="filter"
                                placeholder="Cerca email..."
                                size="small"
                                @input="onFilterChange"
                            />
                        </IconField>
                    </div>
                </template>

                <Column field="username" header="Username"></Column>
                <Column field="email" header="Email"></Column>
                <Column field="name" header="Nome"></Column>
                <Column field="surname" header="Cognome"></Column>
                <Column header="Azioni" :exportable="false" style="min-width: 8rem">
                    <template #body="slotProps">
                        <Button icon="pi pi-pencil" outlined class="me-2" @click="editUser(slotProps.data)" />
                        <Button icon="pi pi-trash" outlined severity="danger" @click="confirmDelete(slotProps.data)" />
                    </template>
                </Column>

                <template #empty> Nessun utente trovato. </template>
            </DataTable>

            <Paginator :rows="pagination.per_page" :totalRecords="pagination.total" @page="onPage" class="mt-2" />
        </div>

        <Dialog
            v-model:visible="displayModal"
            :header="selectedUser ? 'Modifica Utente' : 'Nuovo Utente'"
            :style="{ width: '60vw' }"
            :modal="true"
        >
            <UserForm :selectedUser="selectedUser" @user-created="onUserSaved" @user-updated="onUserSaved" />
        </Dialog>

        <Dialog
            v-model:visible="displayDeleteModal"
            header="Conferma Eliminazione"
            :style="{ width: '450px' }"
            :modal="true"
        >
            <div class="d-flex align-items-center">
                <i class="pi pi-exclamation-triangle me-3 text-warning" style="font-size: 2rem" />
                <span v-if="userToDelete">
                    Sei sicuro di voler eliminare l'utente <b>{{ userToDelete.name }}</b
                    >?
                </span>
            </div>
            <template #footer>
                <Button label="Annulla" icon="pi pi-times" text @click="displayDeleteModal = false" />
                <Button label="Elimina" icon="pi pi-check" severity="danger" @click="deleteUser" />
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
import UserForm from "./UserForm.vue";

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
        UserForm,
    },
    data() {
        return {
            filter: "",
            loading: false,
            pagination: { data: [], total: 0, per_page: 10 },
            displayModal: false,
            selectedUser: null,
            searchTimeout: null, // Per gestire il debounce sulla ricerca
            displayDeleteModal: false,
            userToDelete: null,
        };
    },
    methods: {
        loadUsers(page = 1) {
            this.loading = true;
            const url = window.location.origin + "/admin/v1/users";

            // Nota: Assumiamo che axios sia registrato globalmente.
            // In caso contrario, aggiungi: import axios from 'axios';
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

        onPage(event) {
            this.loadUsers(event.page + 1);
        },

        onFilterChange() {
            // Debounce: aspetta 500ms prima di fare la chiamata API per non intasare il server
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.loadUsers(1);
            }, 500);
        },

        openCreateModal() {
            this.selectedUser = null;
            this.displayModal = true;
        },

        onUserSaved() {
            this.displayModal = false; // Chiude la modale
            this.loadUsers(); // Ricarica la tabella
        },

        editUser(user) {
            this.selectedUser = user;
            this.displayModal = true;
        },

        confirmDelete(user) {
            this.userToDelete = user;
            this.displayDeleteModal = true;
        },

        deleteUser() {
            if (!this.userToDelete) return;
            axios
                .delete(`/admin/v1/users/${this.userToDelete.id}`)
                .then(() => {
                    this.displayDeleteModal = false;
                    this.userToDelete = null;
                    this.loadUsers();
                    this.$toast.add({
                        severity: "success",
                        summary: "Operazione completata",
                        detail: "Utente eliminato correttamente",
                        life: 3000,
                    });
                })
                .catch((error) => {
                    console.error(error);
                    this.$toast.add({
                        severity: "error",
                        summary: "Errore",
                        detail: "Errore eliminazione utente",
                        life: 3000,
                    });
                });
        },
    },
    mounted() {
        this.loadUsers();
    },
};
</script>

<style scoped>
.web-user-container {
    max-width: 1200px;
    margin: 0 auto;
}
</style>
