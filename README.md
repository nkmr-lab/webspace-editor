# webspace-editor

<img width="1233" height="834" alt="image" src="https://github.com/user-attachments/assets/25692299-f632-408f-a93b-2f5e98f092e4" />


**日本語** | [English](#english)

各ユーザーが **自分の `~/public_html` だけ** をブラウザから安全に編集できる、最小構成のセルフホスト型 Web エディタです。多人数(研究室・授業・共用サーバ)で「各自が自分の Web 領域を編集する」用途を想定しています。VS Code Remote を多人数で使うとメモリを食い潰す問題を避けるために作りました。

大きなファイルマネージャを流用せず、**小さく作って攻撃面を最小化**する方針です。素の PHP + PDO、エディタは [Monaco](https://microsoft.github.io/monaco-editor/) 1枚、フレームワークなし。

## 特長

- **Google ログイン + deny-by-default** — 未認証のリクエストには何も返しません。
- **パス閉じ込め** — すべてのパスを `realpath()` で解決し、必ず `/home/<user>/public_html` の内側かを検証。`..` やシンボリックリンクによる脱出を拒否。新規ファイルは「親ディレクトリが領域内」+「安全なファイル名」で合成します。
- **エディタ** — Monaco(シンタックスハイライト)。**未保存ガード**(●未保存マーク、別ファイルを開く時に保存/破棄の確認、タブを閉じる時の警告)。
- **ファイル操作** — 新規ファイル / 新規フォルダ / 名前変更 / **再帰削除(symlink安全)** / 権限変更(chmod) / ダウンロード を行ごとのメニューで。
- **メディアプレビュー** — 画像 / PDF / 音声 / 動画をインライン表示(`script-src 'none'` の厳格 CSP で配信するので SVG 内スクリプトは実行されません)。
- **検索** — ファイル名 / 中身(grep)。ユーザーの領域内に閉じ込め。
- **ドラッグ&ドロップ + 複数アップロード。**
- **その場で実行確認** — ファイルの実際の公開 URL を新規タブで開いて即動作確認。
- **AI 補助(任意・OpenAI)** — 2種類:
  - **生成アシスタント**: 「AI は頭脳、手はユーザー」。編集を提案・ファイルを開く/作る許可を求め、**ファイル操作は必ず閉じ込め + ユーザーの明示クリックを通ります**。
  - **AI ヒント(学習用)**: 答えは言わず、問題点を該当行に指摘(文法エラーは**赤波線**、その他は**💡**)。さらに **SQL や echo / 手組み JSON・HTML の"実際の出力"を、ふつうの入力ときわどい入力の2例で展開表示**して、エスケープ漏れ等に自分で気づかせます。
  - ユーザーごとの日次トークン上限。`ai_hint_only_users` に載せたユーザー(初学者など)は**生成AIをオフ・ヒントのみ**に制限可。`openai_api_key` を空にすれば AI 全体を無効化。
- **URL に状態を反映** — 開いているフォルダ/ファイルが URL(ハッシュ)に入り、**リロードで復元・ブックマーク/共有**が可能。
- **モバイル対応** — ファイル一覧がドロワーになり、ファイルを開くと自動で隠れます。

## セキュリティモデル

1. **認証ゲート** — 有効なセッションが無ければ `login` / `oauth_callback` 以外は到達不可。
2. **閉じ込め** — `safe_existing()` / `safe_new()` が全操作をユーザーの領域内に限定。さらに実行時に `open_basedir` をそのユーザーの領域だけに絞ります。
3. **CSRF** — 書き込み系アクションはトークン必須。
4. **多層防御** — **専用 PHP-FPM プール**を専用ユーザーで動かし、`open_basedir` / `disable_functions` / POSIX ACL(そのプールユーザーだけに書込権限)を付与(`deploy/` 参照)。

## 構成

```
index.php     # ルーティング + 認証 + パス閉じ込め + 各アクション
ui.php        # Monaco の UI(index.php から include。直アクセスは vhost で拒否)
config.example.php
deploy/
  apache-vhost.conf.example
  php-fpm-pool.conf.example
  acl_provision.sh            # FPMユーザーに各ユーザーのpublic_htmlへの書込ACLを付与
```

## セットアップ

1. `cp config.example.php /etc/fileapp/config.php` として、Google OAuth 資格情報と `allowed_emails`(メール→ユーザー名)を記入。**docroot の外**に置き、`640 root:<fpm-group>` に。
2. `index.php` / `ui.php` を docroot(例 `/var/www/fileapp`)へ。
3. `deploy/*.example` から Apache の vhost と専用 PHP-FPM プールを用意。
4. Google Console でリダイレクト URI `https://<あなたのホスト>/?action=oauth_callback` を登録し、個人 Google アカウントを使うなら同意画面を公開(外部)に。
5. FPM プールのユーザーに各ユーザーの `public_html` への書込権限を付与(`deploy/acl_provision.sh`、POSIX ACL 方式)。

ホスト固有の値(`file.nkmr.io`、セッション Cookie ドメイン、`base_url()` など)は現状 `index.php` にハードコードされています。自分のホストに置き換えてください。

## 単一サーバ / 管理者モード

各ユーザの `~/public_html` ではなく、**1つの絶対パスを直接編集**したい場合(自分のサーバのサイトを管理する等)は、config で:

```php
'fixed_base' => '/var/www/html',   // 全員がここを編集(ユーザ毎モデルより優先)
```

編集は `fixed_base` 配下に**閉じ込め**られます(範囲は設定した dir に限定)。ユーザ毎に別 dir を割り当てるなら `user_bases`(例: `['admin' => '/var/www/html']`)。
※ FPM プールの `open_basedir` にその dir を含め、プールユーザに書込権限を付与してください。「🌐 表示」の公開 URL は `public_url_tpl`(`{user}` 省略可、例 `https://mysite.example/`)で調整。
なお、安全のため **ベースをファイルシステムのルート `/` にはできません**(コード側で拒否)。

## 注意

- 本番の `config.php`(OAuth シークレット + 実在ユーザーのメール)は**絶対にコミットしない**でください。
- AI 機能は現在開いているファイルの内容を OpenAI に送信します。許容できない場合は無効化してください。

---

<a id="english"></a>

# webspace-editor (English)

A tiny self-hosted web editor that lets each authenticated user browse and edit **only their own `~/public_html`**, straight from the browser. Built for the "many users, each editing their own web space" case (a lab, a class, a shared server), to avoid the memory cost of VS Code Remote at scale. Deliberately kept **small with a minimal attack surface** rather than reusing a large file manager. Plain PHP + PDO, a single [Monaco](https://microsoft.github.io/monaco-editor/) editor, no framework.

## Features

- **Google sign-in, deny-by-default** — unauthenticated requests get nothing.
- **Path confinement** — every path is resolved with `realpath()` and checked to be inside `/home/<user>/public_html`; `..` and symlink escapes are rejected.
- **Editor** — Monaco with syntax highlighting; unsaved-changes guard (dirty marker, save/discard prompt on switch, `beforeunload` warning).
- **File ops** — new file / new folder / rename / **recursive delete** (symlink-safe) / chmod / download, in a per-row menu.
- **Media preview** — images / PDF / audio / video render inline (served with a strict `script-src 'none'` CSP so SVG can't run scripts).
- **Search** — by filename or file contents (grep), confined to the user's tree.
- **Drag & drop + multi-upload.**
- **Run it** — open the file's real public URL in a new tab to test immediately.
- **AI assist (optional, OpenAI)** — two flavors:
  - **Generative assistant**: "the AI is the brain, the user is the hands" — it proposes edits / asks to open or create files, and **every filesystem effect goes through the same confinement + an explicit user click**.
  - **AI hints (learning mode)**: never gives the answer — it flags issues on the relevant line (**red squiggle** for syntax errors, **💡** for hints) and **shows what your SQL / `echo` / hand-built JSON actually outputs**, for a normal input and a tricky input, so students spot missing escaping themselves.
  - Per-user daily token cap. Users listed in `ai_hint_only_users` (e.g. beginners) get **hints only, no generative AI**. Leave `openai_api_key` empty to disable all AI.
- **State in the URL** — the open folder/file is reflected in the URL hash, so **reload restores it** and links are shareable/bookmarkable.
- **Mobile mode** — the file list becomes a drawer; opening a file hides it.

## Security model

1. **Auth gate** — no valid session ⇒ only `login`/`oauth_callback` are reachable.
2. **Confinement** — `safe_existing()` / `safe_new()` keep every operation inside the user's base; at runtime `open_basedir` is further narrowed to just that user's dir.
3. **CSRF** — token required on all write actions.
4. **Defense in depth** — a **dedicated PHP-FPM pool** as a dedicated user with `open_basedir`, `disable_functions`, and POSIX ACLs granting *only that pool user* write access (see `deploy/`).

## Setup

1. `cp config.example.php /etc/fileapp/config.php` and fill in Google OAuth creds + your `allowed_emails` map. Keep it out of the docroot, `640 root:<fpm-group>`.
2. Put `index.php` / `ui.php` in the docroot (e.g. `/var/www/fileapp`).
3. Create the Apache vhost and a dedicated PHP-FPM pool from `deploy/*.example`.
4. In Google Console, add the redirect URI `https://<your-host>/?action=oauth_callback` and publish the consent screen (external) if using personal Google accounts.
5. Give the FPM pool user write access to each user's `public_html` (`deploy/acl_provision.sh`).

Host-specific values (`file.nkmr.io`, session cookie domain, `base_url()`) are hardcoded in `index.php` — replace them with your own host.

## Notes

- Never commit the real `config.php` (OAuth secret + real user emails).
- The AI feature sends the current file's contents to OpenAI; disable it if that's not acceptable.

## License

MIT
