# SJC public metadata import

Source: https://sjcjournal.com/ and 19 linked archive issue pages; public editorial board at https://sjcjournal.com/editorial-board.

Local import: 39 new article drafts, 176 new author profiles, 7 editorial profiles. One of 40 discovered source articles already matched a local DOI and was skipped to preserve its content. Names were retained as displayed; no speculative merging of differently spelled names. Repeated editorial listings were deduplicated. No login accounts, passwords, reviewer assignments, article bodies, abstracts, images or PDFs were copied.

The JSON manifest at `storage/app/imports/sjc/metadata.json` includes source URLs, article dates, DOI and original PDF links. Original PDF links are provenance only, not downloaded files. Metadata for created records is retained in private `import.sjc.article.*` settings. Article drafts include an original-article link and clearly state that full content is pending source-file migration.

`journal:import-sjc-metadata {file}` previews counts. `--apply` imports only in local/testing environments and skips existing DOI/import IDs. A database backup was saved before import in `storage/app/backups/sjc-import/before-metadata-import.sql`.

The public editorial board lists 3 editors-in-chief and 4 editors. No named public reviewer directory was found; reviewer navigation leads to guidance or authenticated workspaces. A source database export is needed for private reviewer records. Full article/PDF migration requires supplied source files.

Validation: isolated import test passed (13 assertions), including draft-only creation, no account creation, repeat-import deduplication and preservation of edits. Production Azure was not changed by this import.

## Full article migration and reading experience — 11 September 2026

The 39 imported records are now published on localhost, preserving original publication dates. All 39 have validated local copies of their source PDFs; the one existing DOI match remains untouched. PDFs retain complete figures, tables, references and original author credits. Full content is displayed using an on-site PDF.js reader with previous/next pages, zoom, selectable page text and download. This is the published PDF layout, not a re-typeset HTML manuscript. Original-site reading links and inaccurate one-minute reading labels were removed. Printing opens the complete PDF.

`journal:import-sjc-pdfs {directory} --apply` validates SHA-256 and PDF signatures, targets imported IDs only, stores files on the private local disk, and preserves existing editorial PDF replacements. Source filenames, hashes and page counts are recorded in private import settings. Files remain editable/removable through the existing admin article-file tools. Database backup: `storage/app/backups/sjc-fulltext/before-pdf-import.sql`. Download manifest: `storage/app/imports/sjc/pdf-manifest.json`.

The admin dashboard now has one metrics row, two work queues and website shortcuts. Navigation groups core tasks and removes duplicate policy/workflow shortcuts. People → Author profiles edits the public Author model; Website → Editorial board edits public EditorialMember records. Roles and settings links respect the existing super-admin route boundary. Author profiles are compact, contributor summaries have two lines per author, and standards/resources appear before the footer.

Production Azure has not been modified.

## Production deployment — 11 September 2026

The publication and PDF migration described above has now been deployed to Azure as `journal-azure:sjc-20260911`. Production contains all 39 imported articles and PDFs, 176 imported authors and 7 editorial members. Existing production credentials and genuine submissions were preserved. See `docker/azure/README.md` for backups and verification.
