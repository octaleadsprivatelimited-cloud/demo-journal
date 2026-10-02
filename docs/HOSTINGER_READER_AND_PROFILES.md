# Hostinger reader and profile images

The live site is https://lightcoral-chicken-832806.hostingersite.com.

PDF.js uses a Vite worker build ending in `.js`. Hostinger previously served its `.mjs` worker as `text/plain`, which prevented the embedded reader from loading. The public `.htaccess` also declares JavaScript MIME types for `.js` and `.mjs`. After a build, upload the complete `public/build` directory together with its manifest. Verify actual canvas rendering, next/previous page navigation, zoom and extracted text in a browser; a successful PDF download alone does not verify the reader.

Authors upload portraits under Author studio → Profile. Editors use My profile in their workspace or account menu. Both accept JPG, PNG and WebP up to 5 MB, show a preview before saving, retain the current photo when no replacement is selected, and display the saved portrait in the account header. Author updates also change their public contributor portrait. The editor endpoint updates only the authenticated account and remains restricted to verified, active editors.

Lossless compression remains available when jpegoptim/OptiPNG can run. Shared hosts without those tools retain valid originals within the limit; validation is still enforced.

The Hostinger installation separates `journal_app` from `public_html`. Preserve its production `.env`, SQLite database, storage, public storage root and deployment-specific bootstrap/public paths when updating source files. Back up replaced files outside the public web directory and clear Laravel view/route/config caches after updating. The reader/profile update requires no database migration or account changes.

Validation: the regression run passed 148 tests initially; its single failure exposed an omitted archive issue description. After restoring that description, all 18 affected tests passed. Production build succeeded. The live reader rendered the ten-page anaphylaxis article, including page navigation and zoom; the worker returned `application/javascript`. Protected profile routes continued to redirect guests to sign-in.
