<script setup>
import { ref, watch, computed } from "vue";
import { useToast } from "primevue/usetoast";
import { trans } from "laravel-vue-i18n";

import InputText from "primevue/inputtext";
import Password from "primevue/password";
import Button from "primevue/button";
import ToggleSwitch from "primevue/toggleswitch";
import Message from "primevue/message";

const props = defineProps({
    selectedUser: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(["user-created", "user-updated"]);
const toast = useToast();

const loading = ref(false);
const form = ref({
    id: null,
    username: "",
    email: "",
    name: "",
    surname: "",
    password: "",
    password_confirmation: "",
    enabled: true,
});

const errors = ref({
    username: "",
    email: "",
    name: "",
    surname: "",
    password: "",
    password_confirmation: "",
    form: "",
});

const isEditMode = computed(() => !!props.selectedUser);

const resetForm = () => {
    form.value = {
        id: null,
        username: "",
        email: "",
        name: "",
        surname: "",
        password: "",
        password_confirmation: "",
        enabled: true,
        form: "",
    };
    resetErrors();
};

const resetErrors = () => {
    Object.keys(errors.value).forEach((key) => (errors.value[key] = ""));
};

const clearPasswords = () => {
    form.value.password = "";
    form.value.password_confirmation = "";
    errors.value.password = "";
    errors.value.password_confirmation = "";
};

const validateEmail = (email) => {
    return String(email)
        .toLowerCase()
        .match(
            /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|.(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
        );
};

const validate = () => {
    resetErrors();
    let isValid = true;

    if (!form.value.username) {
        errors.value.username = trans("admin.users.form.validate.username.mandatory");
        isValid = false;
    }
    if (!form.value.email || !validateEmail(form.value.email)) {
        errors.value.email = trans("admin.users.form.validate.email.mandatory");
        isValid = false;
    }
    if (!form.value.name) {
        errors.value.name = trans("admin.users.form.validate.name.mandatory");
        isValid = false;
    }
    if (!form.value.surname) {
        errors.value.surname = trans("admin.users.form.validate.surname.mandatory");
        isValid = false;
    }

    if (!isEditMode.value && !form.value.password) {
        errors.value.password = trans("admin.users.form.validate.password.mandatory");
        isValid = false;
    }

    if (form.value.password && form.value.password !== form.value.password_confirmation) {
        errors.value.password_confirmation = trans("admin.users.form.validate.password_confirmation.mandatory");
        isValid = false;
    }

    return isValid;
};

const fetchUser = async (id) => {
    loading.value = true;
    try {
        const res = await window.axios.get(`/admin/v1/users/${id}`);
        const data = res.data;

        form.value = {
            id: data.id,
            username: data.username,
            email: data.email,
            name: data.name,
            surname: data.surname,
            enabled: data.enabled == 1,
            password: "",
            password_confirmation: "",
        };
    } catch (err) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: trans("admin.users.toast.load_user_error"),
            life: 3000,
        });
    } finally {
        loading.value = false;
    }
};

