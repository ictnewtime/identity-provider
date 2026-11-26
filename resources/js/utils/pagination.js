// Il backend restituisce al massimo 100 righe per pagina, qualunque `per_page` gli si chieda.
export const MAX_PER_PAGE = 100;

/**
 * Scarica TUTTE le pagine di un elenco, una dopo l'altra.
 *
 * Serve ai menu a tendina che mostrano l'elenco completo (provider, ruoli di un provider): chiedere
 * `per_page: 1000` non basta, il server si ferma a 100 e chi viene dopo non compare mai.
 */
export const fetchAllPages = async (url, params = {}) => {
    const rows = [];
    let page = 1;
    let lastPage = 1;

    do {
        const res = await window.axios.get(url, { params: { ...params, per_page: MAX_PER_PAGE, page } });
        rows.push(...(res.data.data || []));
        lastPage = res.data.last_page ?? res.data.meta?.last_page ?? 1;
        page++;
    } while (page <= lastPage);

    return rows;
};
