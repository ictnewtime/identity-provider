<script setup>
import { ref, reactive, onMounted, computed } from "vue";
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
import ProviderUserRoleForm from "./ProviderUserRoleForm.vue";
import DeleteProviderUserRoleDialog from "./provider-user-role/DeleteProviderUserRoleDialog.vue";
import DeleteProviderUserRolesDialog from "./provider-user-role/DeleteProviderUserRolesDialog.vue";
import RestoreProviderUserRoleDialog from "./provider-user-role/RestoreProviderUserRoleDialog.vue";
import RestoreProviderUserRolesDialog from "./provider-user-role/RestoreProviderUserRolesDialog.vue";
import { Icon } from "@iconify/vue";
import { formatDate } from "../utils/data";

const emit = defineEmits(["item-success", "item-error"]);
const toast = useToast();

const filter = ref("");
const loading = ref(false);
const pagination = ref({ data: [], total: 0, per_page: 10 });
const displayModal = ref(false);
const itemSelected = ref(null);
const displayDeleteModal = ref(false);
const displayDeleteItemModal = ref(false);
const displayDeleteItemsModal = ref(false);
const displayRestoreModal = ref(false);
const displayRestoreItemModal = ref(false);
const displayRestoreItemsModal = ref(false);
const selectedProviderUserRoles = ref();
let searchTimeout = null;
const tableComponent = reactive({
    showRecordsDeleted: false,
});

const loadRecords = (page = 1) => {
    loading.value = true;

    window.axios
        .get("/admin/v1/provider-user-roles", {
            params: {
                page: page,
                per_page: pagination.value.per_page,
                q: filter.value,
                show_deleted: tableComponent.showRecordsDeleted,
            },
        })
        .then((res) => {
            pagination.value = res.data;
        })
        .catch((err) => {
            console.error(err);
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.provider_user_roles.toast.load_error"),
                life: 3000,
            });
            emit("item-error", err);
        })
        .finally(() => {
            loading.value = false;
        });
};

const onPage = (event) => {
    loadRecords(event.page + 1);
};

const onFilterChange = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadRecords(1);
    }, 500);
};

const openCreateModal = () => {
    itemSelected.value = null;
    displayModal.value = true;
};

defineExpose({
    openCreateModal,
});

const onItemSaved = () => {
    displayModal.value = false;
    loadRecords();
};

const editItem = (item) => {
    itemSelected.value = item;
    displayModal.value = true;
};

const confirmDelete = (item) => {
    console.log(item);
    itemSelected.value = item;
    displayDeleteItemModal.value = true;
};
const confirmRestore = (item) => {
    console.log(item);
    itemSelected.value = item;
    displayRestoreItemModal.value = true;
};

const confirmDeleteSelectedProviderUserRoles = () => {
    const rawData = getSelectedProviderUserRoles();
    const ids = rawData.map((item) => item.id);
    itemSelected.value = { ids: ids };
    displayDeleteItemsModal.value = true;
};

const confirmRestoreSelectedProviderUserRoles = () => {
    const rawData = getSelectedProviderUserRoles();
    const ids = rawData.map((item) => item.id);
    itemSelected.value = { ids: ids };
    displayRestoreItemsModal.value = true;
};

const toggleShowRecordsDeleted = () => {
    tableComponent.showRecordsDeleted = !tableComponent.showRecordsDeleted;
    selectedProviderUserRoles.value = [];
    loadRecords(1);
};

const onModalSuccess = () => {
    itemSelected.value = null;
    selectedProviderUserRoles.value = [];
    loadRecords(pagination.value.current_page);
};

const deleteSelectedProviderUserRoles = () => {
    const rawData = getSelectedProviderUserRoles();
    const ids = rawData.map((item) => item.id);
    deleteProviderUserRoles(ids);
};

const getSelectedProviderUserRoles = () => {
    let rawData = [];
    try {
        rawData = JSON.parse(JSON.stringify(selectedProviderUserRoles.value));
    } catch (error) {
        console.error(error);
    }
    return rawData;
};

const hasSelectedProviderUserRoles = computed(() => {
    return selectedProviderUserRoles.value && selectedProviderUserRoles.value.length > 0;
});

onMounted(() => {
    loadRecords();
});
</script>

