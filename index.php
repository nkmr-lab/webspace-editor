<?php
/**
 * file.nkmr.io - 最小・セキュアなWebファイルエディタ
 * 方針: Google認証必須(未認証は全拒否) → 認証済みユーザは自分の public_html だけを
 *       realpath閉じ込めで 一覧/編集/アップロード/DL できる。
 *
 * セキュリティ中核:
 *  - 認証ゲート: 有効セッション(Google認証済&ホワイトリスト)以外は login/callback 以外全拒否
 *  - パス閉じ込め: realpath()で解決し、必ず base(=/home/<user>/public_html)配下か検証。
 *                 新規ファイルは「親dirのrealpath ∈ base」+ basenameで合成。シンボリックリンク脱出も realpath で防ぐ。
 *  - CSRF: 書き込み系(save/upload/mkdir/delete)はトークン必須
 *  - セッション: HttpOnly+Secure+SameSite、ログイン時 regenerate
 */

declare(strict_types=1);

$CONFIG = require '/etc/fileapp/config.php';

// ---- セッション(堅め) ----
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => 'file.nkmr.io',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name('FILEAPP');
session_start();

$action = $_GET['action'] ?? 'app';

// ================= 認証 (Google OIDC 手動フロー) =================
function base_url(): string { return 'https://file.nkmr.io'; }

function require_auth(array $CONFIG): array {
    if (empty($_SESSION['user']) || empty($_SESSION['email'])) {
        header('Location: ' . base_url() . '/?action=login');
        exit;
    }
    return ['user' => $_SESSION['user'], 'email' => $_SESSION['email']];
}

function do_login(array $CONFIG): void {
    $state = bin2hex(random_bytes(16));
    // モバイルは ?action=login を投機的に複数回叩くことがある。毎回上書きすると
    // Google から戻る state と食い違って bad state になるので、直近5個を保持しどれか一致でOKにする。
    $states = $_SESSION['oauth_states'] ?? [];
    $states[] = $state;
    $_SESSION['oauth_states'] = array_slice($states, -5);
    $params = http_build_query([
        'client_id' => $CONFIG['google_client_id'],
        'redirect_uri' => $CONFIG['redirect_uri'],
        'response_type' => 'code',
        'scope' => 'openid email',
        'state' => $state,
        'prompt' => 'select_account',
    ]);
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
}

function do_oauth_callback(array $CONFIG): void {
    // state検証(CSRF)。直近保持したstateのどれかに一致すればOK(モバイルの二重login対策)。
    $state = $_GET['state'] ?? '';
    $hadStates = !empty($_SESSION['oauth_states']);
    $valid = false;
    foreach ($_SESSION['oauth_states'] ?? [] as $s) {
        if (hash_equals($s, $state)) { $valid = true; break; }
    }
    if (!$valid) {
        if ($hadStates) {
            // sessionは生きてるがstate不一致(二重login競合など)→ loginからやり直し
            header('Location: ' . base_url() . '/?action=login'); exit;
        }
        // stateが1つも無い=Cookie未保持。ループさせず明確に案内。
        http_response_code(400);
        exit('セッションを確認できませんでした。ブラウザのCookieを有効にして、もう一度ログインしてください。');
    }
    unset($_SESSION['oauth_states']);
    $code = $_GET['code'] ?? '';
    if ($code === '') { http_response_code(400); exit('no code'); }

    // codeをtokenに交換
    $tok = http_post('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => $CONFIG['google_client_id'],
        'client_secret' => $CONFIG['google_client_secret'],
        'redirect_uri' => $CONFIG['redirect_uri'],
        'grant_type' => 'authorization_code',
    ]);
    $tok = json_decode($tok, true);
    if (empty($tok['access_token'])) { http_response_code(401); exit('token error'); }

    // userinfoで検証済みemail取得
    $ui = http_get('https://openidconnect.googleapis.com/v1/userinfo', $tok['access_token']);
    $ui = json_decode($ui, true);
    $email = strtolower(trim($ui['email'] ?? ''));
    $verified = $ui['email_verified'] ?? false;
    if ($email === '' || !$verified) { http_response_code(403); exit('email not verified'); }

    // ホワイトリスト照合 → username確定
    $map = $CONFIG['allowed_emails'];
    if (!isset($map[$email])) {
        http_response_code(403);
        exit('このアカウント(' . htmlspecialchars($email) . ')は許可されていません。');
    }
    $user = $map[$email];

    session_regenerate_id(true);
    $_SESSION['email'] = $email;
    $_SESSION['user'] = $user;
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
    header('Location: ' . base_url() . '/');
    exit;
}

