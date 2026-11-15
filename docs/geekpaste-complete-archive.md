# Complete GeekPaste archive

The normal importer attaches historical submissions only when the student, course
and task are unambiguous. `code-checking:archive-legacy` additionally retains the
complete unmodified source export, including personal snippets, missing contexts,
deleted task definitions and every saved similarity edge. It changes no grades,
rewards, ownership or source rows.

Generate the source with `legacy_export.export_legacy(..., include_personal=True,
service_state=True, environments=...)` in a read-only repeatable-read transaction.
An optional `reuse_blobs` list permits verified hard links to previous immutable
exports on the same filesystem. The export includes the durable ZIP directory,
environment source files and fixtures, not just currently referenced originals.
Archive Redis counters only as DUMP/TTL records; never restore expired limits.

Add a separate read-only PostgreSQL custom-format backup as `database.dump` and
its SHA-256 to `manifest.files`. Record its own snapshot time. Keep the entire
directory private. A source runtime environment file can be supplied separately;
it is encrypted with Coding Projects' Laravel Crypt before permanent storage.
Keep the Coding Projects APP_KEY in the normal protected backup process.

```sh
php artisan code-checking:archive-legacy /private/full-export --actor=1
php artisan code-checking:archive-legacy /private/full-export --actor=1 --apply \
  --environment=/private/source-runtime.json
```

All sidecars and source blob descriptors are checked before application writes.
Code, tests, checker sources and binary test fixtures go to database artifacts;
binary fixtures use a reversible base64 representation. Original ZIP files and
redundant backups go to private S3. Existing verified originals are reused.
The archive becomes visible only when all content has been stored. Repeated
application of an identical manifest is idempotent.

Only administrators can list archives at
`GET /insider/settings/code-executor/legacy-exports`. Download
`.../legacy-exports/{manifest_sha256}/files/archive-index.json` for the inventory.
The index describes the exact ordered parts of every original sidecar. Concatenate
the parts to reconstruct each file and verify its original size and SHA-256.
The blob map parts are similarly concatenated JSONL; retrieve each mapped blob at
`GET /insider/settings/code-executor/legacy-artifacts/{blob_id}`. Files remain
private and downloads use the existing authenticated/S3 signed-download mechanism.

Run `scripts/integration/executor/verify-legacy-archive.php /private/full-export`
inside the application with a read-only database connection. It verifies sidecar
reconstruction, every database blob, and the verified S3 original inventory.
Neither this command nor successful verification authorizes deleting GeekPaste.

Historical reports are preserved exactly as saved. The old IO checker stored
only verdicts for hidden tests. The submission's imported IO definition also
retains the GP inputs and expected answers; these are now available directly in
the corresponding test rows, with explicit archive provenance. They are never
read from the current task configuration. Matching requires the complete test
count and every public/private flag to agree. The export does not establish the
test-set version at the original execution time, and actual hidden-test output
cannot be reconstructed. Both limitations remain visible in the report.

Teachers may inspect the archived fixture. Students may reveal a failed hidden
fixture under the existing task, price and balance rules. The purchase is scoped
to the author, task and immutable input/expected hashes, is reusable between runs,
and never changes historical grades. Missing fixtures and incompatible archived
sets cannot be purchased. Metadata remains body-free; test contents and downloads
require a separate authorized request.
