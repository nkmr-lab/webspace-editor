#!/bin/bash
# Grant the app's PHP-FPM pool user write access to each user's ~/public_html
# via POSIX ACLs, so the web app (running as a dedicated user) can save files
# while the directories stay owned by the individual users.
#   - u:<APP_USER>:x   on /home/<user>            (traverse into public_html)
#   - u:<APP_USER>:rwX on /home/<user>/public_html (recursive) + default ACL
# Idempotent. Users without a public_html are skipped.
# Usage: sudo APP_USER=fileapp bash acl_provision.sh
set -u

APP_USER="${APP_USER:-fileapp}"

# List the usernames to provision (edit this to your own users).
USERS="
alice
bob
carol
"

ok=0; skip=0
for u in $USERS; do
  home="/home/$u"
  ph="$home/public_html"
  if [ ! -d "$ph" ]; then
    echo "SKIP  $u (no public_html)"; skip=$((skip+1)); continue
  fi
  setfacl -m    "u:$APP_USER:x"   "$home"
  setfacl -R -m "u:$APP_USER:rwX" "$ph"
  setfacl -R -d -m "u:$APP_USER:rwX" "$ph"
  echo "OK    $u"
  ok=$((ok+1))
done
echo "---- granted:$ok  skipped:$skip ----"
