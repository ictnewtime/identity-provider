<script setup>
import { useForm, Link } from "@inertiajs/vue3";
import { trans } from "laravel-vue-i18n";
import InputText from "primevue/inputtext";
import Button from "primevue/button";
import Message from "primevue/message";
import Image from "primevue/image";
import FloatLabel from "primevue/floatlabel";

defineProps({
    status: String,
});

const form = useForm({
    username: "",
});

const submit = () => {
    form.post("/forgot-password");
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-200 py-10">
        <div class="w-full sm:w-[26rem] p-8 bg-gray-50 border border-gray-500 rounded-xl shadow-lg">
            <div class="flex flex-col items-center mb-6 gap-4">
                <Image src="/images/logo.png" alt="Logo Aziendale" width="480" />

                <h2 class="text-xl font-semibold text-gray-900">
                    {{ $t("auth.recover_password_title") }}
                </h2>

                <p class="text-sm text-gray-600 text-center">
                    {{ $t("auth.recover_password_body") }}
                </p>
            </div>

            <Message v-if="status" severity="success" class="mb-4" :closable="false">
                {{ status }}
            </Message>

            <form @submit.prevent="submit" class="flex flex-col gap-4 w-full">
                <div class="flex flex-col gap-1">
                    <FloatLabel variant="on">
                        <InputText
                            id="username"
                            v-model="form.username"
                            type="text"
                            fluid
                            :invalid="!!form.errors.username"
                            :disabled="form.processing"
                            required
                        />
                        <label for="password" class="font-medium text-gray-700 z-10">Username</label>
                    </FloatLabel>
                    <Message v-if="form.errors.username" severity="error" size="small" variant="simple">
                        {{ form.errors.username }}
                    </Message>
                </div>

                <div class="mt-4 flex flex-col gap-3">
                    <Button
                        type="submit"
                        :label="trans('auth.send_recovery_link')"
                        fluid
                        :loading="form.processing"
                        :disabled="!form.username"
                    />
                    <Link method="get" href="/loginForm" class="w-full">
                        <Button
                            type="button"
                            :label="trans('auth.back_to_login')"
                            severity="secondary"
                            variant="outlined"
                            fluid
                        />
                    </Link>
                </div>
            </form>
        </div>
    </div>
</template>
