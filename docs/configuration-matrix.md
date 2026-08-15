# Configuration matrix

| Area | Where | Owner action | Secret? |
|---|---|---|---|
| Sender email | Formistic / Tour Manager settings | Set verified sender and reply-to | No, but operationally sensitive |
| SMTP/API | Approved mail provider | Configure provider and DNS authentication | Yes; never commit |
| WhatsApp | Brother Tours settings | Confirm display number and canonical `wa.me` URL | No |
| Analytics | Customizer / consent settings | Add public measurement IDs and verify consent | Public identifier |
| Reviews | Customizer | Add profile links; publish only evidenced claims | URLs public |
| Tourflows/connections | Tour Manager connections | Add endpoint and signing secret | Secret endpoint/signature |
| Payments | Tour Manager settings | Configure only after provider and webhook review | Yes |
| Operations API | `wp-config.php` / admin | Set trusted origins, HTTPS, roles, and session policy | Origin public; credentials secret |
| Legal | WordPress pages | Approve Privacy, Terms, and Cancellation copy | No |
| Staging protection | Host and SEO settings | Add access control and noindex/robots blocking | No |

Passwords, API keys, webhook secrets, private tokens, and database exports do
not belong in this repository or in client documentation.
