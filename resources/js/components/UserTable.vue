<script setup>
import { ref, onMounted } from "vue";
import { useToast } from "primevue/usetoast";
import { trans } from "laravel-vue-i18n";

import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Paginator from "primevue/paginator";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import InputText from "primevue/inputtext";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import Tag from "primevue/tag";

import UserForm from "./UserForm.vue";

const emit = defineEmits(["item-saved", "item-error"]);
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
    window.axios
        .get("/admin/v1/users", {
            params: { page: page, per_page: pagination.value.per_page, q: filter.value },
        })
        .then((res) => {
            pagination.value = res.data;
        })
        .catch((err) => {
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.users.toast.load_error"),
                life: 3000,
            });
            emit("item-error", err);
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
    window.axios
        .delete(`/admin/v1/users/${userToDelete.value.id}`)
        .then(() => {
            displayDeleteModal.value = false;
            userToDelete.value = null;
            loadUsers();
            toast.add({
                severity: "success",
                summary: trans("common.success"),
                detail: trans("admin.users.toast.delete_success"),
                life: 3000,
            });
            emit("item-saved");
        })
        .catch((error) => {
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.users.toast.delete_error"),
                life: 3000,
            });
            emit("item-error", error);
        });
};

onMounted(() => {
    loadUsers();
});
</script>

<template>
    <div>
        <div class="bg-white rounded-2xl shadow-[0_2px_12px_rgba(0,0,0,0.03)] p-5 md:p-6">
            <DataTable :value="pagination.data" :loading="loading" responsiveLayout="scroll" stripedRows size="small">
                <template #header>
                    <div class="flex flex-col sm:flex-row justify-between items-center pb-4 gap-4">
                        <h3 class="text-lg font-semibold m-0 text-surface-800">
                            {{ $t("admin.users.table.title") }}
                        </h3>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-search text-surface-400" />
                            <InputText
                                v-model="filter"
                                :placeholder="$t('admin.users.table.search_placeholder')"
                                @input="onFilterChange"
                                class="!rounded-lg"
                            />
                        </IconField>
                    </div>
                </template>

                <Column field="username" :header="$t('admin.users.table.username')">
                    <template #body="slotProps">
                        <span class="font-medium text-surface-900">{{ slotProps.data.username }}</span>
                    </template>
                </Column>

                <Column field="email" :header="$t('admin.users.table.email')">
                    <template #body="slotProps">
                        <span class="text-surface-600">{{ slotProps.data.email }}</span>
                    </template>
                </Column>

                <Column field="enabled" :header="$t('admin.users.table.status')">
                    <template #body="slotProps">
                        <Tag
                            :severity="slotProps.data.enabled ? 'success' : 'danger'"
                            :value="
                                (slotProps.data.enabled
                                    ? $t('admin.users.table.status_active')
                                    : $t('admin.users.table.status_blocked')
                                ).toUpperCase()
                            "
                        />
                    </template>
                </Column>

                <Column field="name" :header="$t('admin.users.table.name')"></Column>
                <Column field="surname" :header="$t('admin.users.table.surname')"></Column>

                <Column :header="$t('common.actions')" :exportable="false" style="min-width: 8rem">
                    <template #body="slotProps">
                        <Button
                            icon="pi pi-pencil"
                            text
                            rounded
                            severity="warn"
                            class="mr-1 hover:!bg-orange-50"
                            @click="editUser(slotProps.data)"
                        />
                        <Button
                            icon="pi pi-trash"
                            text
                            rounded
                            severity="danger"
                            class="hover:!bg-red-50"
                            @click="confirmDelete(slotProps.data)"
                        />
                    </template>
                </Column>

                <template #empty>
                    <div class="text-center p-8 text-surface-500">
                        <i class="pi pi-users text-4xl mb-4 text-surface-300"></i>
                        <p class="m-0">{{ $t("admin.users.table.empty") }}</p>
                    </div>
                </template>
            </DataTable>

            <Paginator
                v-if="pagination.total > 0"
                :rows="pagination.per_page"
                :totalRecords="pagination.total"
                @page="onPage"
                class="mt-4 border-t border-surface-100 pt-4"
            />
        </div>

        <Dialog
            v-model:visible="displayModal"
            :header="selectedUser ? $t('admin.users.form.title_edit') : $t('admin.users.form.title_create')"
            :style="{ width: '60vw', maxWidth: '800px' }"
            modal
            :draggable="false"
        >
            <UserForm :selectedUser="selectedUser" @item-saved="onUserSaved" />
        </Dialog>

        <Dialog
            v-model:visible="displayDeleteModal"
            :header="$t('common.confirm_delete_title')"
            :style="{ width: '450px' }"
            modal
            :draggable="false"
        >
            <div class="flex items-center gap-4 pt-2">
                <i class="pi pi-exclamation-triangle text-red-500 text-4xl"></i>
                <span v-if="userToDelete" class="text-surface-700">
                    {{ $t("admin.users.delete.prompt") }}
                    <b class="text-surface-900">{{ userToDelete.username }}</b
                    >?
                </span>
            </div>
            <template #footer>
                <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="displayDeleteModal = false" />
                <Button
                    :label="$t('common.delete')"
                    icon="pi pi-check"
                    severity="danger"
                    @click="deleteUser"
                    autofocus
                />
            </template>
        </Dialog>
    </div>
</template>
