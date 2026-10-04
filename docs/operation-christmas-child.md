# Operation Christmas Child feature

Live website repository only. No app changes.

Admin: `/php/admin/features/index.php` (pastor, admin, media). Settings and the contact inbox persist in MySQL. The module creates its two tables if absent using the existing database connection. The DB user therefore needs CREATE permission on first use.

Enable controls homepage placement below Ministries, public navigation, and public page availability. Disabled page returns HTTP 404. Public feature reads use no-store cache headers. The graphic is the supplied original, with an HTML Learn More button overlay beneath the deadline banner.

Configure video (YouTube or Vimeo HTTPS URL) and organizers (one `Name | Phone` per line) in admin. Empty fields show honest pending-content messages. Contact submissions are CSRF protected, length limited, escaped in admin, use prepared queries, include a honeypot and session submission cooldown. Messages appear in the private inbox; email delivery is not configured.

Deploy via the existing live site's PHP hosting/Git process. This repository has no Vercel configuration or deployment workflow; PHP requires PHP 8.1+ and MySQL. Do not deploy to the separate Next.js church app.