<template>
    <div>
        <div class="bg-white rounded-2xl shadow-[0_2px_12px_rgba(0,0,0,0.03)] p-5 md:p-6">
            <DataTable
                v-model:selection="selectedProviderUserRoles"
                :value="pagination.data"
                dataKey="id"
                :loading="loading"
                responsiveLayout="scroll"
                stripedRows
                size="small"
            >
                <template #header>
                    <div class="flex flex-col sm:flex-row justify-between items-center pb-4 gap-4">
                        <h3 class="text-lg font-semibold m-0 text-surface-800">
                            {{ $t("admin.provider_user_roles.table.title") }}
                        </h3>
                        <div class="flex gap-4">
                            <Button
                                v-if="hasSelectedProviderUserRoles && !tableComponent.showRecordsDeleted"
                                variant="text"
                                severity="danger"
                                @click="confirmDeleteSelectedProviderUserRoles"
                                v-tooltip.top="$t('admin.provider_user_roles.table.delete_selected_tooltip')"
                                ><Icon icon="material-symbols:delete-outline-rounded" width="24" height="24" />
                            </Button>
                            <Button
                                v-if="hasSelectedProviderUserRoles && tableComponent.showRecordsDeleted"
                                variant="text"
                                severity="warn"
                                @click="confirmRestoreSelectedProviderUserRoles"
                                v-tooltip.top="$t('admin.provider_user_roles.table.restore_selected_tooltip')"
                                ><Icon
                                    icon="material-symbols:restore-from-trash-outline-rounded"
                                    width="24"
                                    height="24"
                                    class="text-orange-500"
                                />
                            </Button>
                            <Button
                                variant="text"
                                @click="toggleShowRecordsDeleted"
                                v-tooltip.top="
                                    tableComponent.showRecordsDeleted
                                        ? $t('admin.provider_user_roles.table.hide_deleted_tooltip')
                                        : $t('admin.provider_user_roles.table.show_deleted_tooltip')
                                "
                            >
                                <Icon
                                    icon="material-symbols:delete-forever-outline-rounded"
                                    width="24"
                                    height="24"
                                    :class="tableComponent.showRecordsDeleted ? 'text-red-500' : 'text-gray-500'"
                                />
                            </Button>
                            <IconField iconPosition="left">
                                <InputIcon class="pi pi-search text-surface-400" />
                                <InputText
                                    v-model="filter"
                                    :placeholder="$t('admin.provider_user_roles.table.search_placeholder')"
                                    @input="onFilterChange"
                                    class="rounded-lg!"
                                />
                            </IconField>
                        </div>
                    </div>
                </template>

                <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>

                <Column :header="$t('admin.provider_user_roles.table.user')">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.user" class="font-bold text-surface-900">
                            {{ slotProps.data.user.username }}
                        </span>
                        <span v-else class="text-surface-400 italic">{{
                            $t("admin.provider_user_roles.table.missing_user")
                        }}</span>
                    </template>
                </Column>

                <Column :header="$t('admin.provider_user_roles.table.provider')">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.provider" class="text-surface-700 font-medium">
                            {{ slotProps.data.provider.name }}
                        </span>
                        <span v-else class="text-surface-400 italic">{{
                            $t("admin.provider_user_roles.table.missing_provider")
                        }}</span>
                    </template>
                </Column>

                <Column :header="$t('admin.provider_user_roles.table.role')">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.role" class="text-surface-600">
                            {{ slotProps.data.role.name }}
                        </span>
                        <span v-else class="text-surface-400 italic">{{
                            $t("admin.provider_user_roles.table.missing_role")
                        }}</span>
                    </template>
                </Column>
                <Column
                    field="deleted_at"
                    :header="$t('admin.provider_user_roles.table.deleted_at')"
                    v-if="tableComponent.showRecordsDeleted === true"
                >
                    <template #body="slotProps">
                        <span class="text-surface-600">{{ formatDate(slotProps.data.deleted_at) }}</span>
                    </template>
                </Column>

                <Column :header="$t('common.actions')" :exportable="false" style="min-width: 8rem">
                    <template #body="slotProps">
                        <Button text rounded class="mr-1 hover:!bg-orange-50" @click="editItem(slotProps.data)"
                            ><Icon
                                icon="material-symbols:edit-outline"
                                width="24"
                                height="24"
                                class="text-yellow-400"
                            />
                        </Button>
                        <template v-if="slotProps.data.deleted_at">
                            <Button
                                text
                                rounded
                                severity="warn"
                                class="hover:!bg-red-50"
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
                        <i class="pi pi-link text-4xl mb-4 text-surface-300"></i>
                        <p class="m-0">{{ $t("admin.provider_user_roles.table.empty") }}</p>
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
            :header="
                itemSelected
                    ? $t('admin.provider_user_roles.form.title_edit')
                    : $t('admin.provider_user_roles.form.title_create')
            "
            :style="{ width: '60vw', maxWidth: '800px' }"
            modal
            :draggable="false"
        >
            <ProviderUserRoleForm :itemSelected="itemSelected" @item-success="onItemSaved" />
        </Dialog>

        <DeleteProviderUserRoleDialog
            v-model:visible="displayDeleteItemModal"
            :itemSelected="itemSelected"
            @item-success="onModalSuccess"
        />
        <RestoreProviderUserRoleDialog
            v-model:visible="displayRestoreItemModal"
            :itemSelected="itemSelected"
            @item-success="onModalSuccess"
        />
        <DeleteProviderUserRolesDialog
            v-model:visible="displayDeleteItemsModal"
            :itemSelected="itemSelected"
            @item-success="onModalSuccess"
        />
        <RestoreProviderUserRolesDialog
            v-model:visible="displayRestoreItemsModal"
            :itemSelected="itemSelected"
            @item-success="onModalSuccess"
        />
    </div>
</template>
