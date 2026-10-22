# Database backups

Automated, encrypted, signed backups of the MySQL database. Modelled on crow-reports' backup
service, adapted to an Artisan command in the existing app image.

Photos and videos on the SSD are **not** in these backups. This is the database only.

## What a backup is

```
mysqldump ──pipe──▶ zstd ──pipe──▶ age --encrypt -r <recipient> ──▶ <name>.partial
```

- **Dump:** `mysqldump --single-transaction --routines --triggers --events --hex-blob
  --databases laravel`, the same flags as the 2026-10-22 snapshot, plus
  `--ignore-table=laravel.backups --ignore-table=laravel.backup_restores`. Those two tables are the
  record of backups and restores. Leaving them out means a restore never rewinds that record:
  without this, restoring an older dump would drop the rows of every later backup while their
  files stayed on disk. The dump connects as `root`, because the app user lacks the `PROCESS`
  privilege mysqldump needs for tablespaces. `compose.yaml` sets the root password to
  `DB_PASSWORD`, and it goes to mysqldump through `MYSQL_PWD`, never the argv.
- **No plaintext on disk.** The three stages are chained with `proc_open` argv arrays
  (`App\Support\Backups\Pipeline`). There is no shell and no intermediate file. Every stage's
  exit code is checked, so a failed dump fails the backup even though zstd and age exit 0 on
  the truncated stream. A shell `a | b | c` without `pipefail` would report success.
- **Encrypted** to `BACKUP_AGE_RECIPIENTS` (age X25519 public keys). The private identity never
  lives on this machine. With no recipient configured, a backup is refused and recorded as
  failed.
- **Signed.** The age recipient is public, so encryption proves nothing about *who* made a
  file. Each backup gets a manifest, and the manifest is signed with an Ed25519 key that only
  this machine holds:

| file | what |
|---|---|
| `baradox-db-<UTC stamp>-<trigger>-<8 hex>.sql.zst.age` | the data |
| `….sql.zst.age.manifest` | JSON: `file`, `size`, `sha256` of the `.age` file, `created_at`, `trigger`, `database`, `migration`, `recipients`, `signing_key_sha256` |
| `….sql.zst.age.sig` | raw 64-byte Ed25519 signature over the manifest's exact bytes |

  Because the signature covers the hash of the **encrypted** file, a backup can be verified
  without decrypting it. The three files are written as `.partial` and renamed into place,
  with the `.age` renamed last, so a finished `.age` file always has its sidecars beside it.

- **Recorded** in the `backups` table: filename, size, sha256, trigger
  (`manual` / `scheduled` / `prerestore`), status (`running` / `succeeded` / `failed`),
  duration, error. A run that is killed midway leaves a `running` row and `.partial` files
  behind. The next run marks the row failed ("Interrupted") and deletes the partials.

## Where

```
Windows C:\Users\ASUS\baradox-stuff-backups
  → WSL /mnt/c/Users/ASUS/baradox-stuff-backups      (BACKUP_HOST_PATH in .env)
  → container /backups                                (laravel.test and scheduler)
```

The folder is off the Docker volume and off the SSD. `compose.yaml` refuses to start without
`BACKUP_HOST_PATH` (`${BACKUP_HOST_PATH:?…}`). This is the single destination: nothing is
copied off the machine automatically.

## Keys

