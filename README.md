# fileapp — a minimal, secure web file editor

A tiny self-hosted web editor that lets each authenticated user browse and edit
**only their own `~/public_html`**, straight from the browser. Built to avoid the
memory cost of VS Code Remote for many concurrent users, and deliberately kept
**small with a minimal attack surface** rather than reusing a large file manager.

Plain PHP + PDO, a single [Monaco](https://microsoft.github.io/monaco-editor/)
editor, no framework.

## Features

- **Google sign-in, deny-by-default** — unauthenticated requests get nothing.
- **Path confinement** — every path is resolved with `realpath()` and checked to be
  inside `/home/<user>/public_html`; `..` and symlink escapes are rejected.
  New files are validated as `parent-dir-in-base` + safe basename.
- **Editor** — Monaco with syntax highlighting; unsaved-changes guard (dirty marker,
  save/discard prompt on switch, `beforeunload` warning).
- **File ops** — new file / new folder / rename / **recursive delete** (symlink-safe) /
  chmod / download, in a per-row menu.
- **Media preview** — images / PDF / audio / video render inline (served with a
  strict `script-src 'none'` CSP so SVG can't run scripts).
- **Search** — by filename or file contents (grep), confined to the user's tree.
- **Drag & drop + multi-upload.**
- **Run it** — open the file's real public URL in a new tab to test immediately.
- **AI assist (optional, OpenAI)** — "the AI is the brain, the user is the hands":
  the model proposes edits / asks to open or create files, and **every filesystem
  effect goes through the same confinement + an explicit user click**. Per-user daily
  token cap. Leave `openai_api_key` empty to disable.
- **Mobile mode** — the file list becomes a drawer; opening a file hides it.

## Security model

1. **Auth gate** — no valid session ⇒ only `login`/`oauth_callback` are reachable.
2. **Confinement** — `safe_existing()` / `safe_new()` keep every operation inside the
   user's base. At runtime `open_basedir` is further narrowed to just that user's dir.
3. **CSRF** — token required on all write actions.
4. **Defense in depth** — run behind a **dedicated PHP-FPM pool** as a dedicated user
   with `open_basedir`, `disable_functions`, and POSIX ACLs granting *only that pool
   user* write access to users' `public_html` (see `deploy/`).

## Layout

```
index.php     # router + auth + path confinement + all actions
ui.php        # Monaco UI (included by index.php; direct access denied by vhost)
config.example.php
deploy/
  apache-vhost.conf.example
  php-fpm-pool.conf.example
  acl_provision.sh            # grant the FPM user write ACLs on each user's public_html
```

## Setup

1. `cp config.example.php /etc/fileapp/config.php` and fill in Google OAuth creds +
   your `allowed_emails` map. Keep it out of the docroot, `640 root:<fpm-group>`.
2. Put `index.php` / `ui.php` in the docroot (e.g. `/var/www/fileapp`).
3. Create the Apache vhost and a dedicated PHP-FPM pool from `deploy/*.example`.
4. Google Console: add the redirect URI `https://<your-host>/?action=oauth_callback`
   and publish the consent screen (external) if using personal Google accounts.
5. Give the FPM pool user write access to each user's `public_html`
   (`deploy/acl_provision.sh`, POSIX ACL approach).

Host-specific values (e.g. `file.nkmr.io`, session cookie domain, `base_url()`) are
currently hardcoded in `index.php` — replace them with your own host.

## Notes

- Never commit the real `config.php` (OAuth secret + real user emails).
- The AI feature sends the current file's contents to OpenAI; disable it if that's
  not acceptable for your users.
