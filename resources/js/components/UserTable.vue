<script setup>
import { ref, reactive, onMounted } from "vue";
import { useToast } from "primevue/usetoast";
import { trans } from "laravel-vue-i18n";

import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Paginator from "primevue/paginator";
import InputText from "primevue/inputtext";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import Tag from "primevue/tag";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import UserForm from "./UserForm.vue";
import { Icon } from "@iconify/vue";
import { formatDate } from "../utils/data";

const emit = defineEmits(["item-saved", "item-error"]);
const toast = useToast();

const filter = ref("");
const loading = ref(false);
const pagination = ref({ data: [], total: 0, per_page: 10 });
const displayModal = ref(false);
const userSelected = ref(null);
const displayDeleteModal = ref(false);
const displayRestoreModal = ref(false);
const userToDelete = ref(null);
let searchTimeout = null;
const tableComponent = reactive({
    showUsersDeleted: false,
});

const loadUsers = (page = 1) => {
    loading.value = true;
    window.axios
        .get("/admin/v1/users", {
            params: {
                page: page,
                per_page: pagination.value.per_page,
                q: filter.value,
                show_deleted: tableComponent.showUsersDeleted,
            },
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
    userSelected.value = null;
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
    userSelected.value = user;
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
            loadUsers(pagination.value.current_page);
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

const confirmRestore = (user) => {
    userSelected.value = user;
    displayRestoreModal.value = true;
};
const restoreUser = () => {
    if (!userSelected.value) return;
    window.axios
        .patch(`/admin/v1/users/${userSelected.value.id}/restore`)
        .then(() => {
            displayRestoreModal.value = false;
            userSelected.value = null;
            loadUsers(pagination.value.current_page);
            toast.add({
                severity: "success",
                summary: trans("common.success"),
                detail: trans("admin.users.toast.restore_success"),
                life: 3000,
            });
            emit("item-saved");
        })
        .catch((error) => {
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.users.toast.restore_error"),
                life: 3000,
            });
            emit("item-error", error);
        });
};

const toggleShowUsersDeleted = () => {
    tableComponent.showUsersDeleted = !tableComponent.showUsersDeleted;
    loadUsers(1);
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
                        <div class="flex gap-4">
                            <Button
                                variant="text"
                                severity="danger"
                                @click="toggleShowUsersDeleted"
                                v-tooltip.top="
                                    tableComponent.showUsersDeleted
                                        ? $t('admin.providers.table.hide_deleted_tooltip')
                                        : $t('admin.providers.table.show_deleted_tooltip')
                                "
                            >
                                <Icon
                                    icon="material-symbols:delete-forever-outline-rounded"
                                    width="24"
                                    height="24"
                                    :class="tableComponent.showUsersDeleted ? 'text-red-500' : 'text-gray-500'"
                                />
                            </Button>
                            <IconField iconPosition="left">
                                <InputIcon class="pi pi-search text-surface-400" />
                                <InputText
                                    v-model="filter"
                                    :placeholder="$t('admin.users.table.search_placeholder')"
                                    @input="onFilterChange"
                                    class="rounded-lg!"
                                />
                            </IconField>
                        </div>
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

                <Column
                    field="deleted_at"
                    :header="$t('admin.users.table.deleted_at')"
                    v-if="tableComponent.showUsersDeleted === true"
                >
                    <template #body="slotProps">
                        <span class="text-surface-600">{{ formatDate(slotProps.data.deleted_at) }}</span>
                    </template>
                </Column>

                <Column :header="$t('common.actions')" :exportable="false" style="min-width: 8rem">
                    <template #body="slotProps">
                        <Button
                            icon="pi pi-pencil"
                            text
                            rounded
                            severity="warn"
                            class="mr-1 hover:!bg-orange-50"
                            @click="editUser(slotProps.data)"
                            ><Icon
                                icon="material-symbols:edit-outline"
                                width="24"
                                height="24"
                                class="text-yellow-400"
                            />
                        </Button>
                        <template v-if="slotProps.data.deleted_at">
                            <Button
                                icon="pi pi-undo"
                                text
                                rounded
                                severity="success"
                                class="mr-1 hover:!bg-green-50"
                                @click="confirmRestore(slotProps.data)"
                                ><Icon
                                    icon="material-symbols:restore-from-trash-outline-rounded"
                                    width="24"
                                    height="24"
                                    class="text-orange-500"
                                />
                            </Button>
                        </template>
                        <template v-else>
                            <Button
                                icon="pi pi-trash"
                                text
                                rounded
                                severity="danger"
                                class="hover:!bg-red-50"
                                @click="confirmDelete(slotProps.data)"
                                ><Icon icon="material-symbols:delete-outline-rounded" width="24" height="24" />
                            </Button>
                        </template>
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
            :header="userSelected ? $t('admin.users.form.title_edit') : $t('admin.users.form.title_create')"
            :style="{ width: '60vw', maxWidth: '800px' }"
            modal
            :draggable="false"
        >
            <UserForm :userSelected="userSelected" @item-saved="onUserSaved" />
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
        <Dialog
            v-model:visible="displayRestoreModal"
            :header="$t('admin.users.restore.title')"
            :style="{ width: '450px' }"
            modal
            :draggable="false"
        >
            <div class="flex items-center gap-4 pt-2">
                <i class="pi pi-exclamation-triangle text-red-500 text-4xl"></i>
                <span v-if="userSelected" class="text-surface-700">
                    {{ $t("admin.users.restore.prompt") }}
                    <b class="text-surface-900">{{ userSelected.username }}</b
                    >?
                </span>
            </div>
            <template #footer>
                <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="displayRestoreModal = false" />
                <Button
                    :label="$t('common.restore')"
                    icon="pi pi-check"
                    severity="danger"
                    @click="restoreUser([userSelected.id])"
                    autofocus
                />
            </template>
        </Dialog>
    </div>
</template>
