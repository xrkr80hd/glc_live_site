# Media Management and Sunday Service Planner

This change belongs to the existing PHP live website (`xrkr80hd/glc_live_site`). It does not modify the separate app, authentication, accounts, roles, staff responsibilities, equipment, or existing Sunday operating procedures.

## Inspection and compatibility

The implemented admin shell is `php/admin/layout.php`. Authentication uses `admin_users`, the existing PHP session, `admin_require_login()`, and the role checks in `php/admin/bootstrap.php`. Existing role keys are `pastor`, `admin`, `music_minister`, `worship_team`, `youth_minister`, `media`, and `sound`. No role or user records are modified.

| Existing publishing tool | Routes and data | Existing gate |
| --- | --- | --- |
| Announcements | `php/admin/announcements/`: list, create, edit, delete, reorder. `announcements` and public `announcement_photos` references. Homepage/youth APIs retain their publication/date filters. The implemented forms edit text and dates, not photo uploads. | Existing admin login |
| Featured page and homepage feature | `php/admin/features/index.php`, `php/features.php`, `php/api/feature.php`, `api/feature/`, and the public OCC page. `site_features`, `feature_contacts`, existing assets and `uploads/features`. | Existing pastor/admin/media gate |
| Albums and photos/videos | `php/admin/youth-albums/`: album create/edit/delete and `manage-media.php` uploads/removals. `youth_albums`, `youth_media`, existing `uploads/` storage. Public youth API remains unchanged. | Existing admin login |
| Youth scripture | `php/admin/youth-scripture/index.php`, `youth_scripture`, youth API. | Existing admin login |
| Website livestream publishing | `php/admin/stream/index.php`, `live_streams`, existing status files and public current-stream API. | Existing admin login |

The repository's homepage ministry/pastor sections are static HTML. Ministries, social links, youth ticker, sermon archive, and service-song navigation entries were placeholders, not implemented publishing editors. They are not replaced by invented tools. Any additional implemented publishing destinations in the compatible live menu are retained in Media Management. Unrelated Requests, Team & Access, and Operations navigation stays in place. Dashboard summaries and existing publishing routes continue to work.

The full schema also contains `sermons`, `seasonal_features`, `gallery_videos`, `archived_sermons`, `team_members`, `team_roles`, and `service_song_lists`. These are not the active PHP login implementation. `service_song_lists` requires a `team_roles` foreign key and has no implemented PHP editor or consumer in this checkout; it is not silently remapped into the `admin_users` permission system. Existing records remain untouched. The planner's ordered worship data is stored once in its shared Sunday record, not copied into a second station-specific plan. Existing sermon archive/public media records likewise are not used for upcoming operational drafts.

Named production accounts (Andrew, Erin, Elle-Belle, Emery, Trav) cannot be individually verified from repository source. After deployment, confirm they can sign in with their existing accounts. No account creation or role changes are part of this release. The planner reuses the existing login-only access policy used by the implemented announcements, albums, and livestream tools. All currently signed-in backend operators can view and update the shared Sunday; station names are operational sections, never roles. Existing featured-content restrictions stay intact.

## Using the planner

Open **Service Planner**, then select a Sunday or enter its date. A repeated date opens the same record. The header displays the Sunday date and ISO week number; match the date when the church's folder numbering differs.

The Main Service Feed links to sermon information, the ordered worship plan, and all four computer checklists. The pastor enters sermon information once. Worship songs support add, remove, edit, keys, vocalists, notes/instruments, and Up/Down ordering. Every station reads that same information; FOH can read the same worship plan using an existing backend account.

Check a task to save it immediately. Readiness derives from saved completion. Every pre-service task, including the READY marker, must be complete for the station to be READY. Computer 2 has the supplied service-start and service-end phases; all of its tasks must be checked for COMPLETE. A READY/COMPLETE marker cannot bypass preceding station tasks. Sermon readiness requires a title and primary scripture; worship readiness requires a song.

**HOW DO I DO THIS?** opens only that task's instructions. Its nested instruction editor supports plain-text numbered steps, notes/warnings, reference links, and an existing uploaded or web-hosted reference image. This does not add a second upload/storage system. Instructions are shared among open and future Sundays. The initial instructions follow the supplied workflow; exact local file locations, approved OBS profile/scene collection names, Dante routing/devices, template settings, and camera references must be completed by the regular operators. No credentials or stream keys belong in instructions.

Shared status and checklist state refresh every eight seconds and when returning to the page. Forms use revision checks and do not silently overwrite a newer edit. If a form conflicts, its unsaved text remains visible; copy it before reloading. A lost connection displays an error and retries reads. A failed checkbox write is visibly reverted rather than presented as saved.

At the end of the Sunday, use **Preserve this completed Sunday** to archive the record. The plan, completion state, task titles, and instruction snapshot then become read-only. Subsequent instruction edits cannot alter an archived Sunday's snapshot. Creating a new Sunday never reuses the previous Sunday's completion state. There is no delete-service control.

No new code calls or controls OBS, YouTube, Dante, Fender Studio, EasyWorship, cameras, or audio hardware. Existing website livestream publishing code remains unchanged.

## Additive storage and deployment

`database/service_planner.sql` adds only `service_plans`, `service_task_definitions`, and `service_task_completions`. It does not alter existing tables. Definitions seed idempotently from `default-tasks.json`; subsequent deployments preserve operator-edited instructions. SQL uses the host's existing PDO connection and prepared statements. State changes require the existing login and CSRF token.

The existing cPanel deploy command remains in `.cpanel.yml`. `deploy/feature.php` integrates the two navigation links into the host's existing PHP shell rather than replacing that shell. It copies only the feature/planner dependencies, installs the additive schema using the host's current DB configuration, backs up changed destination files, writes new dependencies before the shell integration, and rolls back applied files if a write fails. It leaves `php/config.php` and authentication bootstrap untouched. If the live shell differs from the inspected structure, deployment stops before live file writes so the mismatch can be reviewed. Additive tables may remain after a later file-write failure; existing tables and records are not removed.

In cPanel Git Version Control for `/home/golibert/repositories/glc_live_site`, use **Update from Remote → Deploy HEAD Commit**. The live document root is `/home/golibert/public_html`.

## Verification

Local checks use an isolated MariaDB database, not the inaccessible production database. `tests/service_planner_test.php` requires a `_test` DSN and checks seed idempotence, all 68 supplied tasks, valid Sunday dates, safe reference URLs, unchanged unrelated navigation, unique shared Sundays, sermon/worship persistence, cross-connection visibility, stale-edit rejection, readiness, broadcast completion, archived read-only state, instruction snapshots, and independent next Sundays. Existing announcement ordering checks also run.

Browser integration checks cover the seven existing role keys through the unchanged login, featured-content visibility gates, shared saves between desktop and a phone context, song ordering, unauthorized and CSRF rejection, and layouts at 390px, 768px, and 1366px. Deployment fixture checks cover repeat deployments, retained live HTML, unchanged config/auth, backups, syntax of the patched shell, and refusal of an incompatible shell.

Required live acceptance: sign in with the named current accounts, open both new navigation destinations, confirm existing MMS create/edit/upload/publishing operations, create the intended Sunday, compare two real devices, and have regular station operators refine/validate the local how-to details. Production account verification and hardware procedure validation are not implied by local tests.