function http_post(string $url, array $data): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $r = curl_exec($ch); curl_close($ch);
    return $r === false ? '' : $r;
}
function http_get(string $url, string $bearer): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $bearer],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $r = curl_exec($ch); curl_close($ch);
    return $r === false ? '' : $r;
}

// ================= パス閉じ込め (最重要) =================
function user_base(array $CONFIG, string $user): string {
    // usernameは配列の値(サーバ側マップ由来)なので信用できるが、念のため厳格チェック
    if (!preg_match('/^[a-z0-9_][a-z0-9_-]{0,31}$/i', $user)) {
        http_response_code(400); exit('bad user');
    }
    $base = $CONFIG['home_base'] . '/' . $user . '/' . $CONFIG['subdir'];
    $rp = realpath($base);
    if ($rp === false) { http_response_code(500); exit('base not found'); }
    return $rp;
}

/**
 * 既存ファイル/ディレクトリの安全な解決。base配下でなければ即拒否。
 */
function safe_existing(string $base, string $rel): string {
    $target = realpath($base . '/' . ltrim($rel, '/'));
    if ($target === false) { http_response_code(404); exit('not found'); }
    if ($target !== $base && strpos($target, $base . DIRECTORY_SEPARATOR) !== 0) {
        http_response_code(403); exit('out of scope');
    }
    return $target;
}

/**
 * 新規作成/保存用。親dirは既存でbase配下、basenameは安全な名前のみ。
 * 重要: 親dirは realpath で base 配下か検証(親経由のsymlink脱出を防ぐ)。
 *       さらに最終ターゲット自体が既存symlinkなら拒否する。
 *       ← これが無いと public_html 内に置いた `evil -> /etc/...` を辿って
 *          file_put_contents が base 外へ書き込めてしまう(symlink書込脱出)。
 */
function safe_new(string $base, string $rel): string {
    $rel = ltrim($rel, '/');
    if ($rel === '' || strpos($rel, "\0") !== false) { http_response_code(400); exit('bad path'); }
    $dir = dirname($rel);
    $name = basename($rel);
    if (!preg_match('/^[^\/\\\\\x00]{1,255}$/', $name) || $name === '.' || $name === '..') {
        http_response_code(400); exit('bad filename');
    }
    $parentAbs = ($dir === '.' || $dir === '') ? $base : realpath($base . '/' . $dir);
    if ($parentAbs === false) { http_response_code(404); exit('dir not found'); }
    if ($parentAbs !== $base && strpos($parentAbs, $base . DIRECTORY_SEPARATOR) !== 0) {
        http_response_code(403); exit('out of scope');
    }
    $target = $parentAbs . '/' . $name;
    // 既存ターゲットが symlink なら辿らせない(symlink書込脱出の封じ)
    if (is_link($target)) { http_response_code(403); exit('refuse to follow symlink'); }
    return $target;
}

/**
 * 再帰削除。symlink は辿らず(リンク自体をunlink)、実ディレクトリだけ降りて消す。
 * 呼び出し側で $path が base 配下であることを保証すること。open_basedir も多層で効く。
 */
function rrmdir(string $path): bool {
    if (is_link($path)) { return unlink($path); }        // symlinkは中身を辿らない
    if (is_dir($path)) {
        foreach (scandir($path) as $e) {
            if ($e === '.' || $e === '..') continue;
            if (!rrmdir($path . '/' . $e)) return false;
        }
        return rmdir($path);
    }
    return unlink($path);
}

function check_csrf(): void {
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', $t)) { http_response_code(403); exit('csrf'); }
}

function json_out($data): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function raw_mime(string $f): string {
    static $map = [
        'png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif',
        'webp'=>'image/webp','bmp'=>'image/bmp','ico'=>'image/x-icon','svg'=>'image/svg+xml','avif'=>'image/avif',
        'pdf'=>'application/pdf',
        'mp3'=>'audio/mpeg','wav'=>'audio/wav','ogg'=>'audio/ogg','m4a'=>'audio/mp4','aac'=>'audio/aac',
        'mp4'=>'video/mp4','webm'=>'video/webm','mov'=>'video/quicktime','m4v'=>'video/x-m4v',
    ];
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    return $map[$ext] ?? 'application/octet-stream';
}

