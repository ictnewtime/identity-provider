export function getDomain(url) {
    let hostname = new URL(url).hostname;
    let parts = hostname.split(".");

    // controllo nazioni
    if (parts.length > 2 && parts[parts.length - 2].length < 3 && parts[parts.length - 1].length < 3) {
        return parts.slice(-3).join(".");
    }

    return parts.slice(-2).join(".");
}

/** L'unico host senza punti che vale come dominio a se': non e' un IP e non ha sottodomini. */
const LOCALHOST = "localhost";

/** Quattro gruppi di cifre separati da punti. Il valore dei gruppi non interessa: chi lo scrive e' un URL. */
const IPV4 = /^\d{1,3}(\.\d{1,3}){3}$/;

/**
 * Il dominio da mettere nel cookie, a partire da un URL.
 *
 * **Con il punto davanti** per un dominio vero: `.esempio.it` vale per `esempio.it` e per tutti i suoi
 * sottodomini, ed e' cio' che serve a un IdP, dove l'applicazione sta su un sottodominio diverso.
 *
 * **Senza**, per un indirizzo IP o per `localhost`: sottodomini non ne hanno, e un punto davanti
 * renderebbe il cookie non valido — il browser lo scarta senza dire niente.
 *
 * `getDomain()` non si puo' usare per gli IP: taglia le ultime due o tre etichette, quindi di
 * `192.168.1.10` restituisce `168.1.10`. Per questo il ramo dell'IP usa l'hostname intero.
 */
export function cookieDomain(url) {
    const hostname = new URL(url).hostname;

    // IPv6: `new URL()` lo restituisce fra parentesi quadre, ed e' l'unico host che contiene `:`.
    const isIpv6 = hostname.startsWith("[") || hostname.includes(":");

    if (hostname === LOCALHOST || IPV4.test(hostname) || isIpv6) {
        return hostname;
    }

    return "." + getDomain(url);
}