const submit = async () => {
    if (!validate()) return;

    loading.value = true;

    const url = isEditMode.value ? `/admin/v1/users/${form.value.id}` : "/admin/v1/users";
    const method = isEditMode.value ? "put" : "post";

    const payload = {
        username: form.value.username,
        email: form.value.email,
        name: form.value.name,
        surname: form.value.surname,
        enabled: form.value.enabled,
    };

    if (form.value.password) {
        payload.password = form.value.password;
        payload.password_confirmation = form.value.password_confirmation;
    }

    try {
        await window.axios[method](url, payload);
        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: isEditMode.value
                ? trans("admin.users.toast.detail_updated")
                : trans("admin.users.toast.detail_created"),
            life: 3000,
        });
        emit(isEditMode.value ? "user-updated" : "user-created");
        resetForm();
    } catch (error) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: trans("admin.users.toast.submit_error"),
            life: 3000,
        });

        if (error.response?.data?.errors) {
            const backendErrors = error.response.data.errors;
            Object.keys(backendErrors).forEach((key) => {
                if (errors.value[key] !== undefined) {
                    errors.value[key] = backendErrors[key][0];
                }
            });
        }

        if (error.response?.data?.message) {
            errors.value.form = error.response.data.message;
        }
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.selectedUser,
    (newVal) => {
        if (newVal && newVal.id) {
            fetchUser(newVal.id);
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
            <div class="flex flex-col gap-1">
                <label for="username" class="font-medium text-surface-900">{{ $t("admin.users.form.username") }}</label>
                <InputText id="username" v-model="form.username" :invalid="!!errors.username" fluid />
                <Message v-if="errors.username" severity="error" size="small" variant="simple">
                    {{ errors.username }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <label for="email" class="font-medium text-surface-900">{{ $t("admin.users.form.email") }}</label>
                <InputText id="email" type="email" v-model="form.email" :invalid="!!errors.email" fluid />
                <Message v-if="errors.email" severity="error" size="small" variant="simple">
                    {{ errors.email }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <label for="name" class="font-medium text-surface-900">{{ $t("admin.users.form.name") }}</label>
                <InputText id="name" v-model="form.name" :invalid="!!errors.name" fluid />
                <Message v-if="errors.name" severity="error" size="small" variant="simple">
                    {{ errors.name }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <label for="surname" class="font-medium text-surface-900">{{ $t("admin.users.form.surname") }}</label>
                <InputText id="surname" v-model="form.surname" :invalid="!!errors.surname" fluid />
                <Message v-if="errors.surname" severity="error" size="small" variant="simple">
                    {{ errors.surname }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <label for="password" class="font-medium text-surface-900">{{
                            $t("admin.users.form.password")
                        }}</label>
                        <i
                            class="pi pi-question-circle"
                            style="color: var(--p-yellow-400); cursor: pointer; font-size: 0.875rem"
                            v-tooltip.top="{ value: $t('admin.users.form.password_tip'), escape: true }"
                        ></i>
                    </div>

                    <Button
                        v-if="isEditMode && form.password.length > 0"
                        icon="pi pi-eraser"
                        :label="$t('admin.users.form.btn_clear')"
                        severity="secondary"
                        size="small"
                        text
                        @click="clearPasswords"
                        class="p-0 h-auto text-surface-500 hover:text-surface-900"
                    />
                </div>

                <Password
                    id="password"
                    v-model="form.password"
                    autocomplete="new-password"
                    :invalid="!!errors.password"
                    :feedback="false"
                    toggleMask
                    fluid
                />
                <Message v-if="errors.password" severity="error" size="small" variant="simple">
                    {{ errors.password }}
                </Message>
            </div>

            <div class="flex flex-col gap-1">
                <label for="password_confirmation" class="font-medium text-surface-900">
                    {{ $t("admin.users.form.password_confirmation") }}
                </label>
                <Password
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    autocomplete="new-password"
                    :invalid="!!errors.password_confirmation"
                    :feedback="false"
                    toggleMask
                    fluid
                />
                <Message v-if="errors.password_confirmation" severity="error" size="small" variant="simple">
                    {{ errors.password_confirmation }}
                </Message>
            </div>

            <div class="flex items-center gap-3 mt-2 md:col-span-2">
                <label for="enabled" class="font-medium text-surface-900">{{ $t("admin.users.form.enabled") }}</label>
                <ToggleSwitch id="enabled" v-model="form.enabled" />
            </div>
        </div>

        <Message v-if="errors.form" severity="error" size="small" variant="simple">
            {{ errors.form }}
        </Message>

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
                :label="isEditMode ? $t('common.save_changes') : $t('admin.users.form.btn_create')"
                icon="pi pi-check"
                :loading="loading"
            />
        </div>
    </form>
</template>
