# User-facing error popups

The website, login forms and role panels share an accessible error dialog. It displays the reason, corrective action and a stable error code. It supports Escape, restores keyboard focus, and uses text-only DOM insertion for error content. Validation errors still remain next to the original form. Browser-native required/format failures also use the dialog.

Original images must be **5 MB (5 × 1024 × 1024 bytes) or smaller**. This is checked in the browser and on the server. The existing **1,000,000-byte final image limit** still applies after lossless compression. Static SVGs must already fit the final size limit because their vector bytes are preserved. Documents are limited to 20 MB, a complete request to 32 MB, and PHP accepts at most 20 files per request; individual workflow fields may have smaller count limits.

| Code | Meaning / next step |
|---|---|
| image_too_large | Original image exceeds 5 MB; choose a smaller original. |
| image_output_too_large | Lossless output cannot fit 1 MB; upload a smaller original. |
| image_invalid | Invalid image or more than 40 megapixels. |
| image_format_unsupported | Use JPG, PNG, WebP or self-contained SVG. |
| image_compression_failed | Compression failed; try a smaller valid image. |
| image_processing_timeout | Processing budget exhausted; split the image batch. |
| svg_too_large | SVG exceeds 1 MB. |
| svg_invalid / svg_unsafe | SVG is malformed or contains unsupported active/external content. |
| upload_incomplete | Upload was interrupted or exceeded PHP's limit; reselect and retry. |
| document_too_large | Document exceeds 20 MB. |
| too_many_files | More than 20 files selected; split the batch. |
| upload_too_large | Complete request exceeds 32 MB. |
| required_field / invalid_format / invalid_value | Correct the named form field. |
| validation_failed | Review the field errors returned by the server. |
| authentication_required / session_expired | Sign in or refresh the form; retain unsaved text first. |
| access_denied | This account cannot perform the action. |
| version_conflict | Record changed or action is no longer available; review current state. |
| not_found / method_not_allowed / invalid_request | Resource or request is unavailable; return to the correct form. |
| rate_limit_exceeded | Wait before retrying. |
| storage_unavailable | Storage operation failed; keep originals and retry once available. |
| request_timeout / network_error | Autosave could not finish; keep the page open and retry. |
| service_unavailable / server_error / request_failed | General fallback; retry later or contact the administrator with the code. |

Laravel exception JSON responses include `code` and `X-Error-Code`. Validation JSON also includes `error_codes` keyed by field, alongside the existing `errors`. Unknown server exceptions use a safe message even with debug enabled. The exception remains available in server logs. Nginx's oversized-request page works without PHP; `/api/` requests receive JSON instead.

Codes identify error categories, not unique incident IDs. Unexpected infrastructure failures cannot always execute website JavaScript (for example, complete server/network outages). Form input and selected files are not persisted by the error dialog. This implementation does not deploy to a remote production host automatically.
