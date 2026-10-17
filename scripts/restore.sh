#!/bin/bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DESTINATION="${BACKUP_DESTINATION:-${HOME}/Library/Mobile Documents/com~apple~CloudDocs/InvoiceCore/Backups}"
TMP_ROOT="${TMPDIR:-/tmp}"
WORK_DIR=""

cleanup() {
    if [[ -n "${WORK_DIR}" && -d "${WORK_DIR}" ]]; then
        rm -rf "${WORK_DIR}"
    fi
}
trap cleanup EXIT

fail() {
    echo "Restore nije izvršen: $*" >&2
    exit 1
}

[[ $# -eq 1 ]] || fail "Upotreba: scripts/restore.sh /putanja/do/invoicecore-YYYYmmdd-HHMMSS.tar.gz"
ARCHIVE="$(cd "$(dirname "$1")" && pwd)/$(basename "$1")"
[[ -f "${ARCHIVE}" ]] || fail "Backup arhiva ne postoji: ${ARCHIVE}"
CHECKSUM="${ARCHIVE}.sha256"
[[ -f "${CHECKSUM}" ]] || fail "Nedostaje checksum fajl: ${CHECKSUM}"

expected="$(awk 'NF {print $1; exit}' "${CHECKSUM}")"
actual="$(shasum -a 256 "${ARCHIVE}" | awk '{print $1}')"
[[ -n "${expected}" && "${expected}" == "${actual}" ]] \
    || fail "SHA-256 provera nije uspela; arhiva nije menjana."

WORK_DIR="$(mktemp -d "${TMP_ROOT%/}/invoicecore-restore.XXXXXX")"
EXTRACT_DIR="${WORK_DIR}/extract"
mkdir -p "${EXTRACT_DIR}"

entries="$(tar -tzf "${ARCHIVE}")" || fail "Arhiva nije validan tar.gz."
while IFS= read -r entry; do
    [[ -n "${entry}" ]] || continue
    [[ "${entry}" != /* && "${entry}" != *"../"* && "${entry}" != *"/.."* && "${entry}" != *"/./"* ]] \
        || fail "Arhiva sadrži nebezbednu putanju: ${entry}"
    case "${entry}" in
        manifest.txt|database.sql|storage|storage/app|storage/app/private|storage/app/private/*) ;;
        *) fail "Neočekivana putanja u arhivi: ${entry}" ;;
    esac
done <<< "${entries}"

grep -qx 'manifest.txt' <<< "${entries}" || fail "Manifest nedostaje iz arhive."
grep -qx 'database.sql' <<< "${entries}" || fail "SQL dump nedostaje iz arhive."
grep -q '^storage/app/private\(/\|$\)' <<< "${entries}" || fail "Privatni storage nedostaje iz arhive."

tar -xzf "${ARCHIVE}" -C "${EXTRACT_DIR}" || fail "Raspakivanje arhive nije uspelo."
find "${EXTRACT_DIR}" -type l -print -quit | grep -q . \
    && fail "Arhiva sadrži symbolic link; restore je prekinut radi bezbednosti." || true
[[ -f "${EXTRACT_DIR}/database.sql" && -d "${EXTRACT_DIR}/storage/app/private" ]] \
    || fail "Raspakovana struktura nije kompletna."

echo "Biće vraćeno:"
echo "- MariaDB baza iz database.sql"
echo "- storage/app/private"
echo "- .env, aplikacioni kod i ostali fajlovi neće biti menjani"
echo
cat "${EXTRACT_DIR}/manifest.txt"
echo
read -r -p "Nastaviti sa restore operacijom? Upišite RESTORE: " confirmation
[[ "${confirmation}" == "RESTORE" ]] || fail "Restore je otkazan."

cd "${ROOT_DIR}"
[[ -d .ddev ]] || fail "DDEV konfiguracija nije pronađena u ${ROOT_DIR}."
ddev_status="$(ddev status 2>&1)" || fail "DDEV projekat nije pokrenut. Pokrenite 'ddev start' pa pokušajte ponovo."
printf '%s\n' "${ddev_status}" | grep -Eiq 'running|healthy' \
    || fail "DDEV projekat nije pokrenut. Pokrenite 'ddev start' pa pokušajte ponovo."

mkdir -p "${DESTINATION}" || fail "Nije moguće pripremiti destinaciju za sigurnosni backup."
echo "Kreiram sigurnosni backup trenutnog stanja..."
BACKUP_DESTINATION="${DESTINATION}" "${ROOT_DIR}/scripts/backup.sh" \
    || fail "Sigurnosni backup nije uspeo; restore nije započet."

echo "Vraćam bazu..."
ddev import-db --file="${EXTRACT_DIR}/database.sql" \
    || fail "Vraćanje baze nije uspelo. Sigurnosni backup je sačuvan u ${DESTINATION}."

STORAGE_PARENT="${ROOT_DIR}/storage/app"
TARGET_PRIVATE="${STORAGE_PARENT}/private"
STAGED_PRIVATE="${STORAGE_PARENT}/.private.restore.$$"
OLD_PRIVATE="${WORK_DIR}/private-before-restore"
mkdir -p "${STORAGE_PARENT}" "${STAGED_PRIVATE}"
cp -R "${EXTRACT_DIR}/storage/app/private/." "${STAGED_PRIVATE}/" \
    || fail "Priprema privatnog storage-a nije uspela; sigurnosni backup je sačuvan u ${DESTINATION}."

if [[ -e "${TARGET_PRIVATE}" ]]; then
    mv "${TARGET_PRIVATE}" "${OLD_PRIVATE}" \
        || fail "Nije moguće skloniti trenutni privatni storage."
fi
if ! mv "${STAGED_PRIVATE}" "${TARGET_PRIVATE}"; then
    [[ ! -e "${TARGET_PRIVATE}" && -e "${OLD_PRIVATE}" ]] && mv "${OLD_PRIVATE}" "${TARGET_PRIVATE}" || true
    fail "Nije moguće aktivirati vraćeni privatni storage; sigurnosni backup je sačuvan u ${DESTINATION}."
fi

echo "Restore je završen. Sigurnosni backup trenutnog stanja nalazi se u: ${DESTINATION}"
