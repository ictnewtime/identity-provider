<script setup>
import { computed, toRef, ref } from "vue";
import { useForm, Link } from "@inertiajs/vue3";
import Password from "primevue/password";
import Button from "primevue/button";
import Message from "primevue/message";
import Image from "primevue/image";
import FloatLabel from "primevue/floatlabel";
import { usePassword } from "@/Composables/usePassword";
import { Icon } from "@iconify/vue";
import InputGroup from "primevue/inputgroup";
import InputGroupAddon from "primevue/inputgroupaddon";

const props = defineProps({
    username: String,
    csrf_token: String,
});

const form = useForm({
    current_password: "",
    new_password: "",
    new_password_confirmation: "",
});

const formItems = ref({
    current_password: {
        visible: false,
    },
    new_password: {
        visible: false,
    },
    new_password_confirmation: {
        visible: false,
    },
});

const passwordRef = toRef(form, "new_password");
const confirmPasswordRef = toRef(form, "new_password_confirmation");

const { requirements, strength, strengthColorClass, strengthTextColorClass, strengthText, isValid, generatePassword } =
    usePassword(passwordRef, confirmPasswordRef);

const isFormValid = computed(() => form.current_password.length > 0 && isValid.value);

const handleGeneratePassword = () => {
    const newPwd = generatePassword();
    form.new_password = newPwd;
    form.new_password_confirmation = newPwd;
};

