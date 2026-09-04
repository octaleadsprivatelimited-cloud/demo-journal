# Homepage article selection

Admin → Articles → Manage opens either the article record or its manuscript workflow. In **Homepage hero**, select **Featured article**, **Latest article**, or **Hidden from hero**, then save.

Selections can be prepared before publication, but only published, non-deleted articles with a publication date at or before the current time appear. Workflow publication checks still apply. Admins can change placement after publication without unlocking manuscript metadata. Authors, reviewers, and editors cannot change hero placement.

The carousel displays featured selections first, followed by latest selections; each group is ordered by publication date, newest first. One article appears once. Hiding an article from the hero does not remove it from the archive or recently published list. Existing featured flags remain selected; latest placement defaults to off.

With multiple selections, the carousel advances every seven seconds and provides previous, next, and pause/play controls. Hover pauses temporarily. Keyboard focus stops rotation until play is selected. Reduced-motion preferences disable automatic rotation initially. A single selection has no controls; an empty selection shows the compact journal introduction.

Deployment requires the `2026_09_04_000500_add_homepage_latest_to_articles` migration and rebuilt Vite assets.

Validation: HomepageHeroTest covers admin selection after workflow lock, role restrictions, publication filtering, ordering, empty and single selection. Existing publication and manuscript workflow tests also pass. An isolated browser preview verified rotation, wraparound navigation, keyboard-focus pause, reduced motion, and mobile overflow.
