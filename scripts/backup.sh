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
    echo "Backup nije uspeo: $*" >&2
    exit 1
}

cd "${ROOT_DIR}"
[[ -d .ddev ]] || fail "DDEV konfiguracija nije pronađena u ${ROOT_DIR}."

if ! ddev_status="$(ddev describe --json-output --skip-hooks 2>&1)"; then
    fail "DDEV projekat nije pokrenut. Pokrenite 'ddev start' pa pokušajte ponovo."
fi
printf '%s\n' "${ddev_status}" | grep -Fq "\"approot\":\"${ROOT_DIR}\"" \
    || fail "DDEV aktivni projekat nije InvoiceCore root: ${ROOT_DIR}"
printf '%s\n' "${ddev_status}" | grep -Fq '"status":"running"' \
    || fail "DDEV projekat nije pokrenut. Pokrenite 'ddev start' pa pokušajte ponovo."

mkdir -p "${DESTINATION}" || fail "Nije moguće kreirati destinaciju: ${DESTINATION}"
WORK_DIR="$(mktemp -d "${TMP_ROOT%/}/invoicecore-backup.XXXXXX")"
PAYLOAD="${WORK_DIR}/payload"
mkdir -p "${PAYLOAD}/storage/app/private"

if [[ -d storage/app/private ]]; then
    cp -R storage/app/private/. "${PAYLOAD}/storage/app/private/" \
        || fail "Kopiranje storage/app/private nije uspelo."
fi

ddev export-db --file="${PAYLOAD}/database.sql" \
    || fail "MariaDB SQL dump nije uspeo."
[[ -s "${PAYLOAD}/database.sql" ]] || fail "SQL dump je prazan."

created_at="$(date '+%Y-%m-%dT%H:%M:%S%z')"
file_count="$(find "${PAYLOAD}/storage/app/private" -type f | wc -l | tr -d ' ')"
database_sha256="$(shasum -a 256 "${PAYLOAD}/database.sql" | awk '{print $1}')"
cat > "${PAYLOAD}/manifest.txt" <<EOF
InvoiceCore local backup
format_version=1
created_at=${created_at}
database_dump=database.sql
database_sha256=${database_sha256}
storage_root=storage/app/private
private_file_count=${file_count}
EOF

timestamp="$(date '+%Y%m%d-%H%M%S')"
archive_name="invoicecore-${timestamp}.tar.gz"
archive_tmp="${WORK_DIR}/${archive_name}"
archive_final="${DESTINATION}/${archive_name}"
checksum_tmp="${WORK_DIR}/${archive_name}.sha256"
checksum_final="${DESTINATION}/${archive_name}.sha256"

[[ ! -e "${archive_final}" && ! -e "${checksum_final}" ]] \
    || fail "Backup sa imenom ${archive_name} već postoji; prethodni backup nije promenjen."

tar -czf "${archive_tmp}" -C "${PAYLOAD}" manifest.txt database.sql storage/app/private \
    || fail "Kreiranje tar.gz arhive nije uspelo."
gzip -t "${archive_tmp}" || fail "Integritet gzip arhive nije potvrđen."
tar -tzf "${archive_tmp}" >/dev/null || fail "Struktura tar arhive nije čitljiva."
shasum -a 256 "${archive_tmp}" > "${checksum_tmp}" || fail "Kreiranje SHA-256 checksum-a nije uspelo."

mv "${archive_tmp}" "${archive_final}" \
    || fail "Nije moguće premestiti kompletan backup u destinaciju."
if ! mv "${checksum_tmp}" "${checksum_final}"; then
    rm -f "${archive_final}" || true
    fail "Nije moguće premestiti checksum; nepotpuni novi backup je uklonjen."
fi

echo "Backup je uspešno kreiran: ${archive_final}"
echo "Checksum: ${checksum_final}"
