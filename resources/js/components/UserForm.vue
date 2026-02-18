<template>
    <Panel header="Crea nuovo utente">
        prova
        <form @submit.prevent="submit" class="grid p-fluid">
            <div class="col-12 md:col-6 field d-flex align-items-center justify-content-between">
                <label for="username">Username</label>
                <InputText
                    id="username"
                    v-model="form.username"
                    :invalid="validator.username.length > 0"
                    placeholder="mario.rossi"
                    size="small"
                />
                <small class="p-error" v-if="validator.username.length">{{ validator.username[0] }}</small>
            </div>

            <div class="col-12 md:col-6 field d-flex align-items-center justify-content-between">
                <label for="email">Email</label>
                <InputText
                    id="email"
                    v-model="form.email"
                    :invalid="validator.email.length > 0"
                    placeholder="mario.rossi@example.com"
                    size="small"
                />
                <small class="p-error" v-if="validator.email.length">{{ validator.email[0] }}</small>
            </div>

            <div class="col-12 md:col-6 field d-flex align-items-center justify-content-between">
                <label for="name">Nome</label>
                <InputText
                    id="name"
                    v-model="form.name"
                    :invalid="validator.name.length > 0"
                    placeholder="Mario"
                    size="small"
                />
                <small class="p-error" v-if="validator.name.length">{{ validator.name[0] }}</small>
            </div>

            <div class="col-12 md:col-6 field d-flex align-items-center justify-content-between">
                <label for="surname">Cognome</label>
                <InputText
                    id="surname"
                    v-model="form.surname"
                    :invalid="validator.surname.length > 0"
                    placeholder="Rossi"
                    size="small"
                />
                <small class="p-error" v-if="validator.surname.length">{{ validator.surname[0] }}</small>
            </div>

            <div class="col-12 md:col-6 field d-flex align-items-center justify-content-between">
                <label for="password">Password</label>
                <Password
                    id="password"
                    v-model="form.password"
                    :invalid="validator.password.length > 0"
                    :feedback="false"
                    toggleMask
                    size="small"
                />
                <small class="p-error" v-if="validator.password.length">{{ validator.password[0] }}</small>
            </div>

            <div class="col-12 md:col-6 field d-flex align-items-center justify-content-between">
                <label for="password_confirmation">Conferma Password</label>
                <Password
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    :invalid="validator.password_confirmation.length > 0"
                    :feedback="false"
                    toggleMask
                    size="small"
                />
                <small class="p-error" v-if="validator.password_confirmation.length">{{
                    validator.password_confirmation[0]
                }}</small>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                <Button label="Reset" icon="pi pi-refresh" severity="danger" variant="outlined" @click="resetForm" />
                <Button type="submit" label="Crea utente" icon="pi pi-user-plus" :loading="loading" />
            </div>
        </form>
    </Panel>
</template>

<script>
import { EventBus } from "../event-bus";
// Importa i componenti PrimeVue qui se non li hai registrati globalmente
import Panel from "primevue/panel";
import InputText from "primevue/inputtext";
import Password from "primevue/password";
import Button from "primevue/button";

export default {
    components: { Panel, InputText, Password, Button },
    data() {
        return {
            form: {
                username: null,
                password: null,
                email: null,
                name: null,
                surname: null,
            },
            validator: {
                username: [],
                email: [],
                password: [],
                password_confirmation: [],
                name: [],
                surname: [],
            },
            pagination: {
                current_page: -1,
                data: [],
                total: 0,
                last_page: 0,
                per_page: 10,
            },
            loading: false,
            filterUserInput: null,
        };
    },

    // created() {},

    watch: {
        filterUserInput: function (newValue, oldValue) {
            this.filterUsers();
        },
    },

    mounted() {
        // recupero l' utente dal localStorage
        this.loadUser();
    },

    methods: {
        loadUser() {
            let user = JSON.parse(localStorage.getItem("user"));
            if (user) {
                this.form.username = user.username;
                this.form.email = user.email;
                this.form.name = user.name;
                this.form.surname = user.surname;
            }
        },
        submit: function () {
            if (!this.validate()) {
                return;
            }

            let vm = this;
            const url = window.location.origin + "/admin/users";
            axios
                .post(url, {
                    username: vm.form.username,
                    password: vm.form.password,
                    password_confirmation: vm.form.password_confirmation,
                    email: vm.form.email,
                    name: vm.form.name,
                    surname: vm.form.surname,
                })
                .then(function (data) {
                    vm.resetForm();
                    vm.loadUsers();
                    EventBus.$emit("newNotification", {
                        message: "Utente aggiunto correttamente",
                        type: "SUCCESS",
                    });
                })
                .catch(function (error) {
                    console.log(error);
                    EventBus.$emit("newNotification", {
                        message: "Errore durante la registrazione",
                        type: "ERROR",
                    });
                    // error.errors è un oggetto key => value, value è una stringa
                    const errors = error.response.data.errors;
                    // inserire il messaggio di errore nella validator
                    vm.validator = { ...vm.validator, ...errors };
                });
        },

        validate: function () {
            this.validator.username = !!this.form.username ? [] : ["Username obbligatorio"];
            this.validator.password = !!this.form.password ? [] : ["Password obbligatoria"];
            this.validator.password_confirmation = !!this.form.password_confirmation ? [] : ["Password obbligatoria"];
            this.validator.email = this.validateEmail(this.form.email) ? [] : ["Email non valida"];
            this.validator.name = !!this.form.name ? [] : ["Nome obbligatorio"];
            this.validator.surname = !!this.form.surname ? [] : ["Cognome obbligatorio"];
            // salvare l' utente nel localStorage
            localStorage.setItem(
                "user",
                JSON.stringify({
                    username: this.form.username,
                    email: this.form.email,
                    name: this.form.name,
                    surname: this.form.surname,
                })
            );
            return (
                this.validator.username.length === 0 &&
                this.validator.password.length === 0 &&
                this.validator.password_confirmation.length === 0 &&
                this.validator.email.length === 0 &&
                this.validator.name.length === 0 &&
                this.validator.surname.length === 0
            );
        },

        validateEmail: function validateEmail(email) {
            let re =
                /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(String(email).toLowerCase());
        },

        resetForm: function () {
            this.form.username = null;
            this.form.password = null;
            this.form.password_confirmation = null;
            this.form.email = null;
            this.form.name = null;
            this.form.surname = null;
        },
    },
};
</script>
