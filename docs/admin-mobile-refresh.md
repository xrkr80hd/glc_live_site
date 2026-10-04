# Admin mobile refresh

The live PHP backend now uses a distinct pale green hero with dark green text for Pastor, Music Minister, and Media Team workspaces. Each hero names the workspace, gives one short direction, and links directly to the relevant form, Sunday picker, or station selector. Archived services keep their read-only state.

The redundant Content Menu control was removed. The original fixed left-edge navigation tab opens the existing drawer on phones, tablets, and desktop. It stays near the top when scrolling, and its close control remains within the screen. Existing navigation permission checks remain in place.

Account management uses one compact list: username and existing role, status, labeled dates, and Edit/Disable/Activate controls. On phones the controls sit beneath the details; larger screens display compact rows. Add/edit forms use single-column mobile fields and readable action buttons. Long usernames and confirmation messages wrap. Existing account validation, authentication, role definitions, role-change rules, CSRF, self-disable protection, and last-active-role protection are unchanged. The deployment does not replace the toggle handler or authentication bootstrap.

## Validation

- `tests/admin_mobile_browser.cjs`: account list/add/edit and role gates; create, edit, disable, and activate a disposable account in the isolated `church_test` database; widths 320/375/390/768/1366; fixed drawer toggle and close bounds; hero destinations; measured text contrast for light heroes, their buttons, and account statuses.
- Existing planner integration/browser checks: shared saves across devices, role visibility and write gates, CSRF, checklist/manual pop-outs, song controls, MMS screens, and archived services.
- Announcement ordering checks and repeated deployment checks, including removal of a legacy inline menu button, unchanged config/auth/toggle handler, and updated account views.
- PHP syntax and Git whitespace checks.

Run the browser checks only against the local test server; the fixture refuses other database names. Preview images use local test accounts. Production named-user sessions and the host deployment were not exercised here.

Deploy through the existing cPanel repository: Update from Remote, then Deploy HEAD Commit. The deployment retains the host shell and updates the workspace CSS cache version to 4.
