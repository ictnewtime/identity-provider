<template>
    <div>
        <div id="providers-container" class="pb-4">
            <div
                class="d-flex flex-column flex-lg-row justify-content-start justify-content-lg-between align-items-lg-center mb-5"
            >
                <div>
                    <h1>Crea nuovo provider</h1>
                </div>
                <div>
                    <!-- <button type="submit" class="btn btn-outline-dark">
            Visualizza tutti
            </button> -->
                </div>
            </div>

            <form v-on:submit.prevent="submit">
                <div class="form-row">
                    <div class="form-group col-lg-6">
                        <label for="input-domain">Dominio</label>
                        <input
                            type="text"
                            v-bind:class="'form-control ' + (validator.domain.length > 0 ? 'is-invalid' : '')"
                            id="input-domain"
                            placeholder="idp.newtimegroup.it"
                            name="domain"
                            v-model="form.domain"
                        />
                        <small class="form-text text-danger">{{
                            validator.domain.length > 0 ? validator.domain[0] : ""
                        }}</small>
                    </div>
                    <div class="form-group col-lg-6">
                        <label for="input-logout-url">Logout URL</label>
                        <input
                            type="text"
                            class="form-control"
                            id="input-logout-url"
                            :placeholder="'https://' + (form.domain ? form.domain : 'dominio') + '/logout-idp'"
                            name="logoutUrl"
                            v-model="form.logoutUrl"
                        />
                        <small>Se vuoto il valore è quello di default</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-lg-6">
                        <label for="input-secret-key">Secret Key</label>
                        <input
                            type="password"
                            :class="'form-control ' + (validator.secretKey.length > 0 ? 'is-invalid' : '')"
                            id="input-secret-key"
                            placeholder="Secret Key"
                            name="secretKey"
                            v-model="form.secretKey"
                        />
                        <small class="form-text text-danger">{{
                            validator.secretKey.length > 0 ? validator.secretKey[0] : ""
                        }}</small>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-3">Crea Provider</button>
            </form>
        </div>
    </div>
</template>

<script>
import { EventBus } from "../event-bus";

export default {
    data() {
        return {
            form: {
                domain: null,
                secretKey: null,
                logoutUrl: null,
            },
            validator: {
                domain: [],
                secretKey: [],
                logoutUrl: [],
            },
        };
    },

    methods: {
        submit: function () {
            if (!this.validate()) {
                return;
            }

            let vm = this;
            axios
                .post("/admin/v1/providers", {
                    domain: vm.form.domain,
                    logoutUrl: vm.form.logoutUrl,
                    secretKey: vm.form.secretKey,
                })
                .then(function (data) {
                    vm.resetForm();
                    EventBus.$emit("newNotification", {
                        message: "Provider aggiunto correttamente",
                        type: "SUCCESS",
                    });
                })
                .catch(function (error) {
                    const errors = error.response.data.errors;
                    EventBus.$emit("newNotification", {
                        message: "Dominio esistente o errore durante la registrazione del provider.",
                        type: "ERROR",
                    });
                    vm.validator = { ...vm.validator, ...errors };
                });
        },

        validate: function () {
            this.validator.domain = !!this.form.domain ? [] : ["Dominio obbligatorio"];
            this.validator.secretKey = !!this.form.secretKey ? [] : ["Secret Key obbligatoria"];

            if (!this.form.domain || !this.form.secretKey) {
                return false;
            }

            return true;
        },

        resetForm: function () {
            this.form.domain = null;
            this.form.secretKey = null;
            this.form.logoutUrl = null;
        },
    },
};
</script>
