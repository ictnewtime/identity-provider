<template>
    <div>
        <div id="users-container" class="pb-4">
            <h1>Crea nuovo utente</h1>

            <form v-on:submit.prevent="submit">
                <div class="form-row row">
                    <div class="form-group col-lg-6 col-md-6 col-sm-12">
                        <label for="input-username">Username</label>
                        <input
                            type="text"
                            v-bind:class="'form-control ' + (validator.username.length > 0 ? 'is-invalid' : '')"
                            id="input-username"
                            placeholder="mario.rossi"
                            name="username"
                            v-model="form.username"
                        />
                        <small class="form-text text-danger">{{
                            validator.username.length > 0 ? validator.username[0] : ""
                        }}</small>
                    </div>
                    <div class="form-group col-lg-6 col-md-6 col-sm-12">
                        <label for="input-email">Email</label>
                        <input
                            type="text"
                            v-bind:class="'form-control ' + (validator.email.length > 0 ? 'is-invalid' : '')"
                            id="input-email"
                            placeholder="mario.rossi@example.com"
                            name="email"
                            v-model="form.email"
                        />
                        <small class="form-text text-muted">{{
                            validator.email.length > 0 ? validator.email[0] : ""
                        }}</small>
                    </div>

                    <div class="form-group col-lg-6 col-md-6 col-sm-12">
                        <label for="input-name">Nome</label>
                        <input
                            type="text"
                            :class="'form-control ' + (validator.name.length > 0 ? 'is-invalid' : '')"
                            id="input-name"
                            placeholder="Mario"
                            name="name"
                            v-model="form.name"
                        />
                        <small class="form-text text-muted">{{
                            validator.name.length > 0 ? validator.name[0] : ""
                        }}</small>
                    </div>
                    <div class="form-group col-lg-6 col-md-6 col-sm-12">
                        <label for="input-surname">Cognome</label>
                        <input
                            type="text"
                            :class="'form-control ' + (validator.surname.length > 0 ? 'is-invalid' : '')"
                            id="input-surname"
                            placeholder="Rossi"
                            name="surname"
                            v-model="form.surname"
                        />
                        <small class="form-text text-muted">{{
                            validator.surname.length > 0 ? validator.surname[0] : ""
                        }}</small>
                    </div>
                    <div class="form-group col-lg-6 col-md-6 col-sm-12">
                        <label for="input-password">Password</label>
                        <input
                            type="password"
                            v-bind:class="'form-control ' + (validator.password.length > 0 ? 'is-invalid' : '')"
                            id="input-password"
                            placeholder="Password"
                            name="password"
                            v-model="form.password"
                        />
                        <small class="form-text text-danger">{{
                            validator.password.length > 0 ? validator.password[0] : ""
                        }}</small>
                    </div>
                    <div class="form-group col-lg-6 col-md-6 col-sm-12">
                        <label for="input-password-confirmation">Conferma Password</label>
                        <input
                            type="password"
                            v-bind:class="
                                'form-control ' + (validator.password_confirmation.length > 0 ? 'is-invalid' : '')
                            "
                            id="input-password-confirmation"
                            placeholder="Password"
                            name="password_confirmation"
                            v-model="form.password_confirmation"
                        />
                        <small class="form-text text-danger">{{
                            validator.password_confirmation.length > 0 ? validator.password_confirmation[0] : ""
                        }}</small>
                    </div>
                </div>

                <div class="d-flex justify-content-around">
                    <button type="reset" class="btn btn-danger mt-3">Reset</button>
                    <button type="submit" class="btn btn-primary mt-3">Crea utente</button>
                </div>
            </form>

            <div class="d-flex mb-2 mt-5 align-items-center justify-content-between">
                <div>
                    <h1>Lista utenti</h1>
                </div>
                <div class="col-4 input-group px-0" style="max-width: 300px">
                    <input
                        v-model="filterUserInput"
                        type="text"
                        class="form-control"
                        aria-label="Search input filter"
                        placeholder="Filtra per e-mail"
                    />
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="thead-dark">
                        <tr>
                            <!-- <th scope="col">ID</th> -->
                            <th scope="col">Username</th>
                            <th scope="col">E-mail</th>
                            <!-- <th scope="col">Verificato</th> -->
                            <th scope="col">Nome</th>
                            <th scope="col">Cognome</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <th colspan="7" class="px-0 py-0">
                                <div class="progress" style="height: 5px">
                                    <div
                                        class="progress-bar progress-bar-striped progress-bar-animated"
                                        role="progressbar"
                                        aria-valuenow="75"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        style="width: 100%"
                                    ></div>
                                </div>
                            </th>
                        </tr>
                        <tr v-for="user in pagination.data" :key="user.id">
                            <!-- <th scope="row">{{ user.id }}</th> -->
                            <td>{{ user.username }}</td>
                            <td>{{ user.email }}</td>
                            <!-- <td class="text-center">
                                <i v-if="user.is_verified" class="fas fa-check"></i>
                            </td> -->
                            <td>{{ user.name }}</td>
                            <td>{{ user.surname }}</td>
                        </tr>
                        <tr v-if="!loading && pagination.data.length === 0">
                            <td colspan="7" class="text-center">Nessun utente trovato</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <paginator
                v-if="pagination.total > pagination.per_page"
                :pagination="pagination"
                :onChangePage="(page) => loadUsers(page)"
            ></paginator>
        </div>
    </div>
</template>

<script>
import { EventBus } from "../event-bus";

export default {
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

    created() {
        this.loadUsers();
    },

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
            const url = window.location.origin + "/api/v1/users";
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

        loadUsers(page = 1) {
            let vm = this;
            this.loading = true;

            const url = window.location.origin + "/api/v1/users?page=" + page;
            axios
                .get(url, {
                    params: {
                        page: page,
                        q: vm.filterUserInput,
                    },
                })
                .then((response) => {
                    vm.pagination = response.data;
                    vm.loading = false;
                })
                .catch((error) => {
                    vm.loading = false;
                    EventBus.$emit("newNotification", {
                        message: "Errore durante il caricamento degli utenti",
                        type: "ERROR",
                    });
                });
        },

        filterUsers() {
            this.loadUsers();
        },
    },
};
</script>
