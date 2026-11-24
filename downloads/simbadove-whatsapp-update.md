# WhatsApp button update — 1.0.1

Adds a bottom-right floating WhatsApp link to +23274976543 on the shared visitor
layout, including public pages and customer portal. Staff administration uses its
separate layout. The link opens https://wa.me/23274976543 in a new tab.

## Install on your existing staging application

1. Back up resources/views/layout.php and public/assets/css/app.css to your computer.
2. Upload simbadove-whatsapp-update.zip to /home/simbwdyc, outside public_html.
3. Extract into /home/simbwdyc, allowing replacement of the two existing files.
   The archive contains the simbadove-global folder, so extraction merges into:
   /home/simbwdyc/simbadove-global/resources/views/layout.php
   /home/simbwdyc/simbadove-global/public/assets/css/app.css
   It also adds this update guide inside the project folder.
4. Keep both updated files at permissions 644. Do not import SQL or replace config.php.
5. Refresh staging with Ctrl+F5 (or Ctrl+Shift+R). Check a public page on desktop and
   mobile and click the bottom-right button. Confirm WhatsApp offers the correct number.
6. Delete the uploaded patch ZIP from the server after installation.

The stylesheet URL uses v=2 to refresh cached CSS. The icon is a local inline SVG;
no third-party widget script, analytics, database migration, or credentials are added.
Existing configuration, records and private documents are not part of this patch.
If your deployed layout or stylesheet was manually customized, merge the changes
rather than replacing those customizations. Rollback restores your two backed-up files.

Verification: PHP syntax and rendered URL, accessible label, new-tab protection,
and cache version checked locally. Visual browser verification remains to be done
on staging; this update has not been installed on your hosting account by the assistant.
