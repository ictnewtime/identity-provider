<template>
    <tr>
        <td>{{ oauth_client_id }}</td>
        <td>{{ oauth_name }}</td>
        <td class="d-flex justify-content-between">
            {{ oauth_roles.length > 0 ? oauth_roles.join(", ") : "Nessun ruolo assegnato" }}
            <div class="dropdown" :class="{ show: isOpen }">
                <button class="btn btn-secondary dropdown-toggle" type="button" @click="isOpen = !isOpen">
                    Modifica ruolo
                </button>

                <div
                    class="dropdown-menu p-3"
                    :class="{ show: isOpen }"
                    aria-labelledby="dropdownMenuButton"
                    style="min-width: 250px"
                >
                    <div class="d-flex align-items-center mb-3 border-bottom pb-2">
                        <input
                            :checked="checked"
                            type="checkbox"
                            class="checkmark mr-2"
                            @click="selectAllfn"
                            id="selectAll"
                        />
                        <label
                            class="form-check-label font-weight-bold m-0"
                            for="selectAll"
                            style="cursor: pointer"
                            @click="selectAllfn"
                        >
                            Seleziona tutti
                        </label>
                    </div>

                    <div style="max-height: 200px; overflow-y: auto">
                        <div v-for="(role, index) in roles" :key="role" class="d-flex align-items-center mb-2">
                            <input
                                v-if="oauth_roles.some((el) => el == role)"
                                type="checkbox"
                                class="checkmark mr-2"
                                :value="role"
                                v-model="newRoles"
                                ref="precheck"
                                :id="'role-' + index"
                            />
                            <input
                                v-else
                                type="checkbox"
                                class="checkmark mr-2"
                                :value="role"
                                v-model="newRoles"
                                :id="'role-' + index"
                            />
                            <label class="m-0" :for="'role-' + index" style="cursor: pointer">
                                {{ role }}
                            </label>
                        </div>
                    </div>

                    <button @click="changeRoles(oauth_client_id)" type="button" class="btn btn-dark btn-block mt-3">
                        Conferma
                    </button>
                </div>
            </div>
        </td>
    </tr>
</template>

<script>
export default {
    name: "OauthClientRow",

    props: {
        oauth_client_id: {
            required: true,
        },
        oauth_name: {
            required: true,
            type: String,
        },
        oauth_roles: {
            required: true,
            type: Array,
        },
        loadClients: {
            required: true,
            type: Function,
        },
        roles: {
            required: true,
            type: Array,
        },
    },

    data() {
        return {
            isOpen: false,
            newRoles: [],
            selectAll: false,
            checked: false,
        };
    },

    mounted() {
        console.log("Componente montato. Ruoli ricevuti:", this.roles);
        let prechecked = this.$refs.precheck;

        if (prechecked) {
            prechecked.forEach((el) => this.newRoles.push(el.value));
            if (this.newRoles.length == this.roles.length) {
                this.checked = true;
                this.selectAll = true;
            }
        }
    },

    watch: {
        newRoles: function (newValue, oldValue) {
            if (this.newRoles.length == this.roles.length) {
                this.checked = true;
                this.selectAll = true;
            } else {
                this.checked = false;
                this.selectAll = false;
            }
        },
    },

    methods: {
        changeRoles(clientId) {
            axios
                .put("/admin/update-roles", {
                    clientId: clientId,
                    roles: this.newRoles,
                })
                .then((data) => {
                    this.loadClients();
                    EventBus.$emit("newNotification", {
                        message: "Ruoli aggiornati correttamente",
                        type: "SUCCESS",
                    });
                })
                .catch((error) => {
                    EventBus.$emit("newNotification", {
                        message: "Errore durante la modifica dei ruoli",
                        type: "ERROR",
                    });
                });
        },

        selectAllfn() {
            this.selectAll = !this.selectAll;

            if (this.selectAll) {
                this.newRoles = [...this.roles];
            } else {
                this.newRoles = [];
            }
        },
    },
};
</script>

<style scoped>
.checkmark {
    height: 20px;
    width: 20px;
    background-color: #eee;
    cursor: pointer;
}
</style>