// ================= AI アシスタント (OpenAI プロキシ) =================
// 設計: 「AIは頭脳、ファイル操作の手はユーザー」。モデルの tool_call はサーバでは実行せず、
// フロントに『ユーザーへの依頼』として返す。実際の read/save は既存の confined アクション経由で
// ユーザーがクリックして初めて起きる(閉じ込め + ユーザー承認の二重ゲート)。

function ai_models(array $CONFIG): array {
    // config で上書き可。値=許可するAPIモデルID。
    return $CONFIG['openai_models'] ?? [
        'mini'  => 'gpt-5.4-mini',   // 既定(安い)
        'codex' => 'gpt-5.3-codex',  // コード特化
        'strong'=> 'gpt-5.5',        // 強い
    ];
}

// AI利用量DB(SQLite)。per-user 日次トークン上限。ユーザー領域外に置き改竄不可。
function ai_db(): PDO {
    if (!is_dir(AI_USAGE_DIR)) { @mkdir(AI_USAGE_DIR, 0700, true); }
    $pdo = new PDO('sqlite:' . AI_USAGE_DIR . '/usage.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE IF NOT EXISTS usage (user TEXT, day TEXT, tokens INTEGER, PRIMARY KEY(user,day))');
    return $pdo;
}
function ai_used_today(PDO $db, string $user): int {
    $st = $db->prepare('SELECT tokens FROM usage WHERE user=? AND day=?');
    $st->execute([$user, gmdate('Y-m-d')]);
    return (int)($st->fetchColumn() ?: 0);
}
function ai_add_usage(PDO $db, string $user, int $tokens): void {
    $st = $db->prepare('INSERT INTO usage(user,day,tokens) VALUES(?,?,?)
        ON CONFLICT(user,day) DO UPDATE SET tokens = tokens + excluded.tokens');
    $st->execute([$user, gmdate('Y-m-d'), $tokens]);
}

function ai_system_prompt(string $user): string {
    return "あなたは file.nkmr.io 内蔵のコーディング補助AI。ユーザー({$user})が今開いている1つのファイルに対する指示を助ける。\n"
        . "重要な制約(必ず守る):\n"
        . "- あなたはファイルシステムを直接操作できない。読み書きは必ず tool を通じて『ユーザーに依頼』する形を取る。\n"
        . "- 今開いているファイルを編集する提案は propose_edit を使い、ファイル全体の新しい内容を渡す(差分ではなく完成形)。\n"
        . "- 別ファイルの中身が必要なら request_open_file で理由と共に依頼する(ユーザーが開くと内容が渡される)。\n"
        . "- 新規ファイルが必要なら propose_new_file で提案する(ユーザーが承認すると作成される)。\n"
        . "- 一度に複数ファイルを勝手に書き換えない。1ステップずつ、ユーザーの承認を得て進める。\n"
        . "- 回答は簡潔に。コードは日本語コメントを適度に。";
}
function ai_tools(): array {
    return [
        ['type'=>'function','function'=>[
            'name'=>'propose_edit',
            'description'=>'今ユーザーが開いているファイルの新しい内容全体を提案する。ユーザーには差分で表示され、承認されると保存される。',
            'parameters'=>['type'=>'object','properties'=>[
                'summary'=>['type'=>'string','description'=>'何を変えたかの短い説明(日本語)'],
                'new_content'=>['type'=>'string','description'=>'ファイルの新しい内容(完成形の全文)'],
            ],'required'=>['summary','new_content']],
        ]],
        ['type'=>'function','function'=>[
            'name'=>'request_open_file',
            'description'=>'別のファイルの内容を読みたいとき、ユーザーにそのファイルを開いて渡すよう依頼する。',
            'parameters'=>['type'=>'object','properties'=>[
                'path'=>['type'=>'string','description'=>'public_html からの相対パス'],
                'reason'=>['type'=>'string','description'=>'なぜ必要かの短い理由(日本語)'],
            ],'required'=>['path','reason']],
        ]],
        ['type'=>'function','function'=>[
            'name'=>'propose_new_file',
            'description'=>'新しいファイルの作成を提案する。ユーザーが承認すると作成され、その後編集できるようになる。',
            'parameters'=>['type'=>'object','properties'=>[
                'path'=>['type'=>'string','description'=>'public_html からの相対パス'],
                'summary'=>['type'=>'string','description'=>'このファイルの目的(日本語)'],
                'content'=>['type'=>'string','description'=>'作成時の初期内容'],
            ],'required'=>['path','summary','content']],
        ]],
    ];
}

// OpenAI Responses API に POST(GPT-5系は chat/completions 非対応=codex等。responsesに統一)。
function ai_responses_post(array $CONFIG, array $payload): array {
    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $CONFIG['openai_api_key'], 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
    ]);
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($raw === false) { return ['ok'=>false, 'error'=>'network: ' . $err]; }
    $j = json_decode($raw, true);
    if ($code !== 200) { return ['ok'=>false, 'error'=>'openai ' . $code . ': ' . ($j['error']['message'] ?? substr((string)$raw,0,300))]; }
    return ['ok'=>true, 'data'=>$j];
}
// chat/completions 形式のツール定義 → Responses 形式(flat)
function chat_tools_to_responses(array $tools): array {
    $out = [];
    foreach ($tools as $t) {
        $f = $t['function'] ?? [];
        $out[] = ['type'=>'function', 'name'=>$f['name'] ?? '', 'description'=>$f['description'] ?? '',
                  'parameters'=>$f['parameters'] ?? ['type'=>'object','properties'=>new stdClass()]];
    }
    return $out;
}
// フロント由来の chat 形式メッセージ列 → Responses の input 形式
function chat_to_responses_input(array $messages): array {
    $input = [];
    foreach ($messages as $m) {
        $role = $m['role'] ?? '';
        if ($role === 'tool') {
            $input[] = ['type'=>'function_call_output', 'call_id'=>(string)($m['tool_call_id'] ?? ''), 'output'=>(string)($m['content'] ?? '')];
        } elseif ($role === 'assistant' && !empty($m['tool_calls'])) {
            if (!empty($m['content'])) { $input[] = ['role'=>'assistant', 'content'=>(string)$m['content']]; }
            foreach ($m['tool_calls'] as $tc) {
                $input[] = ['type'=>'function_call', 'call_id'=>(string)($tc['id'] ?? ''),
                            'name'=>(string)($tc['function']['name'] ?? ''), 'arguments'=>(string)($tc['function']['arguments'] ?? '{}')];
            }
        } else {
            $input[] = ['role'=>($role ?: 'user'), 'content'=>(string)($m['content'] ?? '')];
        }
    }
    return $input;
}
// Responses の output からテキスト(JSON応答用)を取り出す
function responses_output_text(array $data): string {
    $text = '';
    foreach ($data['output'] ?? [] as $item) {
        if (($item['type'] ?? '') === 'message') {
            foreach ($item['content'] ?? [] as $c) {
                if (($c['type'] ?? '') === 'output_text') { $text .= $c['text'] ?? ''; }
            }
        }
    }
    return $text;
}
// Responses の output → フロントが期待する chat 形式の assistant メッセージ(content + tool_calls)
function responses_to_chat_message(array $data): array {
    $content = ''; $tool_calls = [];
    foreach ($data['output'] ?? [] as $item) {
        $t = $item['type'] ?? '';
        if ($t === 'message') {
            foreach ($item['content'] ?? [] as $c) {
                if (($c['type'] ?? '') === 'output_text') { $content .= $c['text'] ?? ''; }
            }
        } elseif ($t === 'function_call') {
            $tool_calls[] = ['id'=>(string)($item['call_id'] ?? ''), 'type'=>'function',
                             'function'=>['name'=>(string)($item['name'] ?? ''), 'arguments'=>(string)($item['arguments'] ?? '{}')]];
        }
    }
    $msg = ['role'=>'assistant', 'content'=>$content];
    if ($tool_calls) { $msg['tool_calls'] = $tool_calls; }
    return $msg;
}
// 生成AI(tool使用)。Responses API を叩き、フロント互換の {message, tokens} を返す。
function ai_openai_call(array $CONFIG, string $model, array $messages): array {
    $res = ai_responses_post($CONFIG, [
        'model' => $model,
        'input' => chat_to_responses_input($messages),
        'tools' => chat_tools_to_responses(ai_tools()),
        'tool_choice' => 'auto',
    ]);
    if (!$res['ok']) { return $res; }
    $d = $res['data'];
    return ['ok'=>true, 'message'=>responses_to_chat_message($d), 'tokens'=>(int)($d['usage']['total_tokens'] ?? 0)];
}

