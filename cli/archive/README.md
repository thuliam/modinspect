# cli/archive

One-off scripts kept for the record: data repairs, backfills, probes and catalog generators from earlier milestones.
Nothing in the application, the tests or the runbooks calls them (checked 2026-11-28, LS-89 BE-4).

Before running one, read it: several write to the database, and they were written for the data of their day.
Run from the project root, for example `php cli/archive/ram_backfill.php`.

Regular tools stay in `cli/`.
