export function getDomain(url) {
    let hostname = new URL(url).hostname;
    let parts = hostname.split(".");

    // If the last part is short (like .uk, .au), it might be a multi-part TLD
    if (parts.length > 2 && parts[parts.length - 1].length <= 2) {
        return "." + parts.slice(-3).join("."); // Returns "example.co.uk"
    }

    return parts.slice(-2).join("."); // Returns "example.com"
}