// tool無し・JSON応答固定のプレーン呼び出し(AIヒントチェック用)
function ai_openai_plain(array $CONFIG, string $model, array $messages): array {
    $res = ai_responses_post($CONFIG, [
        'model' => $model,
        'input' => $messages,
        'text'  => ['format' => ['type' => 'json_object']],
    ]);
    if (!$res['ok']) { return $res; }
    return ['ok'=>true, 'text'=>responses_output_text($res['data']), 'tokens'=>(int)($res['data']['usage']['total_tokens'] ?? 0)];
}

// ================= ルーティング =================
if ($action === 'login') { do_login($CONFIG); }
if ($action === 'oauth_callback') { do_oauth_callback($CONFIG); }
if ($action === 'logout') { $_SESSION = []; session_destroy(); header('Location: ' . base_url() . '/?action=login'); exit; }

// --- ここより下は全て認証必須 ---
$auth = require_auth($CONFIG);
$base = user_base($CONFIG, $auth['user']);

// AI生成機能(🤖 提案/編集)の可否。hint_only に載っている人は「🔎 AIヒント」のみ許可(初学者向け)。
$ai_gen_allowed = !in_array($auth['user'], $CONFIG['ai_hint_only_users'] ?? [], true);

// 多層防御: 認証済み以降のデータ操作は「本人の base + アップロード用tmp」だけに
// open_basedir を実時間で絞る。パス閉じ込め(safe_*)にバグがあっても他人のhomeへ届かない。
// セッションはこれ以降変更しない(csrfは読むだけ)ので、書込を確定させてから絞る。
session_write_close();
// 許可: 本人のbase / 自分のコードdir(ui.php include用) / アップロードtmp / AI利用量DB
define('AI_USAGE_DIR', '/var/lib/php/fpm-fileapp/ai-usage');
@ini_set('open_basedir', $base . PATH_SEPARATOR . __DIR__ . PATH_SEPARATOR . sys_get_temp_dir() . PATH_SEPARATOR . '/tmp' . PATH_SEPARATOR . AI_USAGE_DIR);

