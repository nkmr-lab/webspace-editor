<?php
/**
 * config.example.php — copy to /etc/fileapp/config.php and fill in.
 * Keep the real config OUTSIDE the document root (e.g. /etc/fileapp/), readable
 * only by the PHP-FPM pool user. NEVER commit the real config (secrets + PII).
 */
return [
    // ---- Google OAuth (create an OAuth client, type: Web application) ----
    'google_client_id'     => 'YOUR_CLIENT_ID.apps.googleusercontent.com',
    'google_client_secret' => 'YOUR_CLIENT_SECRET',
    // Must EXACTLY match the redirect URI registered in Google Console:
    'redirect_uri'         => 'https://file.example.com/?action=oauth_callback',

    // ---- Where each user's editable directory lives: <home_base>/<user>/<subdir> ----
    'home_base' => '/home',
    'subdir'    => 'public_html',
    // Single-server / admin mode: everyone edits ONE absolute path (e.g. a site docroot).
    // If set, this wins over the per-user home model above. Editing stays confined to this dir.
    // (The FPM pool's open_basedir must include it, and the pool user needs write access.)
    // 'fixed_base' => '/var/www/html',
    // Or assign an arbitrary dir per user (wins over fixed_base):
    // 'user_bases' => ['admin' => '/var/www/html', 'alice' => '/srv/alice'],

    // ---- "Open in browser" button: {user} is replaced with the username ----
    'public_url_tpl' => 'https://{user}.example.com/',

    // ---- Optional: AI assistant (OpenAI). Leave key empty to disable the feature. ----
    'openai_api_key'     => '',
    'ai_daily_token_cap' => 100000,   // per-user daily token cap
    // Users who get only the "AI hint" tool (educational), not the generative AI (e.g. beginners):
    'ai_hint_only_users' => [],
    // Map UI model choices -> API model ids (adjust to models you have access to):
    'openai_models'      => ['mini' => 'gpt-4o-mini', 'codex' => 'gpt-4o', 'strong' => 'gpt-4o'],

    // ---- Whitelist (deny-by-default): verified email => system username ----
    // The user may only browse/edit /home/<username>/public_html.
    'allowed_emails' => [
        'alice@example.com' => 'alice',
        'bob@example.com'   => 'bob',
    ],
];
