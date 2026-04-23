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

const restoreUser = (id) => {
    if (!id) return;

    window.axios
        .patch(`/admin/v1/users/${id}/restore`)
        .then(() => {
            emit("update:visible", false);
            toast.add({
                severity: "success",
                summary: trans("common.success"),
                detail: trans("admin.users.toast.restore_success"),
                life: 3000,
            });
            emit("user-success");
        })
        .catch((error) => {
            console.error(error);
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: trans("admin.users.toast.restore_error"),
                life: 3000,
            });
            emit("user-error", error);
        });
};
</script>

<template>
    <Dialog
        :visible="props.visible"
        @update:visible="$emit('update:visible', $event)"
        :header="$t('admin.users.restore.user_title')"
        :style="{ width: '450px' }"
        modal
    >
        <div class="flex items-center gap-4 pt-2">
            <i class="pi pi-exclamation-triangle text-red-500 text-4xl"></i>
            <span v-if="itemSelected" class="text-surface-700">
                {{ $t("admin.users.restore.prompt_restore_user") }}
                <b class="text-surface-900">{{ itemSelected.username }}</b
                >?
            </span>
        </div>
        <template #footer>
            <Button :label="$t('common.cancel')" icon="pi pi-times" text @click="$emit('update:visible', false)" />
            <Button
                :label="$t('common.delete')"
                icon="pi pi-check"
                severity="danger"
                @click="restoreUser(itemSelected.id)"
                autofocus
            />
        </template>
    </Dialog>
</template>
