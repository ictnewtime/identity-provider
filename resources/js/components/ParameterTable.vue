<script setup>
import { ref, reactive, onMounted } from "vue";
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

// Assicurati di creare questo componente per il form
import ParameterForm from "./ParameterForm.vue";
import { Icon } from "@iconify/vue";
import { formatDate } from "../utils/data";

const emit = defineEmits(["item-saved", "item-error"]);
const toast = useToast();

const filter = ref("");
const loading = ref(false);
const pagination = ref({ data: [], total: 0, per_page: 10 });
const displayModal = ref(false);
const parameterSelected = ref(null);
const displayDeleteModal = ref(false);
const displayRestoreModal = ref(false);
let searchTimeout = null;
const tableComponent = reactive({
    showParametersDeleted: false,
});

const loadParameters = (page = 1) => {
    loading.value = true;

    window.axios
        .get("/admin/v1/parameters", {
            params: {
                page: page,
                per_page: pagination.value.per_page,
                q: filter.value,
                show_deleted: tableComponent.showParametersDeleted,
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
                detail: trans("admin.parameters.toast.load_error"),
                life: 3000,
            });
            emit("item-error", err);
        })
        .finally(() => {
            loading.value = false;
        });
};

const onPage = (event) => {
    loadParameters(event.page + 1);
};

const onFilterChange = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadParameters(1);
    }, 500);
};

const openCreateModal = () => {
    parameterSelected.value = null;
    displayModal.value = true;
};

defineExpose({
    openCreateModal,
});

const onParameterSaved = () => {
    displayModal.value = false;
    loadParameters();
};

const editParameter = (parameter) => {
    parameterSelected.value = parameter;
    displayModal.value = true;
};

const confirmDelete = (parameter) => {
    parameterSelected.value = parameter;
    displayDeleteModal.value = true;
};

const deleteParameter = () => {
    if (!parameterSelected.value) return;

    window.axios
        .delete(`/admin/v1/parameters/${parameterSelected.value.id}`)
        .then(() => {
            displayDeleteModal.value = false;
            parameterSelected.value = null;
            loadParameters(pagination.value.current_page);
            toast.add({
                severity: "success",
                summary: trans("common.success"),
                detail: trans("admin.parameters.toast.delete_success"),
                life: 3000,
            });
            emit("item-saved");
        })
        .catch((error) => {
            console.error(error);
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.parameters.toast.delete_error"),
                life: 3000,
            });
            emit("item-error", error);
        });
};

const confirmRestore = (parameter) => {
    parameterSelected.value = parameter;
    displayRestoreModal.value = true;
};

const restoreParameter = () => {
    if (!parameterSelected.value) return;
    window.axios
        .patch(`/admin/v1/parameters/${parameterSelected.value.id}/restore`)
        .then(() => {
            displayRestoreModal.value = false;
            parameterSelected.value = null;
            loadParameters(pagination.value.current_page);
            toast.add({
                severity: "success",
                summary: trans("common.success"),
                detail: trans("admin.parameters.toast.restore_success"),
                life: 3000,
            });
            emit("item-saved");
        })
        .catch((error) => {
            console.error(error);
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.parameters.toast.restore_error"),
                life: 3000,
            });
            emit("item-error", error);
        });
};

const toggleShowParametersDeleted = () => {
    tableComponent.showParametersDeleted = !tableComponent.showParametersDeleted;
    loadParameters(1);
};

onMounted(() => {
    loadParameters();
});
</script>

<template>
    <div>
        <div class="bg-white rounded-2xl shadow-[0_2px_12px_rgba(0,0,0,0.03)] p-5 md:p-6">
            <DataTable :value="pagination.data" :loading="loading" responsiveLayout="scroll" stripedRows size="small">
                <template #header>
                    <div class="flex flex-col sm:flex-row justify-between items-center pb-4 gap-4">
                        <h3 class="text-lg font-semibold m-0 text-surface-800">
                            {{ $t("admin.parameters.table.title") }}
                        </h3>
                        <div class="flex gap-4">
                            <Button
                                variant="text"
                                severity="danger"
                                @click="toggleShowParametersDeleted"
                                v-tooltip.top="
                                    tableComponent.showParametersDeleted
                                        ? $t('admin.parameters.table.hide_deleted_tooltip')
                                        : $t('admin.parameters.table.show_deleted_tooltip')
                                "
                            >
                                <Icon
                                    icon="material-symbols:delete-forever-outline-rounded"
                                    width="24"
                                    height="24"
                                    :class="tableComponent.showParametersDeleted ? 'text-red-500' : 'text-gray-500'"
                                />
                            </Button>
                            <IconField iconPosition="left">
                                <InputIcon class="pi pi-search text-surface-400" />
                                <InputText
                                    v-model="filter"
                                    :placeholder="$t('admin.parameters.table.search_placeholder')"
                                    @input="onFilterChange"
                                    class="rounded-lg!"
                                />
                            </IconField>
                        </div>
                    </div>
                </template>

                <Column field="key" :header="$t('admin.parameters.table.key')">
                    <template #body="slotProps">
                        <span class="font-bold text-surface-900">{{ slotProps.data.key }}</span>
                    </template>
                </Column>

                <Column field="value" :header="$t('admin.parameters.table.value')">
                    <template #body="slotProps">
                        <span class="font-medium text-surface-700">{{ slotProps.data.value }}</span>
                    </template>
                </Column>

                <Column field="type" :header="$t('admin.parameters.table.type')">
                    <template #body="slotProps">
                        <span class="text-surface-600">{{ slotProps.data.type }}</span>
                    </template>
                </Column>

                <Column
                    field="deleted_at"
                    :header="$t('admin.parameters.table.deleted_at')"
                    v-if="tableComponent.showParametersDeleted === true"
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
                            @click="editParameter(slotProps.data)"
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
                        <i class="pi pi-server text-4xl mb-4 text-surface-300"></i>
                        <p class="m-0">
                            {{ $t("admin.parameters.table.empty") }}
                        </p>
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
                parameterSelected ? $t('admin.parameters.form.title_edit') : $t('admin.parameters.form.title_create')
            "
            :style="{ width: '60vw', maxWidth: '800px' }"
            modal
            :draggable="false"
        >
            <ParameterForm :parameterSelected="parameterSelected" @item-saved="onParameterSaved" />
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
                <span v-if="parameterSelected" class="text-surface-700">
                    {{ $t("admin.parameters.delete.prompt") }}
                    <b class="text-surface-900">{{ parameterSelected.key }}</b
                    >?
                </span>
            </div>
            <template #footer>
                <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="displayDeleteModal = false" />
                <Button
                    :label="$t('common.delete')"
                    icon="pi pi-check"
                    severity="danger"
                    @click="deleteParameter"
                    autofocus
                />
            </template>
        </Dialog>
        <Dialog
            v-model:visible="displayRestoreModal"
            :header="$t('admin.parameters.restore.title')"
            :style="{ width: '450px' }"
            modal
            :draggable="false"
        >
            <div class="flex items-center gap-4 pt-2">
                <i class="pi pi-exclamation-triangle text-red-500 text-4xl"></i>
                <span v-if="parameterSelected" class="text-surface-700">
                    {{ $t("admin.parameters.restore.prompt") }}
                    <b class="text-surface-900">{{ parameterSelected.key }}</b
                    >?
                </span>
            </div>
            <template #footer>
                <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="displayRestoreModal = false" />
                <Button
                    :label="$t('common.restore')"
                    icon="pi pi-check"
                    severity="danger"
                    @click="restoreParameter"
                    autofocus
                />
            </template>
        </Dialog>
    </div>
</template>
