<template>
    <div class="d-flex justify-content-center align-items-center min-vh-100">
        <div class="col-10 col-sm-8 col-md-6 col-lg-4 col-xl-3 border px-3 py-4">
            <img class="w-100 mb-4" src="/images/logo.png" />
            <form method="post" action="/v2/login" class="d-flex flex-column align-items-center">
                <input type="hidden" name="redirect" value="placeholder-redirect" />
                <div class="form-group w-100">
                    <label for="inputUsername">Username</label>
                    <input
                        v-model="username"
                        id="inputUsername"
                        type="text"
                        class="form-control"
                        name="username"
                        placeholder="Username"
                    />
                </div>
                <div class="form-group w-100">
                    <label for="inputPassword">Password</label>
                    <input
                        v-model="password"
                        id="inputPassword"
                        type="password"
                        class="form-control"
                        name="password"
                        placeholder="Password"
                    />
                </div>
                <button
                    type="submit"
                    :class="['btn btn-primary w-50 mt-4 text-white']"
                    :disabled="!validate"
                    @click.prevent="login()"
                >
                    Login
                </button>
            </form>
            <div v-if="errorMessage !== null" class="alert alert-danger mt-4">
                <ul class="mb-0">
                    <li>{{ errorMessage }}</li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        redirect: null,
    },

    data() {
        return {
            username: null,
            password: null,
            errorMessage: null,
        };
    },

    computed: {
        validate() {
            return this.username && this.password && this.password.length > 2;
        },
    },

    methods: {
        login() {
            let vm = this;

            if (!this.validate) {
                return;
            }
            const urlParams = new URLSearchParams(window.location.search);
            const provider_id = urlParams.get("provider_id");
            const redirect_to = urlParams.get("redirect_to");
            if (provider_id) {
                vm.provider_id = provider_id;
            }
            if (redirect_to) {
                vm.redirect_to = redirect_to;
            }
            axios
                .post(
                    "/v2/login",
                    {
                        username: vm.username,
                        password: vm.password,
                        provider_id: vm.provider_id,
                        redirect_to: vm.redirect_to,
                    },
                    {
                        headers: {
                            "Content-Type": "application/json",
                            Accept: "application/json",
                        },
                    }
                )
                .then((response) => {
                    if (response.data.redirect_url) {
                        window.location.replace(response.data.redirect_url);
                        // window.location.href = response.data.redirect_url;
                    } else {
                        window.location.href = "/";
                    }
                })
                .catch((error) => {
                    if (error.response) {
                        vm.errorMessage = error.response.data.message;
                    } else if (error.message) {
                        vm.errorMessage = error.message;
                    } else {
                        vm.errorMessage = error;
                    }
                });
        },
    },
};
</script>

<style scoped></style>