const submit = () => {
    if (!isFormValid.value) return;
    form.post("/password/force-update");
};
const togglePasswordVisibility = (password_type) => {
    formItems.value[password_type].visible = !formItems.value[password_type].visible;
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-200 py-10">
        <div class="w-full sm:w-[30rem] p-8 bg-gray-50 border border-gray-500 rounded-xl shadow-lg">
            <div class="flex flex-col items-center mb-6 gap-2">
                <Image src="/images/logo.png" alt="Logo Aziendale" width="480" class="mb-2" />
                <h2 class="text-xl font-semibold text-gray-900">{{ $t("auth.mandatory_change_title") }}</h2>
                <p class="text-sm text-gray-600 text-center">
                    {{ $t("auth.mandatory_change_body") }} <br />
                    <strong class="text-gray-700">{{ username }}</strong>
                </p>
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-4 w-full">
                <div class="flex flex-col gap-1">
                    <InputGroup>
                        <FloatLabel variant="on">
                            <Password
                                inputId="current_password"
                                name="current_password"
                                v-model="form.current_password"
                                :feedback="false"
                                fluid
                                :invalid="!!form.errors.current_password"
                                :pt="{
                                    pcInputText: {
                                        root: {
                                            type: formItems.current_password.visible ? 'text' : 'password',
                                        },
                                    },
                                }"
                            />
                            <label for="current_password" class="font-medium text-gray-700 z-10">
                                {{ $t("auth.current_password") }}
                            </label>
                        </FloatLabel>
                        <InputGroupAddon class="p-0 border-none">
                            <Button
                                type="button"
                                severity="secondary"
                                :icon="formItems.current_password.visible ? 'pi pi-eye-slash' : 'pi pi-eye'"
                                v-tooltip.top="null"
                                @click="togglePasswordVisibility('current_password')"
                            />
                        </InputGroupAddon>
                    </InputGroup>
                    <Message v-if="form.errors.current_password" severity="error" size="small" variant="simple">
                        {{ form.errors.current_password }}
                    </Message>
                </div>

                <div class="flex flex-col gap-1 mt-2">
                    <InputGroup>
                        <FloatLabel variant="on">
                            <Password
                                inputId="password"
                                name="password"
                                v-model="form.new_password"
                                :feedback="true"
                                fluid
                                :invalid="!!form.errors.new_password"
                                :pt="{
                                    pcInputText: {
                                        root: {
                                            type: formItems.new_password.visible ? 'text' : 'password',
                                        },
                                    },
                                }"
                            >
                                <template #content>
                                    <div class="w-full sm:w-[22rem] p-1">
                                        <div class="w-full bg-gray-200 h-1.5 rounded-full overflow-hidden mb-2">
                                            <div
                                                class="h-full transition-all duration-300"
                                                :class="strengthColorClass"
                                                :style="{ width: `${(strength / 5) * 100}%` }"
                                            ></div>
                                        </div>
                                        <small class="text-gray-700 block font-medium">
                                            {{ $t("auth.password_strength") }}:
                                            <span class="font-bold" :class="strengthTextColorClass">{{
                                                strengthText
                                            }}</span>
                                        </small>
                                    </div>
                                </template>
                            </Password>
                            <label for="password" class="font-medium text-gray-700 z-10">
                                {{ $t("auth.new_password") }}
                            </label>
                        </FloatLabel>

                        <InputGroupAddon class="p-0 border-none">
                            <Button
                                type="button"
                                severity="secondary"
                                :icon="formItems.new_password.visible ? 'pi pi-eye-slash' : 'pi pi-eye'"
                                v-tooltip.top="null"
                                @click="togglePasswordVisibility('new_password')"
                            />
                        </InputGroupAddon>
                        <InputGroupAddon class="p-0 border-none">
                            <Button
                                type="button"
                                severity="secondary"
                                @click="handleGeneratePassword"
                                v-tooltip.top="$t('auth.generate_random_btn')"
                            >
                                <template #icon>
                                    <Icon icon="mdi:dice-multiple-outline" width="24" height="24" />
                                </template>
                            </Button>
                        </InputGroupAddon>
                    </InputGroup>

                    <Message v-if="form.errors.new_password" severity="error" size="small" variant="simple">
                        {{ form.errors.new_password }}
                    </Message>
                </div>

                <div class="flex flex-col gap-1 mt-2">
                    <InputGroup>
                        <FloatLabel variant="on">
                            <Password
                                inputId="new_password_confirmation"
                                name="new_password_confirmation"
                                v-model="form.new_password_confirmation"
                                :feedback="false"
                                fluid
                                :pt="{
                                    pcInputText: {
                                        root: {
                                            type: formItems.new_password_confirmation.visible ? 'text' : 'password',
                                        },
                                    },
                                }"
                            />
                            <label for="new_password_confirmation" class="font-medium text-gray-700 z-10">
                                {{ $t("auth.password_confirmation_label") }}
                            </label>
                        </FloatLabel>
                        <InputGroupAddon class="p-0 border-none">
                            <Button
                                type="button"
                                severity="secondary"
                                :icon="formItems.new_password_confirmation.visible ? 'pi pi-eye-slash' : 'pi pi-eye'"
                                v-tooltip.top="null"
                                @click="togglePasswordVisibility('new_password_confirmation')"
                            />
                        </InputGroupAddon>
                    </InputGroup>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg border border-gray-400 mt-2">
                    <ul class="text-sm flex flex-col gap-1">
                        <li :class="requirements.minLength ? 'text-green-600 font-medium' : 'text-gray-700'">
                            <i
                                class="pi mr-2 text-xs"
                                :class="requirements.minLength ? 'pi-check-circle' : 'pi-circle'"
                            ></i>
                            {{ $t("auth.req_min_length") }}
                        </li>
                        <li :class="requirements.hasUpperCase ? 'text-green-600 font-medium' : 'text-gray-700'">
                            <i
                                class="pi mr-2 text-xs"
                                :class="requirements.hasUpperCase ? 'pi-check-circle' : 'pi-circle'"
                            ></i>
                            {{ $t("auth.req_upper") }}
                        </li>
                        <li :class="requirements.hasLowerCase ? 'text-green-600 font-medium' : 'text-gray-700'">
                            <i
                                class="pi mr-2 text-xs"
                                :class="requirements.hasLowerCase ? 'pi-check-circle' : 'pi-circle'"
                            ></i>
                            {{ $t("auth.req_lower") }}
                        </li>
                        <li :class="requirements.hasNumber ? 'text-green-600 font-medium' : 'text-gray-700'">
                            <i
                                class="pi mr-2 text-xs"
                                :class="requirements.hasNumber ? 'pi-check-circle' : 'pi-circle'"
                            ></i>
                            {{ $t("auth.req_number") }}
                        </li>
                        <li :class="requirements.hasSpecialChar ? 'text-green-600 font-medium' : 'text-gray-700'">
                            <i
                                class="pi mr-2 text-xs"
                                :class="requirements.hasSpecialChar ? 'pi-check-circle' : 'pi-circle'"
                            ></i>
                            {{ $t("auth.req_special") }}
                        </li>
                        <li
                            v-if="form.new_password_confirmation"
                            :class="
                                requirements.passwordsMatch ? 'text-green-600 font-medium' : 'text-red-600 font-medium'
                            "
                        >
                            <i
                                class="pi mr-2 text-xs"
                                :class="requirements.passwordsMatch ? 'pi-check-circle' : 'pi-times-circle'"
                            ></i>
                            {{ $t("auth.req_match") }}
                        </li>
                    </ul>
                </div>

                <div class="mt-2 flex flex-col gap-3">
                    <Button
                        type="submit"
                        :label="$t('auth.update_password_btn')"
                        fluid
                        :loading="form.processing"
                        :disabled="!isFormValid"
                    />
                    <Link method="post" href="/logout" class="w-full">
                        <Button
                            type="button"
                            :label="$t('auth.back_to_login')"
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
