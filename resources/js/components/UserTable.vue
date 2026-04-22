<script setup>
import { ref, reactive, onMounted, computed } from "vue";
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
const displayDeleteModal = ref(false);
const displayRestoreModal = ref(false);
const itemSelected = ref(null);
const selectedUsers = ref();
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
    itemSelected.value = null;
    displayModal.value = true;
};

defineExpose({
    openCreateModal,
});

const getSelectedUsers = () => {
    let rawData = [];
    try {
        rawData = JSON.parse(JSON.stringify(selectedUsers.value));
    } catch (error) {
        console.error(error);
    }
    return rawData;
};

const onUserSaved = () => {
    displayModal.value = false;
    loadUsers();
};

const editUser = (user) => {
    itemSelected.value = user;
    displayModal.value = true;
};

const confirmDelete = (item) => {
    itemSelected.value = item;
    itemSelected.value = {
        ids: [item.id],
        body: "l' utente: " + item.username,
    };
    displayDeleteModal.value = true;
};
const confirmRestore = (item) => {
    itemSelected.value = item;
    itemSelected.value = {
        ids: [item.id],
        body: "l' utente: " + item.username,
    };
    displayRestoreModal.value = true;
};

const confirmRestoreSelectedUsers = () => {
    const rawData = getSelectedUsers();
    const ids = rawData.map((item) => item.id);
    itemSelected.value = {
        ids: ids,
        body: "gli utenti con ids: " + ids.join(","),
    };
    displayRestoreModal.value = true;
};
const confirmDeleteSelectedUsers = () => {
    const rawData = getSelectedUsers();
    const ids = rawData.map((item) => item.id);
    itemSelected.value = ids;
    itemSelected.value = {
        ids: ids,
        body: "gli utenti con ids: " + ids.join(","),
    };

    console.log(itemSelected.value.body);
    console.log(itemSelected.value.ids);
    displayDeleteModal.value = true;
};

const deleteUsers = (ids) => {
    console.log("deleteUsers ids", ids);
    if (!ids || ids.length === 0) return;
    window.axios
        .delete("/admin/v1/users/bulk-delete", { data: { ids } })
        .then(() => {
            displayDeleteModal.value = false;
            itemSelected.value = null;
            selectedUsers.value = [];
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
            console.error(error);
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.users.toast.delete_error"),
                life: 3000,
            });
            emit("item-error", error);
        });
};

const restoreUsers = (ids) => {
    if (!ids || ids.length === 0) return;

    window.axios
        .patch("/admin/v1/users/bulk-restore", { ids })
        .then(() => {
            displayRestoreModal.value = false;
            itemSelected.value = null;
            selectedUsers.value = [];
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
            console.error(error);
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.users.toast.restore_error"),
                life: 3000,
            });
            emit("item-error", error);
        });
};

const deleteSelectedUsers = () => {
    const rawData = getSelectedUsers();
    const ids = rawData.map((item) => item.id);
    deleteUsers(ids);
};

const restoreSelectedUsers = () => {
    const rawData = getSelectedUsers();
    const ids = rawData.map((item) => item.id);
    restoreUsers(ids);
};

const toggleShowUsersDeleted = () => {
    tableComponent.showUsersDeleted = !tableComponent.showUsersDeleted;
    selectedUsers.value = [];
    loadUsers(1);
};

onMounted(() => {
    loadUsers();
});

const hasSelectedUsers = computed(() => {
    return selectedUsers.value && selectedUsers.value.length > 0;
});
</script>

<template>
    <div>
        <div class="bg-white rounded-2xl shadow-[0_2px_12px_rgba(0,0,0,0.03)] p-5 md:p-6">
            <DataTable
                :value="pagination.data"
                :loading="loading"
                responsiveLayout="scroll"
                stripedRows
                size="small"
                v-model:selection="selectedUsers"
            >
                <template #header>
                    <div class="flex flex-col sm:flex-row justify-between items-center pb-4 gap-4">
                        <h3 class="text-lg font-semibold m-0 text-surface-800">
                            {{ $t("admin.users.table.title") }}
                        </h3>
                        <div class="flex gap-4">
                            <Button
                                v-if="hasSelectedUsers && !tableComponent.showUsersDeleted"
                                variant="text"
                                severity="danger"
                                @click="confirmDeleteSelectedUsers"
                                v-tooltip.top="$t('admin.users.table.delete_selected_tooltip')"
                                ><Icon icon="material-symbols:delete-outline-rounded" width="24" height="24" />
                            </Button>
                            <Button
                                v-if="hasSelectedUsers && tableComponent.showUsersDeleted"
                                variant="text"
                                severity="warn"
                                @click="confirmRestoreSelectedUsers"
                                v-tooltip.top="$t('admin.users.table.restore_selected_tooltip')"
                                ><Icon
                                    icon="material-symbols:restore-from-trash-outline-rounded"
                                    width="24"
                                    height="24"
                                    class="text-orange-500"
                                />
                            </Button>
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

                <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>

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
            :header="itemSelected ? $t('admin.users.form.title_edit') : $t('admin.users.form.title_create')"
            :style="{ width: '60vw', maxWidth: '800px' }"
            modal
            :draggable="false"
        >
            <UserForm :itemSelected="itemSelected" @item-saved="onUserSaved" />
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
                <span v-if="itemSelected" class="text-surface-700">
                    {{ $t("admin.users.delete.prompt") }}
                    <b class="text-surface-900">{{ itemSelected.body }}</b
                    >?
                </span>
            </div>
            <template #footer>
                <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="displayDeleteModal = false" />
                <Button
                    :label="$t('common.delete')"
                    icon="pi pi-check"
                    severity="danger"
                    @click="deleteUsers(itemSelected.ids)"
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
                <span v-if="itemSelected" class="text-surface-700">
                    {{ $t("admin.users.restore.prompt") }}
                    <b class="text-surface-900">{{ itemSelected.body }}</b
                    >?
                </span>
            </div>
            <template #footer>
                <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="displayRestoreModal = false" />
                <Button
                    :label="$t('common.restore')"
                    icon="pi pi-check"
                    severity="danger"
                    @click="restoreUsers(itemSelected.ids)"
                    autofocus
                />
            </template>
        </Dialog>
    </div>
</template>
