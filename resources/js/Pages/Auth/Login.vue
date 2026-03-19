<script setup>
import { computed, onMounted } from "vue";
import { useForm, Link, usePage } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import { trans } from "laravel-vue-i18n";

import InputText from "primevue/inputtext";
import Password from "primevue/password";
import Button from "primevue/button";
import Message from "primevue/message";
import Image from "primevue/image";
import FloatLabel from "primevue/floatlabel";
import { Toast } from "primevue";

const form = useForm({
    username: "",
    password: "",
    provider_id: null,
    redirect_to: null,
});

const toast = useToast();
const page = usePage();

onMounted(() => {
    // 1. Lettura parametri URL
    const urlParams = new URLSearchParams(window.location.search);
    form.provider_id = urlParams.get("provider_id");
    form.redirect_to = urlParams.get("redirect_to");

    // 2. Lettura del messaggio flash di successo (inviato dal backend)
    // Nota: dipende da come hai configurato HandleInertiaRequests.php.
    // Di solito Inertia lo mappa sotto `page.props.flash.success` o `page.props.success`
    const successMessage = page.props.flash?.success || page.props.success;
    console.log("page.props.flash", page.props.flash);
    if (successMessage) {
        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: successMessage,
            life: 5000,
        });
    }
});

const isFormValid = computed(() => {
    return form.username && form.password && form.password.length > 2;
});

const submit = () => {
    if (!isFormValid.value) return;
    form.post("/v2/login");
};
</script>

<template>
    <Toast />
    <div class="min-h-screen flex items-center justify-center bg-gray-200 py-10">
        <div class="w-full sm:w-[26rem] p-8 bg-gray-50 border border-gray-500 rounded-xl shadow-lg">
            <div class="flex flex-col items-center mb-8 gap-4">
                <Image src="/images/logo.png" alt="Logo Aziendale" width="480" />
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-4 w-full">
                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <InputText
                            inputId="username"
                            name="username"
                            v-model="form.username"
                            type="text"
                            fluid
                            :invalid="!!form.errors.username"
                            :disabled="form.processing"
                        />
                        <label for="username" class="font-medium text-gray-700 z-10"> Username </label>
                    </FloatLabel>
                    <Message v-if="form.errors.username" severity="error" size="small" variant="simple">
                        {{ form.errors.username }}
                    </Message>
                </div>

                <div class="flex flex-col gap-1 mt-2">
                    <FloatLabel variant="on">
                        <Password
                            inputId="password"
                            name="password"
                            v-model="form.password"
                            :feedback="false"
                            toggleMask
                            fluid
                            :invalid="!!form.errors.password"
                            :disabled="form.processing"
                        />
                        <label for="password" class="font-medium text-gray-700 z-10"> Password </label>
                    </FloatLabel>

                    <div class="flex justify-end mt-1 mb-2">
                        <Link
                            href="/forgot-password"
                            class="text-sm text-primary-600 hover:text-primary-700 font-medium"
                        >
                            {{ $t("auth.forgot_password") }}
                        </Link>
                    </div>

                    <Message v-if="form.errors.password" severity="error" size="small" variant="simple">
                        {{ form.errors.password }}
                    </Message>
                </div>

                <Message v-if="form.errors.login" severity="error" size="small" variant="simple">
                    {{ form.errors.login }}
                </Message>

                <Button
                    type="submit"
                    label="Login"
                    class="mt-2"
                    fluid
                    :loading="form.processing"
                    :disabled="!isFormValid"
                />
            </form>
        </div>
    </div>
</template>
