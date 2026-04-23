<script setup>
import { useToast } from "primevue/usetoast";
import { trans } from "laravel-vue-i18n";

import Button from "primevue/button";
import Dialog from "primevue/dialog";
import { Icon } from "@iconify/vue";

const props = defineProps({
    visible: { type: Boolean, required: true },
    itemSelected: { type: Object, default: () => null },
});

const emit = defineEmits(["update:visible", "user-success", "user-error"]);
const toast = useToast();
const filterRoles = ref("");
let searchTimeout = null;

const onFilterChange = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadRoles(1);
    }, 500);
};

// fetch role with provider
const loadRoles = () => {
    loading.value = true;

    window.axios
        .get("/admin/v1/roles", {
            params: {
                page: 1,
                per_page: 1000,
                q: filterRoles.value,
                show_deleted: tableComponent.showRolesDeleted,
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
                detail: trans("admin.roles.toast.load_error"),
                life: 3000,
            });
            emit("item-error", err);
        })
        .finally(() => {
            loading.value = false;
        });
};
</script>

<template>
    <Dialog
        :visible="props.visible"
        @update:visible="$emit('update:visible', $event)"
        :header="$t('admin.users.roles.add')"
        :style="{ width: '450px' }"
        modal
    >
        <div class="flex items-center gap-4 pt-2">
            <i class="pi pi-exclamation-triangle text-red-500 text-4xl"></i>
            <span v-if="itemSelected" class="text-surface-700">
                {{ $t("admin.users.roles.prompt") }}
                <b class="text-surface-900">{{ itemSelected.ids.join(",") }}</b
                >?
            </span>
        </div>
        <template #footer>
            <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="$emit('update:visible', false)" />
            <Button
                :label="$t('common.restore')"
                icon="pi pi-check"
                severity="danger"
                @click="itemSelected.ids"
                autofocus
            />
        </template>
    </Dialog>
</template>
