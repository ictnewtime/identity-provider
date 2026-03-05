<template>
    <form @submit.prevent="submit" class="row justify-content-start">
        <div class="col-lg-6 field d-flex flex-column mb-3">
            <label for="input-name">Nome Ruolo</label>
            <InputText
                id="input-name"
                v-model="form.name"
                :invalid="validator.name.length > 0"
                placeholder="es. Amministratore"
            />
            <small class="p-error" v-if="validator.name.length">{{ validator.name[0] }}</small>
        </div>

        <div class="col-lg-6 field d-flex flex-column mb-3">
            <label for="input-provider">Provider</label>
            <Dropdown
                id="input-provider"
                v-model="form.provider_id"
                :options="providers"
                optionLabel="domain"
                optionValue="id"
                placeholder="Seleziona un Provider"
                :invalid="validator.provider_id.length > 0"
                :loading="loadingProviders"
                class="w-full"
            />
            <small class="p-error" v-if="validator.provider_id.length">{{ validator.provider_id[0] }}</small>
        </div>

        <div class="col-12 mt-3 d-flex justify-content-end">
            <Button
                type="submit"
                :label="isEditMode ? 'Salva Modifiche' : 'Crea Ruolo'"
                :loading="loading"
                icon="pi pi-check"
            />
        </div>
    </form>
</template>

<script>
import InputText from "primevue/inputtext";
import Dropdown from "primevue/dropdown";
import Button from "primevue/button";

export default {
    name: "RoleForm",
    components: { InputText, Dropdown, Button },
    props: {
        selectedRole: {
            type: Object,
            default: null,
        },
    },
    data() {
        return {
            form: {
                id: null,
                name: null,
                provider_id: null,
            },
            providers: [], // Popolato via API
            loadingProviders: false,
            validator: {
                name: [],
                provider_id: [],
            },
            loading: false,
        };
    },
    computed: {
        isEditMode() {
            return !!this.selectedRole;
        },
    },
    watch: {
        selectedRole: {
            immediate: true,
            handler(newVal) {
                if (newVal && newVal.id) {
                    this.form.id = newVal.id;
                    this.form.name = newVal.name;
                    this.form.provider_id = newVal.provider_id;
                    this.clearErrors();
                } else {
                    this.resetForm();
                }
            },
        },
    },
    mounted() {
        this.loadProvidersList();
    },
    methods: {
        // Carica i provider per riempire il Dropdown
        loadProvidersList() {
            this.loadingProviders = true;
            // Richiediamo un numero alto per averli tutti nella select
            axios
                .get("/admin/v1/providers", { params: { per_page: 1000 } })
                .then((res) => {
                    // res.data.data perché l'API restituisce un oggetto paginato
                    this.providers = res.data.data || res.data;
                })
                .catch((err) => {
                    console.error("Errore caricamento provider", err);
                    this.$toast.add({
                        severity: "error",
                        summary: "Errore",
                        detail: "Errore caricamento provider",
                        life: 3000,
                    });
                })
                .finally(() => (this.loadingProviders = false));
        },

        submit() {
            if (!this.validate()) return;

            this.loading = true;
            let vm = this;

            const baseUrl = "/admin/v1/roles";
            const url = this.isEditMode ? `${baseUrl}/${this.form.id}` : baseUrl;
            const method = this.isEditMode ? "put" : "post";

            let payload = {
                name: vm.form.name,
                provider_id: vm.form.provider_id,
            };

            axios[method](url, payload)
                .then(() => {
                    vm.resetForm();
                    this.$toast.add({
                        severity: "success",
                        summary: "Operazione completata",
                        detail: vm.isEditMode ? "Ruolo aggiornato correttamente" : "Ruolo aggiunto correttamente",
                        life: 3000,
                    });
                    vm.$emit("role-saved");
                })
                .catch((error) => {
                    this.$toast.add({
                        severity: "error",
                        summary: "Errore",
                        detail: "Errore salvataggio ruolo",
                        life: 3000,
                    });
                    console.error("Errore salvataggio ruolo", error);
                    // if (error.response?.data?.errors) {
                    //     vm.validator = { ...vm.validator, ...error.response.data.errors };
                    // }
                })
                .finally(() => (vm.loading = false));
        },

        validate() {
            this.clearErrors();
            this.validator.name = !!this.form.name ? [] : ["Nome obbligatorio"];
            this.validator.provider_id = !!this.form.provider_id ? [] : ["Provider obbligatorio"];
            return this.validator.name.length === 0 && this.validator.provider_id.length === 0;
        },

        clearErrors() {
            this.validator.name = [];
            this.validator.provider_id = [];
        },

        resetForm() {
            this.form.id = null;
            this.form.name = null;
            this.form.provider_id = null;
            this.clearErrors();
        },
    },
};
</script>