| key | where | notes |
|---|---|---|
| age identity (private) | **offline**, with Baradox | needed to restore; never on this machine except for the duration of a restore |
| age recipient (public) | `.env` `BACKUP_AGE_RECIPIENTS` | |
| signing key (Ed25519 private, PKCS#8 PEM) | `storage/app/private/backup-keys/signing.pem`, mode 600 | gitignored; `BACKUP_SIGNING_KEY` overrides |
| verification key (public, SPKI PEM) | `storage/app/private/backup-keys/signing.pub.pem` | **keep an offline copy next to the age identity**; `BACKUP_VERIFY_KEY` overrides |

`php artisan backup:keygen` creates the pair and never overwrites an existing one. A fresh
machine generates a *new* signing pair, so to trust older backups there, point
`BACKUP_VERIFY_KEY` at the offline copy of the old public key.

The keys are standard PEM, so a signature can be checked with no PHP at all:

```bash
openssl pkeyutl -verify -pubin -inkey signing.pub.pem -rawin \
  -in <file>.manifest -sigfile <file>.sig      # "Signature Verified Successfully"
sha256sum <file>                               # must equal "sha256" in the manifest
```

## Commands

All go through Sail: `./vendor/bin/sail artisan …`.

| command | what | exit codes |
|---|---|---|
| `backup:run` | one manual backup, then retention | 0 ok, 1 failed (recorded) or busy |
| `backup:run --scheduled` | the same, recorded as `scheduled` | |
| `backup:run --scheduled --if-stale` | only if the last success is older than 24 h; busy counts as a skip | 0 ok or skipped, 1 failed |
| `backup:verify {id}` | signature + size + sha256 of row `id`'s file, no decryption; prints the recipients it was encrypted to | 0 ok, 3 missing, 2 config (no key), 6 bad signature, 7 file doesn't match |
| `backup:restore {file} --identity=PATH` | see below | 0 ok, 1 failed or cancelled, 2 usage/config, 3 missing, 6, 7 |
| `backup:keygen` | create the signing pair | 0, 1 if a key exists |

Backups and restores share an `flock` on `storage/framework/backups.lock`. It works across
the app and scheduler containers through the shared bind mount, a killed process releases
it, and a restore rewriting the `cache` table cannot clear it, which is why it isn't a cache
lock.

## Schedule

The `scheduler` service in `compose.yaml` (the same `sail-8.5/app` image, no SSD mount, no
ports) starts with `sail up -d`. Its command is:

1. **Startup catch-up:** `backup:run --scheduled --if-stale`. A failure is printed and recorded,
   and the scheduler starts anyway.
2. `exec php artisan schedule:work`, which runs `routes/console.php`:
   - `backup:run --scheduled` daily at `BACKUP_DAILY_AT` (03:00) Asia/Baghdad
   - `backup:run --scheduled --if-stale` hourly at :30. This catches a 03:00 the machine slept
     through: sleep doesn't restart the container, so the startup check alone would miss it.

It depends only on mysql being healthy, not on `laravel.test`, so backups still run when the
SSD mount is dead and the app container won't start. It is the only service with a restart
policy, `on-failure`: if `schedule:work` dies, Docker brings it back, which re-runs the startup
catch-up. A clean `sail down` still stops it.

Logs: `storage/logs/backups.log` (every success, failure, retention removal and restore) and
`docker logs baradox-stuff-scheduler-1`.

## Retention

After each successful backup, `BackupRetention` prunes each trigger on its own:

- **scheduled:** the newest of each of the last `BACKUP_KEEP_DAILY` (7) **days that have a
  scheduled backup**, and the newest of each of the last `BACKUP_KEEP_WEEKLY` (4) **ISO weeks
  that have one**, in Asia/Baghdad time. Days without a backup don't count, so a machine left
  off for a fortnight doesn't come back to an empty folder.
- **manual:** the newest `BACKUP_KEEP_MANUAL` (10). They are kept out of the day/week buckets
  on purpose: under one shared policy, a backup taken by hand before a risky change was
  superseded by the next backup that same day.
- **prerestore:** never pruned.

Everything else is deleted: all three files, then the row. A file that can't be deleted keeps its
row and is logged. Files in the folder with no row (copied in by hand) are never touched.

## Failure visibility

- `App\Models\Backup::attention()` returns `null` when all is well, otherwise a sentence:
  the newest finished backup failed (with its error), or no backup has succeeded in 24 h, or
  none ever has. This is the flag the Backups UI reads. `Backup::isStale()` and
  `Backup::lastSuccess()` are its parts.
- Every failure is also written to `storage/logs/backups.log`. A failure that happens before
  a row can be written (database down) exists only there.

## Restore

CLI only. There is no route, and the identity is a path given at run time, never stored:

```bash
cp /path/to/age-identity.txt storage/app/private/restore-identity.txt     # visible in the container
./vendor/bin/sail artisan backup:restore <filename> --identity=storage/app/private/restore-identity.txt
shred -u storage/app/private/restore-identity.txt
```

In order:

1. The filename must match the backup naming pattern.
2. **Verify before decrypting.** The signature and the hash are checked against
   `BACKUP_VERIFY_KEY`. A bad signature or a changed file is refused (6 / 7) before age reads
   anything.
3. The manifest's database must be the one the app uses. The dump carries `USE \`laravel\``,
   so a mismatch would land the data somewhere else. Both metadata tables must exist; on a
   fresh machine, run `migrate` first, then restore.
4. **Typed confirmation:** `RESTORE laravel`. A non-interactive run is refused.
5. **Pre-restore backup** of the current database (`trigger = prerestore`, never pruned). If it
   fails, nothing is restored.
6. `age -d | zstd -d | mysql` as root. Every table in the dump is dropped and recreated;
   `backups` and `backup_restores` are not in the dump and are untouched.
7. The restore is recorded in `backup_restores` (filename, sha256, status, duration, error)
   and logged. If the code has newer migrations than the restored DB, it says to run `migrate`.

**Not atomic.** mysql applies the dump statement by statement, so a failure midway leaves a
partly restored database. The error message names the pre-restore backup, which holds the
state from just before. Sessions and the database cache come back as they were at backup time.

### Fresh machine

Bring the stack up, run `migrate`, `backup:keygen`, and set `BACKUP_VERIFY_KEY` to the
offline copy of the old public key. Copy the backup's three files into the backups folder,
then restore as above.

## Testing it

`tests/Feature/Backups/*` covers a real dump → zstd → age → sign round trip into a temporary
folder with a throwaway age identity: verify, tamper detection (6 and 7), a missing recipient,
a failing dump not being masked by the pipe, `--if-stale`, retention selection (including
manual and prerestore buckets), and `openssl` reading the PEM keys.
