# Public profile photos — 9 October 2026

Approved users with verified email can upload JPG, PNG or WebP photos from their profile page. Authors and contributors use Author → Profile; editors and reviewers use Profile in their portal; administrators and Super Admins use My account → Public profile. The upload limit is 5 MB and the existing image optimization policy applies.

Saving a photo publishes a member profile at `/people/{id}`, linked from the upload form. Public member profiles can be found through Contributors → View member profiles. They display the photo, name, organization, designation and published work. Emails, phone numbers, account roles and reviewer expertise are not included. Accounts that are pending, unverified or inactive are excluded from member profiles.

Photos also appear in approved public comments and replies. Registered editors with photos appear on the editorial-board page. Existing editorial-board portrait uploads now render for editors and reviewers as well as the chief editor. A changed account photo synchronizes the linked author portrait, including for users with multiple roles; author profiles and article bylines display the current image.

Uploads update only the signed-in account. Invalid images leave the current photo intact; saving without a replacement retains it. Photo updates cannot change account approval, passwords or someone else's image.

Verification: **230 tests passed, 2,741 assertions**. This includes image upload, replacement and retention for all six roles, anonymous public rendering, author bylines, editorial-board portraits, public comment/reply images, hidden inactive/unverified/pending profiles, invalid uploads and authorization boundaries. Deployment rehearsal checks all sixteen source files, new view installation, public storage mapping, application boot, rollback guards and restoration of the regular mail worker. No schema migration or asset rebuild is required.

The production audit sends no notifications and changes no member photos. Deployment evidence and temporary helpers remain private and excluded from Git.
