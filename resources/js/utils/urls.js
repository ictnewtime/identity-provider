export function getDomain(url) {
    let hostname = new URL(url).hostname;
    let parts = hostname.split(".");

    // controllo nazioni
    if (parts.length > 2 && parts[parts.length - 2].length < 3 && parts[parts.length - 1].length < 3) {
        return parts.slice(-3).join(".");
    }

    return parts.slice(-2).join(".");
}
