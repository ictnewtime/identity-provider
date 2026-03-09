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

import UserForm from "./UserForm.vue";

const toast = useToast();

const filter = ref("");
const loading = ref(false);
const pagination = ref({ data: [], total: 0, per_page: 10 });
const displayModal = ref(false);
const selectedUser = ref(null);
const displayDeleteModal = ref(false);
const userToDelete = ref(null);
let searchTimeout = null;

const loadUsers = (page = 1) => {
    loading.value = true;
    axios
        .get("/admin/v1/users", {
            params: { page: page, per_page: pagination.value.per_page, q: filter.value },
        })
        .then((res) => {
            pagination.value = res.data;
        })
        .catch((err) => {
            toast.add({ severity: "error", summary: "Errore", detail: "Impossibile caricare gli utenti", life: 3000 });
        })
        .finally(() => {
            loading.value = false;
        });
};

const onPage = (event) => {
    loadUsers(event.page + 1);
};

const onFilterChange = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadUsers(1);
    }, 500);
};

const openCreateModal = () => {
    selectedUser.value = null;
    displayModal.value = true;
};

defineExpose({
    openCreateModal,
});

const onUserSaved = () => {
    displayModal.value = false;
    loadUsers();
};

const editUser = (user) => {
    selectedUser.value = user;
    displayModal.value = true;
};

const confirmDelete = (user) => {
    userToDelete.value = user;
    displayDeleteModal.value = true;
};

const deleteUser = () => {
    if (!userToDelete.value) return;
    axios
        .delete(`/admin/v1/users/${userToDelete.value.id}`)
        .then(() => {
            displayDeleteModal.value = false;
            userToDelete.value = null;
            loadUsers();
            toast.add({ severity: "success", summary: "Fatto", detail: "Utente eliminato correttamente", life: 3000 });
        })
        .catch((error) => {
            toast.add({ severity: "error", summary: "Errore", detail: "Errore durante l'eliminazione", life: 3000 });
        });
};

onMounted(() => {
    loadUsers();
});
</script>

<template>
    <div class="max-w-7xl mx-auto">
        <div class="bg-surface-0 border border-surface-200 rounded-xl shadow-sm p-4">
            <DataTable :value="pagination.data" :loading="loading" responsiveLayout="scroll" stripedRows size="small">
                <template #header>
                    <div class="flex justify-between items-center pb-2">
                        <h3 class="text-lg font-semibold m-0">{{ $t("user_table.title") }}</h3>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-search" />
                            <InputText
                                v-model="filter"
                                :placeholder="$t('user_table.search_placeholder')"
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
                        <!-- color yellow -->
                        <Button
                            icon="pi pi-pencil"
                            outlined
                            severity="warn"
                            class="mr-2"
                            @click="editUser(slotProps.data)"
                        />
                        <Button icon="pi pi-trash" outlined severity="danger" @click="confirmDelete(slotProps.data)" />
                    </template>
                </Column>

                <template #empty>
                    <div class="text-center p-4 text-surface-500">{{ $t("user_table.empty") }}</div>
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
            :header="$t(selectedUser ? 'user_form.title_edit' : 'user_form.title_create')"
            :style="{ width: '60vw' }"
            modal
            :draggable="false"
        >
            <UserForm :selectedUser="selectedUser" @user-created="onUserSaved" @user-updated="onUserSaved" />
        </Dialog>

        <Dialog
            v-model:visible="displayDeleteModal"
            :header="$t('confirm_delete')"
            :style="{ width: '450px' }"
            modal
            :draggable="false"
        >
            <template #footer>
                <Button :label="$t('cancel')" icon="pi pi-times" @click="displayDeleteModal = false" />
                <Button :label="$t('delete')" icon="pi pi-check" severity="danger" @click="deleteUser" />
            </template>
        </Dialog>
    </div>
</template>