switch ($action) {
    case 'list':
        $rel = $_GET['path'] ?? '';
        $dir = $rel === '' ? $base : safe_existing($base, $rel);
        if (!is_dir($dir)) { http_response_code(400); exit('not a dir'); }
        $items = [];
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $full = $dir . '/' . $f;
            $items[] = [
                'name' => $f,
                'is_dir' => is_dir($full),
                'size' => is_file($full) ? filesize($full) : 0,
                'mtime' => filemtime($full),
                'perms' => substr(sprintf('%o', fileperms($full)), -3),
            ];
        }
        json_out(['base_user' => $auth['user'], 'path' => $rel, 'items' => $items, 'csrf' => $_SESSION['csrf']]);

    case 'read':
        $f = safe_existing($base, $_GET['path'] ?? '');
        if (!is_file($f)) { http_response_code(400); exit('not a file'); }
        if (filesize($f) > 5 * 1024 * 1024) { http_response_code(413); exit('too large to edit'); }
        header('Content-Type: text/plain; charset=utf-8');
        readfile($f);
        exit;

    case 'save':
        check_csrf();
        $target = safe_new($base, $_POST['path'] ?? '');
        $content = $_POST['content'] ?? '';
        if (file_put_contents($target, $content) === false) { http_response_code(500); exit('write failed'); }
        json_out(['ok' => true]);

    case 'upload':
        check_csrf();
        if (empty($_FILES['file'])) { http_response_code(400); exit('no file'); }
        $reldir = $_POST['path'] ?? '';
        $name = basename($_FILES['file']['name']);
        $target = safe_new($base, ($reldir === '' ? '' : $reldir . '/') . $name);
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) { http_response_code(500); exit('upload failed'); }
        json_out(['ok' => true, 'name' => $name]);

    case 'download':
        $f = safe_existing($base, $_GET['path'] ?? '');
        if (!is_file($f)) { http_response_code(404); exit('not found'); }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode(basename($f)) . '"');
        readfile($f);
        exit;

    case 'raw':
        // プレビュー用にインラインでファイル配信。SVG等のスクリプト実行を封じるため sandbox CSP。
        $f = safe_existing($base, $_GET['path'] ?? '');
        if (!is_file($f)) { http_response_code(404); exit('not found'); }
        // SVG/HTML内スクリプト実行を封じる(script-src 'none')。PDF/画像/動画の表示は許可。
        header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'; script-src 'none'; object-src 'none'");
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . raw_mime($f));
        header('Content-Length: ' . filesize($f));
        readfile($f);
        exit;

    case 'search':
        $q = trim((string)($_GET['q'] ?? ''));
        $mode = ($_GET['mode'] ?? 'name') === 'content' ? 'content' : 'name';
        if (mb_strlen($q) < 2) { json_out(['results' => [], 'note' => '2文字以上で検索']); }
        $skip = ['node_modules', '.git', 'vendor', '.cache'];
        $results = []; $scanned = 0; $capped = false;
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
                    function ($cur) use ($skip) {
                        if ($cur->isDir()) {
                            if ($cur->isLink()) return false;              // symlink dirへは潜らない
                            if (in_array($cur->getFilename(), $skip)) return false;
                        }
                        return true;
                    }
                ), RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($it as $file) {
                if (++$scanned > 8000) { $capped = true; break; }
                if (!$file->isFile()) continue;
                $rel = str_replace('\\', '/', ltrim(substr($file->getPathname(), strlen($base)), '/'));
                if ($mode === 'name') {
                    if (mb_stripos($file->getFilename(), $q) !== false) {
                        $results[] = ['path' => $rel];
                    }
                } else {
                    if ($file->getSize() > 1024 * 1024) continue;          // 1MB超はスキップ
                    $content = @file_get_contents($file->getPathname());
                    if ($content === false || strpos($content, "\0") !== false) continue; // バイナリ除外
                    if (mb_stripos($content, $q) === false) continue;
                    $lineno = 0; $snippet = '';
                    foreach (explode("\n", $content) as $i => $ln) {
                        if (mb_stripos($ln, $q) !== false) { $lineno = $i + 1; $snippet = mb_substr(trim($ln), 0, 140); break; }
                    }
                    $results[] = ['path' => $rel, 'line' => $lineno, 'snippet' => $snippet];
                }
                if (count($results) >= 200) { $capped = true; break; }
            }
        } catch (Throwable $e) { /* 走査中の権限/IOエラーは無視して部分結果を返す */ }
        json_out(['results' => $results, 'capped' => $capped, 'mode' => $mode]);

    case 'mkdir':
        check_csrf();
        $target = safe_new($base, $_POST['path'] ?? '');
        if (file_exists($target)) { http_response_code(409); exit('already exists'); }
        if (!mkdir($target, 0755)) { http_response_code(500); exit('mkdir failed'); }
        json_out(['ok' => true]);

    case 'delete':
        check_csrf();
        // safe_existing は realpath 解決するので、base外へ脱出する symlink は自動的に404/403。
        $target = safe_existing($base, $_POST['path'] ?? '');
        if ($target === $base) { http_response_code(403); exit('cannot delete base'); }
        if (is_dir($target) && !is_link($target)) {
            // フォルダは中身ごと再帰削除(symlinkは辿らない)
            if (!rrmdir($target)) { http_response_code(500); exit('delete failed'); }
        } else {
            if (!unlink($target)) { http_response_code(500); exit('delete failed'); }
        }
        json_out(['ok' => true]);

    case 'rename':
        check_csrf();
        $from = safe_existing($base, $_POST['from'] ?? '');
        if ($from === $base) { http_response_code(403); exit('cannot rename base'); }
        $to = safe_new($base, $_POST['to'] ?? '');
        if (file_exists($to)) { http_response_code(409); exit('destination exists'); }
        if (!rename($from, $to)) { http_response_code(500); exit('rename failed'); }
        json_out(['ok' => true]);

    case 'chmod':
        check_csrf();
        $target = safe_existing($base, $_POST['path'] ?? '');
        $m = $_POST['mode'] ?? '';
        // 3桁の8進数のみ許可。setuid/setgid/sticky等の特殊ビットは付けさせない(0777でマスク)。
        if (!preg_match('/^[0-7]{3}$/', $m)) { http_response_code(400); exit('bad mode (例: 755)'); }
        $mode = octdec($m) & 0777;
        if (!chmod($target, $mode)) { http_response_code(500); exit('chmod failed'); }
        json_out(['ok' => true, 'perms' => $m]);

    case 'ai':
        check_csrf();
        if (!$ai_gen_allowed) { json_out(['error' => 'このアカウントでは生成AIは使えません。「🔎 AIヒント」を使ってください。']); }
        if (empty($CONFIG['openai_api_key'])) { json_out(['error' => 'AI未設定です(サーバに openai_api_key が未登録)']); }
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) { http_response_code(400); exit('bad json'); }

        // モデル選択(許可リスト内のみ)
        $models = ai_models($CONFIG);
        $mkey = $body['model_key'] ?? 'mini';
        $model = $models[$mkey] ?? $models['mini'];

        // per-user 日次トークン上限
        $cap = (int)($CONFIG['ai_daily_token_cap'] ?? 100000);
        try { $db = ai_db(); } catch (Throwable $e) { http_response_code(500); exit('usage db error'); }
        $used = ai_used_today($db, $auth['user']);
        if ($used >= $cap) {
            json_out(['error' => "本日のAI利用上限に達しました($used/$cap トークン)。明日また使えます。"]);
        }

        // メッセージ組み立て: system(サーバ固定) + 現在ファイル文脈 + 会話履歴(client由来のsystemは除去)
        $messages = [['role'=>'system','content'=>ai_system_prompt($auth['user'])]];
        if (!empty($body['current_file']['path'])) {
            $cf = $body['current_file'];
            $messages[] = ['role'=>'system','content'=>"現在ユーザーが開いているファイル: {$cf['path']}\n----\n" . (string)($cf['content'] ?? '')];
        }
        foreach (($body['messages'] ?? []) as $m) {
            if (!is_array($m) || ($m['role'] ?? '') === 'system') continue;
            $messages[] = $m;
        }

        $res = ai_openai_call($CONFIG, $model, $messages);
        if (!$res['ok']) { json_out(['error' => $res['error']]); }
        $tokens = (int)$res['tokens'];
        if ($tokens > 0) { ai_add_usage($db, $auth['user'], $tokens); }
        json_out([
            'message' => $res['message'],   // assistant message(content と tool_calls)
            'model'   => $model,
            'usage'   => ['today' => $used + $tokens, 'cap' => $cap, 'this_call' => $tokens],
        ]);

    case 'aicheck':
        // 学習用: AIが問題点を「ヒント」で指摘(答えは言わない)。行ごとの注釈をJSONで返す。
        check_csrf();
        if (empty($CONFIG['openai_api_key'])) { json_out(['error' => 'AI未設定です']); }
        $body = json_decode(file_get_contents('php://input'), true);
        $content = (string)($body['content'] ?? '');
        $filename = (string)($body['filename'] ?? 'file');
        if (trim($content) === '') { json_out(['issues' => []]); }

        $cap = (int)($CONFIG['ai_daily_token_cap'] ?? 100000);
        try { $db = ai_db(); } catch (Throwable $e) { json_out(['error' => 'usage db error']); }
        if (ai_used_today($db, $auth['user']) >= $cap) { json_out(['error' => '本日のAI利用上限に達しました。']); }

        $models = ai_models($CONFIG);
        $model = $models['mini'] ?? 'gpt-4o-mini';

        $numbered = '';
        foreach (explode("\n", $content) as $i => $l) { $numbered .= ($i + 1) . "\t" . $l . "\n"; }

        $sys = "あなたは学習支援の先生です。学生のPHPコードを見て、次を『指摘/可視化』します。答え(修正後の完成コード)は書かず、学生が自分で気づけるようにします。"
             . "(1) 文法(パース)エラー・よくあるバグ・危険な書き方 → どの行に・どんな問題かを短いヒントや問いかけで示す。"
             . "(2) 変数を埋め込んで組み立てるSQL や、echo/print・手組みのJSON/HTML などの出力 → 『実際に送られる/出力される文字列』を、(a)ふつうの入力例 と (b)クォートや < > & 日本語 等きわどい入力例 の2つについて ``` のコードブロックで展開して見せる(壊れる/注入・混入する様子が結果から分かるように)。"
             . "hint は Markdown可。プレースホルダ(bind)や json_encode / htmlspecialchars 等で適切に処理済みの箇所は展開不要。指摘も展開対象も無ければ issues は空配列。"
             . "severity は、実行を止める致命的なもの(文法エラー等)だけ \"error\"、それ以外(バグの気づき・展開・注意)は \"warn\" か \"info\"。"
             . "厳密なJSONだけを返す: {\"issues\":[{\"line\":整数, \"hint\":\"Markdown可の短い説明/展開(答えの完成コードは書かない)\", \"severity\":\"error|warn|info\"}]}";
        $usr = "ファイル名: {$filename}\n各行は「行番号<TAB>コード」の形式です。\n----\n{$numbered}";

        $res = ai_openai_plain($CONFIG, $model, [
            ['role' => 'system', 'content' => $sys],
            ['role' => 'user',   'content' => $usr],
        ]);
        if (!$res['ok']) { json_out(['error' => $res['error']]); }
        $tokens = (int)$res['tokens'];
        if ($tokens > 0) { ai_add_usage($db, $auth['user'], $tokens); }

        $parsed = json_decode(($res['text'] ?? '') ?: '{}', true);
        $issues = is_array($parsed['issues'] ?? null) ? $parsed['issues'] : [];
        $clean = [];
        foreach ($issues as $it) {
            $ln = (int)($it['line'] ?? 0);
            if ($ln < 1) continue;
            $sev = in_array(($it['severity'] ?? ''), ['error', 'warn', 'info'], true) ? $it['severity'] : 'info';
            $clean[] = ['line' => $ln, 'hint' => (string)($it['hint'] ?? ''), 'severity' => $sev];
        }
        json_out(['issues' => $clean, 'usage' => ['today' => ai_used_today($db, $auth['user']), 'cap' => $cap]]);

    case 'sqlcheck':
        // 学習用「出力プレビュー」: SQLの展開結果や echo/JSON など実際の出力を、例入力で見せて気づかせる。
        check_csrf();
        if (empty($CONFIG['openai_api_key'])) { json_out(['error' => 'AI未設定です']); }
        $body = json_decode(file_get_contents('php://input'), true);
        $content = (string)($body['content'] ?? '');
        $filename = (string)($body['filename'] ?? 'file');
        if (trim($content) === '') { json_out(['issues' => []]); }

        $cap = (int)($CONFIG['ai_daily_token_cap'] ?? 100000);
        try { $db = ai_db(); } catch (Throwable $e) { json_out(['error' => 'usage db error']); }
        if (ai_used_today($db, $auth['user']) >= $cap) { json_out(['error' => '本日のAI利用上限に達しました。']); }

        $models = ai_models($CONFIG);
        $model = $models['mini'] ?? 'gpt-4o-mini';
        $numbered = '';
        foreach (explode("\n", $content) as $i => $l) { $numbered .= ($i + 1) . "\t" . $l . "\n"; }

        $sys = "あなたはPHPコードが『実際に何を出力/生成するか』を、具体例で可視化する補助ツールです。次の2種類を対象にします。"
             . "(A) 変数展開・文字列連結で組み立てるSQL → データベースへ送られるSQL文字列の展開結果。"
             . "(B) echo / print / printf や、手組みのJSON・HTMLなど、クライアントへ送られる出力 → 実際に出力される文字列。"
             . "各対象について hint(Markdown)に、まず (1)ふつうの入力例での結果、続けて (2)クォート・< > & ・日本語 などきわどい入力例での結果 を、それぞれ ``` のコードブロックで示してください。"
             . "説教はせず、結果を並べて見せて学生自身に気づかせます(JSONが壊れて無効になる/HTMLが崩れる・エスケープされない等が、結果を見れば分かるように)。文章の補足は多くて1行。完成コードは書かない。"
             . "プレースホルダ(bind)や json_encode / htmlspecialchars 等で適切に処理され問題が起きない箇所は含めない。対象が無ければ空配列。"
             . "severity は、きわどい入力で壊れる/注入・混入するものは \"error\"、それ以外は \"info\"。"
             . "厳密なJSONだけを返す: {\"issues\":[{\"line\":整数, \"hint\":\"Markdown。結果のコードブロックが中心\", \"severity\":\"error|warn|info\"}]}";
        $usr = "ファイル名: {$filename}\n各行は「行番号<TAB>コード」の形式です。\n----\n{$numbered}";

        $res = ai_openai_plain($CONFIG, $model, [
            ['role' => 'system', 'content' => $sys],
            ['role' => 'user',   'content' => $usr],
        ]);
        if (!$res['ok']) { json_out(['error' => $res['error']]); }
        $tokens = (int)$res['tokens'];
        if ($tokens > 0) { ai_add_usage($db, $auth['user'], $tokens); }

        $parsed = json_decode(($res['text'] ?? '') ?: '{}', true);
        $issues = is_array($parsed['issues'] ?? null) ? $parsed['issues'] : [];
        $clean = [];
        foreach ($issues as $it) {
            $ln = (int)($it['line'] ?? 0);
            if ($ln < 1) continue;
            $sev = in_array(($it['severity'] ?? ''), ['error', 'warn', 'info'], true) ? $it['severity'] : 'info';
            $clean[] = ['line' => $ln, 'hint' => (string)($it['hint'] ?? ''), 'severity' => $sev];
        }
        json_out(['issues' => $clean, 'usage' => ['today' => ai_used_today($db, $auth['user']), 'cap' => $cap]]);

    case 'app':
    default:
        // メインUI(Monacoエディタ)
        $u = htmlspecialchars($auth['user']);
        $e = htmlspecialchars($auth['email']);
        include __DIR__ . '/ui.php';
        exit;
}
