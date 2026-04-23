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

const emit = defineEmits(["update:visible", "item-success", "item-error"]);
const toast = useToast();

const deleteProviderUserRoles = (ids) => {
    if (!ids || ids.length === 0) return;

    window.axios
        .delete("/admin/v1/provider-user-roles/", { data: { ids } })
        .then(() => {
            emit("update:visible", false);
            toast.add({
                severity: "success",
                summary: trans("common.success"),
                detail: trans("admin.provider_user_roles.toast.delete_success"),
                life: 3000,
            });
            emit("item-success");
        })
        .catch((error) => {
            console.error(error);
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.provider_user_roles.toast.delete_error"),
                life: 3000,
            });
            emit("item-error", error);
        });
};
</script>

<template>
    <Dialog
        :visible="props.visible"
        @update:visible="$emit('update:visible', $event)"
        :header="$t('common.confirm_delete_title')"
        :style="{ width: '450px' }"
        modal
        :draggable="false"
    >
        <div class="flex items-center gap-4 pt-2">
            <i class="pi pi-exclamation-triangle text-red-500 text-4xl"></i>
            <span v-if="itemSelected" class="text-surface-700">
                {{ $t("admin.provider_user_roles.delete.prompt_user") }}
                <b class="text-surface-900">{{ itemSelected.ids.join(",") }}</b
                >?
            </span>
        </div>
        <template #footer>
            <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="$emit('update:visible', false)" />
            <Button
                :label="$t('common.delete')"
                icon="pi pi-check"
                severity="danger"
                @click="deleteProviderUserRoles(itemSelected.ids)"
                autofocus
            />
        </template>
    </Dialog>
</template>
