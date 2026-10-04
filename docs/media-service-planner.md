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

Named production accounts (Andrew, Erin, Elle-Belle, Emery, Trav) cannot be individually verified from repository source. After deployment, confirm they can sign in with their existing accounts. No account creation or role changes are part of this release. The shared read-only Service Sheet and service notes are available to existing signed-in team accounts. Pastor/admin reuse the existing full-access gate and can edit every planner section. Music Minister edits worship; Media and Sound edit station checklists, house sound readiness, and shared instructions. Other existing team roles read the saved sheet. These checks apply to navigation, direct routes, and write endpoints. Station names are operational sections, never roles. Existing featured-content restrictions stay intact.

## Using the planner

Open **Service Planner**, then select a Sunday or enter its date. A repeated date opens the same record. The header displays the Sunday date and ISO week number; match the date when the church's folder numbering differs.

The left Service Planner accordion offers Pastor, Music Minister, Media Team, Service Sheet, and service notes according to existing access. The Sunday overview uses the same access gates. Pastor has separate Sermon and Announcements subsections. Announcement notes save with that Sunday; published current/upcoming church announcements come from the existing announcement table. Expired announcements are excluded, and archive snapshots retain their original content. Pastor and Music Minister see live media readiness within their own work areas. The pastor enters sermon information once. Worship songs support add, remove, edit, keys, vocalists, notes/instruments, and Up/Down ordering. Every station reads that same information; FOH can read the same worship plan using an existing backend account.

Check a task to save it immediately. Readiness derives from saved completion. Every pre-service task, including the READY marker, must be complete for the station to be READY. Computer 2 has the supplied service-start and service-end phases; all of its tasks must be checked for COMPLETE. A READY/COMPLETE marker cannot bypass preceding station tasks. Sermon readiness requires a title and primary scripture; worship readiness requires a song.

Media Team selects Front of House or any combination of Computers 1–4. Computer 2 and 3 remain independent. Each computer checklist groups its existing tasks into numbered process accordions; opening another process closes the previous one for that station. Labels describe equipment and functions without assigning people. Front of House shares an explicit human readiness status; detailed house sound procedures were not supplied, so no equipment instructions are invented.

**Open Guide** opens only that task in a separate closeable Station Manual window. The manual also works independently of a Sunday. Media/Sound and full-access users can edit reusable numbered steps, notes/warnings, links, and reference images, including JPG/PNG/WebP screenshot uploads in the existing `uploads/service-guides` storage. No inline guide editor or instruction panel remains in the checklist. **Pop Out Checklist** opens a separate movable desktop window with Checklists, Service Sheet, and Notes pages. Multiple selected stations switch within that window. Phones retain the checklist on the normal page. The YouTube/embed guide describes the operator’s existing Share → Embed → copy → website embed box → Start Live procedure; it does not control the stream. Instructions are shared among open and future Sundays. The initial instructions follow the supplied workflow; exact local file locations, approved OBS profile/scene collection names, Dante routing/devices, template settings, and camera references must be completed by the regular operators. No credentials or stream keys belong in instructions.

Shared status and checklist state refresh every eight seconds and when returning to the page. Forms use revision checks and do not silently overwrite a newer edit. If a form conflicts, its unsaved text remains visible; copy it before reloading. A lost connection displays an error and retries reads. A failed checkbox write is visibly reverted rather than presented as saved.

At the end of the Sunday, use **Preserve this completed Sunday** to archive the record. The plan, completion state, task titles, instruction snapshot, and announcement snapshot then become read-only. Subsequent instruction edits cannot alter an archived Sunday's snapshot. Creating a new Sunday never reuses the previous Sunday's completion state. There is no delete-service control.

No new code calls or controls OBS, YouTube, Dante, Fender Studio, EasyWorship, cameras, or audio hardware. Existing website livestream publishing code remains unchanged.

## Additive storage and deployment

`database/service_planner.sql` adds only `service_plans`, `service_task_definitions`, and `service_task_completions`. It does not alter existing tables. Definitions seed idempotently from `default-tasks.json`; subsequent deployments preserve operator-edited instructions. SQL uses the host's existing PDO connection and prepared statements. State changes require the existing login and CSRF token.

Media Management is one left navigation accordion with Announcements, Ministries, Youth announcements, Livestream, Featured pages, and Photos & galleries. The youth list filters the existing announcement records and preselects Youth on the existing creation form. Ordering remains in the full announcement list so filtered moves cannot inadvertently reorder other categories. Ministries uses an implemented host editor if present; otherwise its subsection clearly explains the missing editor and links to existing announcements.

