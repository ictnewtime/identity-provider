export function getDomain(url) {
    let hostname = new URL(url).hostname;
    let parts = hostname.split(".");

    if (parts.length > 2 && parts[parts.length - 1].length <= 2) {
        return "." + parts.slice(-3).join(".");
    }

    return parts.slice(-2).join(".");
}
