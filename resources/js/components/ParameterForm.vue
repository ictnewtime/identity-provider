<script setup>
import { ref, watch, computed } from "vue";
import { useToast } from "primevue/usetoast";
import { trans } from "laravel-vue-i18n";

import InputText from "primevue/inputtext";
import Textarea from "primevue/textarea";
import Button from "primevue/button";
import Message from "primevue/message";

const props = defineProps({
    parameterSelected: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(["item-saved", "item-error"]);
const toast = useToast();

const loading = ref(false);

const form = ref({
    id: null,
    key: "",
    value: "",
    type: "string", // Impostiamo "string" come tipo predefinito
});

const errors = ref({
    key: "",
    value: "",
    type: "",
});

const isEditMode = computed(() => !!props.parameterSelected);

const resetForm = () => {
    form.value = {
        id: null,
        key: "",
        value: "",
        type: "string",
    };
    resetErrors();
};

const resetErrors = () => {
    errors.value.key = "";
    errors.value.value = "";
    errors.value.type = "";
};

const validate = () => {
    resetErrors();
    let isValid = true;

    if (!form.value.key) {
        errors.value.key = trans("admin.parameters.form.validate.key.mandatory");
        isValid = false;
    }

    // Rimuovi questo controllo se il campo "value" può essere vuoto
    if (!form.value.value && form.value.value !== 0 && form.value.value !== false) {
        errors.value.value = trans("admin.parameters.form.validate.value.mandatory");
        isValid = false;
    }

    if (!form.value.type) {
        errors.value.type = trans("admin.parameters.form.validate.type.mandatory");
        isValid = false;
    }

    // Validazione opzionale per il tipo JSON
    if (form.value.type === "json" && form.value.value) {
        try {
            JSON.parse(form.value.value);
        } catch (e) {
            errors.value.value = trans("admin.parameters.form.validate.value.invalid_json");
            isValid = false;
        }
    }

    return isValid;
};

const submit = async () => {
    if (!validate()) return;

    loading.value = true;

    const baseUrl = "/admin/v1/parameters";
    const url = isEditMode.value ? `${baseUrl}/${form.value.id}` : baseUrl;
    const method = isEditMode.value ? "put" : "post";

    const payload = {
        key: form.value.key,
        value: form.value.value,
        type: form.value.type,
    };

    try {
        await window.axios[method](url, payload);
        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: isEditMode.value
                ? trans("admin.parameters.toast.detail_updated")
                : trans("admin.parameters.toast.detail_created"),
            life: 3000,
        });
        emit("item-saved");
        resetForm();
    } catch (error) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: trans("admin.parameters.toast.submit_error"),
            life: 3000,
        });
        emit("item-error", error);

        if (error.response?.data?.errors) {
            const backendErrors = error.response.data.errors;
            if (backendErrors.key) errors.value.key = backendErrors.key[0];
            if (backendErrors.value) errors.value.value = backendErrors.value[0];
            if (backendErrors.type) errors.value.type = backendErrors.type[0];
        }
    } finally {
        loading.value = false;
    }
};

const fetchParameter = async (id) => {
    loading.value = true;
    try {
        const res = await window.axios.get(`/admin/v1/parameters/${id}`);
        const data = res.data;

        form.value = {
            id: data.id,
            key: data.key,
            value: data.value,
            type: data.type,
        };
    } catch (err) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: trans("admin.parameters.toast.load_error"),
            life: 3000,
        });
        emit("item-error", err);
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.parameterSelected,
    (newVal) => {
        if (newVal && newVal.id) {
            fetchParameter(newVal.id);
            resetErrors();
        } else {
            resetForm();
        }
    },
    { immediate: true }
);
</script>

<template>
    <form @submit.prevent="submit" class="flex flex-col gap-6 w-full pt-2">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1 md:col-span-2">
                <label for="key" class="font-medium text-surface-900">
                    {{ $t("admin.parameters.form.key_label") }}
                </label>
                <InputText
                    id="key"
                    v-model="form.key"
                    :invalid="!!errors.key"
                    :placeholder="$t('admin.parameters.form.key_placeholder')"
                    fluid
                    :disabled="isEditMode"
                />
                <Message v-if="errors.key" severity="error" size="small" variant="simple">
                    {{ errors.key }}
                </Message>
                <small v-if="isEditMode" class="text-surface-500">
                    {{ $t("admin.parameters.form.key_disabled_hint") }}
                </small>
            </div>

            <div class="flex flex-col gap-1 md:col-span-2">
                <label for="type" class="font-medium text-surface-900">
                    {{ $t("admin.parameters.form.type_label") }}
                </label>
                <InputText
                    id="type"
                    v-model="form.type"
                    :invalid="!!errors.type"
                    :placeholder="$t('admin.parameters.form.type_placeholder')"
                    fluid
                />
                <Message v-if="errors.type" severity="error" size="small" variant="simple">
                    {{ errors.type }}
                </Message>
            </div>

            <div class="flex flex-col gap-1 md:col-span-2">
                <label for="value" class="font-medium text-surface-900">
                    {{ $t("admin.parameters.form.value_label") }}
                </label>

                <Textarea
                    v-if="form.type === 'json' || form.type === 'text'"
                    id="value"
                    v-model="form.value"
                    :invalid="!!errors.value"
                    :placeholder="$t('admin.parameters.form.value_placeholder')"
                    rows="5"
                    fluid
                />
                <InputText
                    v-else
                    id="value"
                    v-model="form.value"
                    :invalid="!!errors.value"
                    :placeholder="$t('admin.parameters.form.value_placeholder')"
                    fluid
                />

                <Message v-if="errors.value" severity="error" size="small" variant="simple">
                    {{ errors.value }}
                </Message>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-4 border-t border-surface-200 pt-4">
            <Button
                type="button"
                :label="$t('common.reset')"
                severity="secondary"
                text
                icon="pi pi-refresh"
                @click="resetForm"
                :disabled="loading"
            />
            <Button
                type="submit"
                :label="isEditMode ? $t('common.save_changes') : $t('admin.parameters.form.btn_create')"
                icon="pi pi-check"
                :loading="loading"
            />
        </div>
    </form>
</template>
