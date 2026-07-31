#!/usr/bin/env python3
"""
jwks_to_pem.py
Converte una chiave RSA da un file JWKS (.well-known/jwks.json) in formato PEM.

Uso:
    python jwks_to_pem.py jwks.json --kid idp-master-key -o public.pem
    python jwks_to_pem.py jwks.json            # usa la prima chiave trovata, stampa a video
"""

import argparse
import base64
import json
import sys

from cryptography.hazmat.primitives.asymmetric import rsa
from cryptography.hazmat.primitives import serialization


def b64url_decode(s: str) -> bytes:
    """Decodifica base64url aggiungendo il padding mancante."""
    padding = "=" * (-len(s) % 4)
    return base64.urlsafe_b64decode(s + padding)


def jwk_to_pem(jwk: dict) -> bytes:
    if jwk.get("kty") != "RSA":
        raise ValueError(f"kty non supportato: {jwk.get('kty')} (atteso RSA)")

    n_int = int.from_bytes(b64url_decode(jwk["n"]), "big")
    e_int = int.from_bytes(b64url_decode(jwk["e"]), "big")

    public_key = rsa.RSAPublicNumbers(e_int, n_int).public_key()

    return public_key.public_bytes(
        encoding=serialization.Encoding.PEM,
        format=serialization.PublicFormat.SubjectPublicKeyInfo,
    )


def find_key(jwks: dict, kid: str | None) -> dict:
    keys = jwks.get("keys", [])
    if not keys:
        raise ValueError("Nessuna chiave trovata nel JWKS")

    if kid is None:
        return keys[0]

    for k in keys:
        if k.get("kid") == kid:
            return k

    raise ValueError(f"Nessuna chiave trovata con kid={kid}")


def main():
    parser = argparse.ArgumentParser(description="Converte una chiave JWKS in PEM")
    parser.add_argument("jwks_file", help="Path al file jwks.json")
    parser.add_argument("--kid", help="kid della chiave da estrarre (opzionale)")
    parser.add_argument("-o", "--output", help="File di output (default: stdout)")
    args = parser.parse_args()

    with open(args.jwks_file, "r", encoding="utf-8") as f:
        jwks = json.load(f)

    jwk = find_key(jwks, args.kid)
    pem = jwk_to_pem(jwk)

    if args.output:
        with open(args.output, "wb") as f:
            f.write(pem)
        print(f"Chiave pubblica salvata in: {args.output}", file=sys.stderr)
    else:
        sys.stdout.buffer.write(pem)


if __name__ == "__main__":
    main()