The existing cPanel deploy command remains in `.cpanel.yml`. `deploy/feature.php` integrates the two navigation links into the host's existing PHP shell rather than replacing that shell. It copies only the feature/planner dependencies, installs the additive schema using the host's current DB configuration, backs up changed destination files, writes new dependencies before the shell integration, and rolls back applied files if a write fails. It leaves `php/config.php` and authentication bootstrap untouched. If the live shell differs from the inspected structure, deployment stops before live file writes so the mismatch can be reviewed. Additive tables may remain after a later file-write failure; existing tables and records are not removed.

In cPanel Git Version Control for `/home/golibert/repositories/glc_live_site`, use **Update from Remote → Deploy HEAD Commit**. The live document root is `/home/golibert/public_html`.

## Verification

Local checks use an isolated MariaDB database, not the inaccessible production database. `tests/service_planner_test.php` requires a `_test` DSN and checks seed idempotence, all 68 supplied tasks, valid Sunday dates, safe reference URLs, unchanged unrelated navigation, unique shared Sundays, sermon/worship persistence, cross-connection visibility, stale-edit rejection, readiness, broadcast completion, archived read-only state, instruction snapshots, and independent next Sundays. Existing announcement ordering checks also run.

Browser integration checks cover the seven existing role keys through the unchanged login, featured-content visibility gates, shared saves between desktop and a phone context, song ordering, unauthorized and CSRF rejection, and layouts at 390px, 768px, and 1366px. Deployment fixture checks cover repeat deployments, retained live HTML, unchanged config/auth, backups, syntax of the patched shell, and refusal of an incompatible shell.

Required live acceptance: sign in with the named current accounts, open both new navigation destinations, confirm existing MMS create/edit/upload/publishing operations, create the intended Sunday, compare two real devices, and have regular station operators refine/validate the local how-to details. Production account verification and hardware procedure validation are not implied by local tests.

### Reproduce isolated checks

Do not point test fixtures at production. `tests/service_planner_fixture.php` requires `DB_NAME=church_test`, loads the existing schema, creates seven QA accounts with the local test password, resets only the isolated planner data, and seeds a preview Sunday. With the application’s normal local DB environment variables configured, run the fixture, then `tests/service_planner_test.php` with `PLANNER_TEST_DSN` containing `_test`. Start a local PHP server with PDO MySQL and file uploads enabled.

`tests/service_planner_browser.cjs` uses Playwright and `PLANNER_TEST_BASE` (defaults to `http://127.0.0.1:8090`; only localhost/127.0.0.1 allowed), with optional `PLANNER_CHROME_EXECUTABLE`. It tests existing login roles, hidden/blocked editors, CSRF rejection, shared checkbox saves, process accordions, editable screenshot guides, closeable popup windows, two-station switching, preserved popup page navigation, announcement notes, live FOH readiness, youth filtering, and no horizontal overflow at 375/390/768/1366px. Preview PNGs in `docs/previews` contain isolated QA content.

`tests/service_planner_deploy_test.py` requires the same isolated `DB_NAME=church_test` environment and a PHP CLI with PDO MySQL. Optional `PLANNER_TEST_PHP` selects the executable; `PLANNER_TEST_PHP_ARGUMENTS` is a JSON argument array. It deploys twice into a temporary host copy and verifies unchanged config/auth, preserved custom HTML, installed dependencies, shell syntax, backups, and refusal of an incompatible shell. It never targets the real document root.

## Mobile editor refinement

Each Pastor, Music Minister, and Media Team screen now starts with its position and a concrete first step. Editors show the work before cross-team readiness. Pastor's additional Media instructions are grouped separately. Song title comes first, key and lead share a compact row, and optional vocals/notes are collapsed. Every song's Up, Down, and Remove controls sit together underneath its fields; saved and newly added songs use the same server-rendered template. Ordering, removal, and saving retain the existing persistence behavior.

Phone announcement lists replace the wide table presentation with readable entries and controls beneath the information. Gallery controls, file fields, checkboxes, and bare text/URL inputs use consistent compact styling. Existing livestream handlers and equipment workflows remain unchanged. The deployment moves the workspace stylesheet after the host CSS and updates cache versions so existing phones load the new controls. Browser coverage includes all implemented MMS pages and both role editors at 320, 375, 390, and 768px, plus saved/new-song footer positioning and order/save behavior.
