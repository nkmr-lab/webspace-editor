<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>file.nkmr.io — <?= $u ?></title>
<style>
  :root { --bg:#1e1e1e; --panel:#252526; --border:#333; --fg:#ddd; --accent:#0a84ff; }
  * { box-sizing:border-box; }
  body { margin:0; height:100vh; display:flex; flex-direction:column; font-family:system-ui,sans-serif; background:var(--bg); color:var(--fg); }
  header { display:flex; align-items:center; gap:12px; padding:8px 14px; background:var(--panel); border-bottom:1px solid var(--border); }
  header b { color:var(--accent); }
  header .sp { flex:1; }
  #curfile { font-family:ui-monospace,monospace; font-size:12px; color:#9cdcfe; background:#1e1e1e; border:1px solid var(--border); border-radius:6px; padding:3px 9px; max-width:38vw; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  header a, header button { color:var(--fg); background:#333; border:1px solid var(--border); border-radius:6px; padding:5px 10px; text-decoration:none; cursor:pointer; font-size:13px; white-space:nowrap; }
  header button:hover, header a:hover { background:#3a3a3a; }
  main { flex:1; display:flex; min-height:0; }
  #side { width:280px; background:var(--panel); border-right:1px solid var(--border); overflow:auto; }
  #crumbs { padding:8px 10px; font-size:12px; color:#aaa; border-bottom:1px solid var(--border); word-break:break-all; }
  .row { padding:6px 12px; cursor:pointer; font-size:13px; display:flex; gap:8px; align-items:center; }
  .row:hover { background:#2d2d2d; }
  .row.dir { color:#7cc; }
  .row .nm { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .row .pcell { position:relative; display:inline-flex; align-items:center; justify-content:flex-end; min-width:36px; height:18px; }
  .row .pnum { font-size:11px; color:#888; font-family:ui-monospace,monospace; user-select:none; }
  .row:hover .pnum { visibility:hidden; }
  .row .pedit { position:absolute; right:-2px; top:50%; transform:translateY(-50%); display:none; background:var(--accent); color:#fff; border:none; border-radius:5px; font-size:12px; line-height:1; padding:3px 8px; cursor:pointer; }
  .row:hover .pedit { display:block; }
  .row .pedit:hover { filter:brightness(1.15); }
  #rowmenu { position:fixed; z-index:60; background:#252526; border:1px solid #444; border-radius:8px; box-shadow:0 6px 20px rgba(0,0,0,.5); padding:4px; display:none; min-width:170px; }
  #rowmenu.show { display:block; }
  #rowmenu button { display:block; width:100%; text-align:left; background:none; border:none; color:var(--fg); font-size:13px; padding:7px 12px; border-radius:6px; cursor:pointer; }
  #rowmenu button:hover { background:#0a3a66; }
  #rowmenu .sep { height:1px; background:#3a3a3a; margin:4px 6px; }
  #editor { flex:1; min-width:0; }
  #status { padding:4px 12px; font-size:12px; background:var(--panel); border-top:1px solid var(--border); color:#9c9; min-height:22px; }
  input[type=file]{ display:none; }

  /* AIヒント: 波線ではなく左端の💡 + 細い印(控えめ) */
  .ai-hint-glyph { cursor:help; }
  .ai-hint-glyph::before { content:'💡'; font-size:11px; margin-left:1px; }
  .ai-hint-linedeco { background:#e0b34d66; width:3px !important; margin-left:3px; }
  .ai-hint-linedeco-err { background:#e0555566; width:3px !important; margin-left:3px; }
  .ai-glyph-sql, .ai-glyph-sql-err { cursor:help; }
  .ai-glyph-sql::before { content:'🗄'; font-size:11px; margin-left:1px; }
  .ai-glyph-sql-err::before { content:'🚨'; font-size:11px; margin-left:1px; }

  /* AI確認中インジケータ */
  #aibusy { display:none; position:fixed; top:58px; left:50%; transform:translateX(-50%); z-index:75;
    background:#252526; border:1px solid var(--accent); color:#e6e6e6; border-radius:20px;
    padding:8px 16px; font-size:13px; box-shadow:0 6px 20px rgba(0,0,0,.5); align-items:center; gap:10px; }
  #aibusy.show { display:flex; }
  #aibusy .spin { width:14px; height:14px; border:2px solid #555; border-top-color:var(--accent);
    border-radius:50%; animation:aispin .8s linear infinite; }
  @keyframes aispin { to { transform:rotate(360deg); } }
  @media (prefers-reduced-motion: reduce) { #aibusy .spin { animation:none; } }

  /* ---- AI パネル ---- */
  #ai { width:380px; background:var(--panel); border-left:1px solid var(--border); display:none; flex-direction:column; min-height:0; }
  #ai.show { display:flex; }
  #aihead { padding:8px 12px; font-size:13px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; }
  #aihead .u { margin-left:auto; font-size:11px; color:#888; }
  #aimsgs { flex:1; overflow:auto; padding:10px; display:flex; flex-direction:column; gap:10px; }
  .m { font-size:13px; line-height:1.5; border-radius:8px; padding:8px 10px; white-space:pre-wrap; word-break:break-word; }
  .m.user { background:#0a3a66; align-self:flex-end; max-width:85%; }
  .m.ai { background:#2d2d2d; align-self:flex-start; max-width:92%; }
  .m.sys { background:transparent; color:#888; font-size:11.5px; padding:2px 4px; }
  .card { border:1px solid var(--border); border-radius:8px; background:#1e1e1e; padding:9px 10px; font-size:12.5px; display:flex; flex-direction:column; gap:7px; }
  .card .t { font-weight:600; color:var(--accent); }
  .card .path { font-family:ui-monospace,monospace; font-size:12px; color:#7cc; }
  .card .why { color:#bbb; }
  .card .btns { display:flex; gap:6px; flex-wrap:wrap; }
  .card button { font-size:12px; padding:4px 10px; border-radius:6px; border:1px solid var(--border); background:#333; color:var(--fg); cursor:pointer; }
  .card button.pri { background:var(--accent); border-color:var(--accent); color:#fff; }
  .card button:disabled { opacity:.5; cursor:default; }
  .card .done { color:#9c9; font-size:11.5px; }
  #aiform { border-top:1px solid var(--border); padding:8px; display:flex; flex-direction:column; gap:6px; }
  #aiin { width:100%; resize:vertical; min-height:52px; background:#1e1e1e; color:var(--fg); border:1px solid var(--border); border-radius:6px; padding:7px; font-family:inherit; font-size:13px; }
  #aiform .row2 { display:flex; gap:6px; align-items:center; }
  #aiform select { background:#1e1e1e; color:var(--fg); border:1px solid var(--border); border-radius:6px; padding:5px; font-size:12px; }
  #aiform button.send { margin-left:auto; background:var(--accent); border:1px solid var(--accent); color:#fff; border-radius:6px; padding:6px 14px; cursor:pointer; font-size:13px; }
  #aiform button.send:disabled { opacity:.5; cursor:default; }

  /* diff モーダル */
  #diffwrap { display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:50; padding:40px; }
  #diffwrap.show { display:flex; flex-direction:column; }
  #diffbar { background:var(--panel); padding:8px 12px; display:flex; align-items:center; gap:10px; border:1px solid var(--border); border-bottom:none; border-radius:8px 8px 0 0; }
  #diffbar .sp { flex:1; }
  #diffbar button { font-size:13px; padding:5px 14px; border-radius:6px; border:1px solid var(--border); background:#333; color:var(--fg); cursor:pointer; }
  #diffbar button.pri { background:var(--accent); border-color:var(--accent); color:#fff; }
  #diffed { flex:1; border:1px solid var(--border); }

  /* ---- 検索バー ---- */
  #searchbar { display:flex; gap:6px; padding:8px 8px 6px; border-bottom:1px solid var(--border); }
  #searchbar input { flex:1; min-width:0; background:#1e1e1e; color:var(--fg); border:1px solid var(--border); border-radius:6px; padding:5px 8px; font-size:12px; }
  #searchbar select { background:#1e1e1e; color:var(--fg); border:1px solid var(--border); border-radius:6px; font-size:11px; }
  .sresult { flex-direction:column; align-items:flex-start !important; gap:2px; }
  .sresult .sp2 { font-size:11px; color:#888; font-family:ui-monospace,monospace; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; }

  /* ---- メディアプレビュー ---- */
  #preview { flex:1; min-width:0; display:none; flex-direction:column; background:#181818; }
  #preview .pv-head { padding:6px 12px; font-size:12px; color:#aaa; border-bottom:1px solid var(--border); display:flex; gap:10px; align-items:center; }
  #preview .pv-head a { color:var(--fg); background:#333; border:1px solid var(--border); border-radius:6px; padding:4px 10px; text-decoration:none; font-size:12px; }
  #preview .pv-name { font-family:ui-monospace,monospace; color:#9cdcfe; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  #preview .pv-body { flex:1; display:flex; align-items:center; justify-content:center; overflow:auto; padding:16px; }
  #preview .pv-body img, #preview .pv-body video { max-width:100%; max-height:100%; object-fit:contain; }
  #preview .pv-body audio { width:80%; }
  #preview .pv-body iframe { width:100%; height:100%; border:none; background:#fff; }

  /* ---- ドロップゾーン ---- */
  #dropzone { display:none; position:fixed; inset:0; z-index:80; background:rgba(10,132,255,.12); border:4px dashed var(--accent); align-items:center; justify-content:center; font-size:20px; color:#fff; pointer-events:none; }
  #dropzone.show { display:flex; }

  /* ---- 確認モーダル ---- */
  .modalbox { display:none; position:fixed; inset:0; z-index:90; background:rgba(0,0,0,.55); align-items:center; justify-content:center; }
  .modalbox.show { display:flex; }
  .mb-card { background:var(--panel); border:1px solid var(--border); border-radius:12px; padding:18px 20px; max-width:430px; width:90%; box-shadow:0 10px 40px rgba(0,0,0,.5); }
  .mb-title { font-size:15px; font-weight:600; margin-bottom:8px; }
  .mb-body { font-size:13px; color:#ccc; margin-bottom:16px; line-height:1.55; }
  .mb-body .ub-name { font-family:ui-monospace,monospace; color:#9cdcfe; }
  .mb-btns { display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap; }
  .mb-btns button { font-size:13px; padding:7px 14px; border-radius:7px; border:1px solid var(--border); background:#333; color:var(--fg); cursor:pointer; }
  .mb-btns button.pri { background:var(--accent); border-color:var(--accent); color:#fff; }

  /* ---- モバイルモード ---- */
  #menuToggle { display:none; font-size:16px; }
  #sideBackdrop { display:none; }
  @media (max-width: 720px) {
    header { flex-wrap:wrap; row-gap:6px; padding:6px 10px; gap:8px; }
    header b { display:none; }            /* ブランドを畳んでファイル名に幅を */
    header span { font-size:12px; }
    #curfile { max-width:52vw; }
    header a, header button, header label { padding:5px 8px; font-size:12px; }
    #menuToggle { display:inline-block; }
    main { position:relative; }
    #side { position:absolute; left:0; top:0; bottom:0; width:82%; max-width:320px;
            transform:translateX(-100%); transition:transform .2s ease; z-index:30;
            box-shadow:2px 0 14px rgba(0,0,0,.55); }
    #side.open { transform:translateX(0); }
    #sideBackdrop.show { display:block; position:absolute; inset:0; background:rgba(0,0,0,.45); z-index:25; }
    #ai { position:fixed; inset:0; width:100%; z-index:70; }
    #diffwrap { padding:8px; }
  }
</style>
</head>
<body>
<header>
  <button id="menuToggle" onclick="toggleSide()" title="ファイル一覧">☰</button>
  <b>file.nkmr.io</b>
  <span id="curfile" title="開いているファイル">（ファイル未選択）</span>
  <span class="sp"></span>
  <button onclick="saveFile()" title="保存 (Ctrl+S)">💾 保存</button>
  <button onclick="openCurrentInBrowser()" title="実物URLを新規タブで開く(実行確認)">🌐 表示</button>
  <button onclick="newMenu(event)" title="新規作成 / アップロード">＋ 新規 ▾</button>
<?php if (!empty($ai_gen_allowed)): ?>
  <button onclick="toggleAI()">🤖 AI</button>
<?php endif; ?>
  <button id="aihintbtn" onclick="aiCheck()" title="AIが問題点をヒントで指摘します（答えは言いません・学習用）">🔎 AIヒント</button>
  <button id="sqlbtn" onclick="sqlCheck()" title="コード内のSQLの危険（エスケープ/インジェクション）を指摘し、悪い入力での展開例を見せます">🗄 SQLチェック</button>
  <input type="file" id="up" multiple onchange="uploadFile(this)">
  <button onclick="userMenu(event)" title="アカウント">👤 <?= $u ?> ▾</button>
</header>
<main>
  <div id="side">
    <div id="searchbar">
      <input id="q" placeholder="🔍 検索…" oninput="onSearchInput()" onkeydown="if(event.key==='Enter')runSearch()">
      <select id="qmode" onchange="runSearch()" title="検索対象"><option value="name">名前</option><option value="content">中身</option></select>
    </div>
    <div id="crumbs"></div>
    <div id="list"></div>
  </div>
  <div id="sideBackdrop" onclick="closeSide()"></div>
  <div id="editor"></div>
  <div id="preview">
    <div class="pv-head"><span class="pv-name"></span><span class="sp"></span><a class="pv-dl" href="#">⬇ ダウンロード</a></div>
    <div class="pv-body"></div>
  </div>
  <div id="ai">
    <div id="aihead">🤖 AI アシスタント <span class="u" id="aiusage"></span>
      <button onclick="toggleAI()" title="閉じる" style="margin-left:8px;background:#333;border:1px solid var(--border);color:var(--fg);border-radius:6px;padding:2px 9px;cursor:pointer;">✕</button></div>
    <div id="aimsgs">
      <div class="m sys">開いているファイルについて指示できます。例:「この関数にエラーハンドリングを足して」。ファイルの読み書きが必要な時は承認を求めます。</div>
    </div>
    <div id="aiform">
      <textarea id="aiin" placeholder="指示を入力(Ctrl+Enterで送信)… 例: このPHPをPDOのプリペアド文に直して"></textarea>
      <div class="row2">
        <select id="aimodel" title="モデル">
          <option value="mini">mini(安い・既定)</option>
          <option value="codex">codex(コード特化)</option>
          <option value="strong">strong(高性能)</option>
        </select>
        <button class="send" id="aisend" onclick="sendAI()">送信</button>
      </div>
    </div>
  </div>
</main>
<div id="status">準備中…</div>
<div id="rowmenu"></div>
<div id="dropzone">📥 ここにドロップしてアップロード</div>
<div id="aibusy"><span class="spin"></span> 🔎 AIがコードを確認しています…</div>
<div id="unsavedbox" class="modalbox">
  <div class="mb-card">
    <div class="mb-title">未保存の変更があります</div>
    <div class="mb-body"><span class="ub-name"></span> の変更が保存されていません。どうしますか？</div>
    <div class="mb-btns">
      <button class="ub-cancel">やめる</button>
      <button class="ub-discard">保存せず続行</button>
      <button class="pri ub-save">保存して続行</button>
    </div>
  </div>
</div>

<div id="diffwrap">
  <div id="diffbar">
    <span id="difftitle"></span><span class="sp"></span>
    <button onclick="closeDiff()">閉じる</button>
    <button class="pri" id="diffapply">この内容で保存</button>
  </div>
  <div id="diffed"></div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.52.2/min/vs/loader.min.js"></script>
<script>
let CSRF = '', CWD = '', CURFILE = null, editor = null, BASEUSER = '';
let PREVIEWING = false, SEARCHING = false, searchTimer = null, dragDepth = 0;
let DIRTY = false, SAVED_CONTENT = '';
// 公開URLテンプレ。{user} をユーザー名に置換。既定=サブドメイン(<user>.nkmr.io)。
const PUBLIC_URL_TPL = <?= json_encode($CONFIG['public_url_tpl'] ?? 'https://{user}.nkmr.io/', JSON_UNESCAPED_SLASHES) ?>;
function fileUrl(rel){
  const base = PUBLIC_URL_TPL.replace('{user}', encodeURIComponent(BASEUSER));
  return base + String(rel).split('/').map(encodeURIComponent).join('/');
}
function openInBrowser(rel){ window.open(fileUrl(rel), '_blank', 'noopener'); }
function openCurrentInBrowser(){ if(!CURFILE){ S('開いているファイルがありません'); return; } window.open(fileUrl(CURFILE), '_blank', 'noopener'); }
const S = (m)=>{ document.getElementById('status').textContent = m; };

// ---- モバイル: ファイラーをドロワー化 ----
function isMobile(){ return window.matchMedia('(max-width:720px)').matches; }
function openSide(){ document.getElementById('side').classList.add('open'); document.getElementById('sideBackdrop').classList.add('show'); }
function closeSide(){ document.getElementById('side').classList.remove('open'); document.getElementById('sideBackdrop').classList.remove('show'); }
function toggleSide(){ document.getElementById('side').classList.contains('open') ? closeSide() : openSide(); }

// ---- URLに現在の状態(フォルダ/開いているファイル)を反映 → リロードで復元 ----
function syncUrl(){
  const parts=[];
  if(CWD) parts.push('d='+encodeURIComponent(CWD));
  if(CURFILE) parts.push('f='+encodeURIComponent(CURFILE));
  history.replaceState(null, '', location.pathname + (parts.length ? '#'+parts.join('&') : ''));
}
async function restoreFromUrl(){
  const p=new URLSearchParams(location.hash.replace(/^#/,''));
  const d=p.get('d')||'', f=p.get('f')||'';
  try{ await loadDir(d); }catch(e){ await loadDir(''); }
  if(f){ try{ await openFile(f, f.split('/').pop()); }catch(e){} }
}

require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.52.2/min/vs' }});
require(['vs/editor/editor.main'], function() {
  editor = monaco.editor.create(document.getElementById('editor'), {
    value: '// 左のファイルを開いてください', language: 'plaintext', theme: 'vs-dark', automaticLayout: true, glyphMargin: true
  });
  window.addEventListener('keydown', e=>{ if((e.ctrlKey||e.metaKey)&&e.key==='s'){ e.preventDefault(); saveFile(); }});
  editor.onDidChangeModelContent(()=>{
    if(PREVIEWING) return;
    const d = (editor.getValue() !== SAVED_CONTENT);
    if(d !== DIRTY){ DIRTY = d; setCurLabel(); }
  });
  window.addEventListener('beforeunload', e=>{ if(DIRTY){ e.preventDefault(); e.returnValue=''; } });
  restoreFromUrl().then(()=>{ if(isMobile() && !CURFILE) openSide(); });   // URLから状態復元。スマホは一覧を出す
});

function langOf(name){
  const ext = name.split('.').pop().toLowerCase();
  const m = {php:'php',js:'javascript',ts:'typescript',css:'css',html:'html',htm:'html',json:'json',md:'markdown',py:'python',sql:'sql',xml:'xml',sh:'shell',c:'c',cpp:'cpp',java:'java'};
  return m[ext] || 'plaintext';
}
async function api(action, params={}){
  const q = new URLSearchParams({action, ...params});
  const r = await fetch('?'+q.toString());
  if(!r.ok) throw new Error(await r.text());
  return r;
}
async function loadDir(path){
  const r = await api('list', {path});
  const d = await r.json();
  CSRF = d.csrf; CWD = d.path; BASEUSER = d.base_user;
  document.getElementById('crumbs').textContent = '/'+d.base_user+'/public_html/'+ (d.path||'');
  const list = document.getElementById('list'); list.innerHTML='';
  if(d.path){ const up=document.createElement('div'); up.className='row dir'; up.textContent='📁 ..';
    up.onclick=()=>loadDir(d.path.split('/').slice(0,-1).join('/')); list.appendChild(up); }
  d.items.sort((a,b)=> (b.is_dir-a.is_dir) || a.name.localeCompare(b.name));
  for(const it of d.items){
    const row=document.createElement('div'); row.className='row'+(it.is_dir?' dir':'');
    const rel = (d.path?d.path+'/':'')+it.name;
    const nm=document.createElement('span'); nm.className='nm'; nm.textContent=(it.is_dir?'📁 ':'📄 ')+it.name;
    nm.onclick = it.is_dir ? ()=>loadDir(rel) : ()=>openFile(rel, it.name);
    const item={rel, name:it.name, is_dir:it.is_dir, perms:it.perms};
    const pcell=document.createElement('span'); pcell.className='pcell';
    const pnum=document.createElement('span'); pnum.className='pnum'; pnum.textContent=it.perms||'…';
    const pedit=document.createElement('button'); pedit.className='pedit'; pedit.textContent='✎'; pedit.title='編集メニュー(名前変更/権限/DL/削除)';
    pedit.onclick=(e)=>{ e.stopPropagation(); openRowMenu(e, item); };
    pcell.append(pnum, pedit);
    row.oncontextmenu=(e)=>{ e.preventDefault(); e.stopPropagation(); openRowMenu(e, item); };
    row.append(nm, pcell);
    list.appendChild(row);
  }
  S('一覧: '+d.items.length+'件');
  syncUrl();
}
async function postAct(action, fields){
  const fd=new FormData(); fd.append('csrf',CSRF);
  for(const k in fields) fd.append(k, fields[k]);
  const r=await fetch('?action='+action,{method:'POST',body:fd});
  if(!r.ok){ S(action+'失敗: '+await r.text()); return false; }
  return true;
}
function mediaType(name){
  const e=(name.split('.').pop()||'').toLowerCase();
  if(['png','jpg','jpeg','gif','webp','bmp','ico','svg','avif'].includes(e)) return 'image';
  if(e==='pdf') return 'pdf';
  if(['mp3','wav','ogg','m4a','aac'].includes(e)) return 'audio';
  if(['mp4','webm','mov','m4v'].includes(e)) return 'video';
  return null;
}
function showEditorPane(){ document.getElementById('preview').style.display='none'; document.getElementById('editor').style.display=''; if(editor) editor.layout(); }
function showPreview(rel, name, type){
  PREVIEWING=true; DIRTY=false; CURFILE=rel; setCurLabel('🖼 '+rel);
  const url='?action=raw&path='+encodeURIComponent(rel), p=document.getElementById('preview');
  let inner='';
  if(type==='image') inner='<img alt="" src="'+url+'">';
  else if(type==='pdf') inner='<iframe src="'+url+'"></iframe>';
  else if(type==='audio') inner='<audio src="'+url+'" controls></audio>';
  else if(type==='video') inner='<video src="'+url+'" controls></video>';
  p.querySelector('.pv-body').innerHTML=inner;
  p.querySelector('.pv-name').textContent=name;
  p.querySelector('.pv-dl').href='?action=download&path='+encodeURIComponent(rel);
  document.getElementById('editor').style.display='none'; p.style.display='flex';
  S('プレビュー: '+rel); syncUrl();
}
async function openFile(rel, name){
  if(!(await guardUnsaved())) return;   // 未保存があれば確認
  const mt=mediaType(name);
  if(mt){ showPreview(rel, name, mt); if(isMobile()) closeSide(); return; }
  try{ const r = await api('read',{path:rel}); const txt = await r.text();
    showEditorPane(); PREVIEWING=false;
    CURFILE = rel; SAVED_CONTENT = txt;
    monaco.editor.setModelLanguage(editor.getModel(), langOf(name));
    editor.setValue(txt); DIRTY=false; clearAiHints(); S('開いた: '+rel); setCurLabel(); syncUrl();
    if(isMobile()) closeSide();   // スマホ: ファイルを開いたらファイラーを隠す
  }catch(e){ S('読込エラー: '+e.message); }
}
async function saveFile(){
  if(PREVIEWING){ S('プレビュー中は保存できません'); return false; }
  if(!CURFILE){ S('保存先ファイルがありません（新規で作成してください）'); return false; }
  const content=editor.getValue();
  const fd = new FormData(); fd.append('path',CURFILE); fd.append('content',content); fd.append('csrf',CSRF);
  const r = await fetch('?action=save',{method:'POST',body:fd});
  if(r.ok){ SAVED_CONTENT=content; DIRTY=false; S('保存しました: '+CURFILE); setCurLabel(); loadDir(CWD); return true; }
  S('保存失敗: '+await r.text()); return false;
}
async function uploadFiles(files){
  if(!files || !files.length) return;
  let ok=0;
  for(const f of files){
    S('アップロード中 '+(ok+1)+'/'+files.length+': '+f.name);
    const fd=new FormData(); fd.append('file',f); fd.append('path',CWD); fd.append('csrf',CSRF);
    const r=await fetch('?action=upload',{method:'POST',body:fd});
    if(r.ok) ok++; else S('失敗: '+f.name+' '+await r.text());
  }
  S('アップロード完了 '+ok+'/'+files.length); loadDir(CWD);
}
function uploadFile(input){ uploadFiles(input.files); input.value=''; }
async function newFile(){
  if(!(await guardUnsaved())) return;
  const name = prompt('新規ファイル名 (例: index.php)'); if(!name) return;
  showEditorPane(); PREVIEWING=false;
  CURFILE = (CWD?CWD+'/':'')+name; SAVED_CONTENT='';
  monaco.editor.setModelLanguage(editor.getModel(), langOf(name));
  editor.setValue(''); DIRTY=false; clearAiHints(); S('新規: '+CURFILE+' （保存すると作成されます）');
  setCurLabel('📄 '+CURFILE+'（新規・未保存）'); syncUrl();
}
async function newDir(){
  const name = prompt('新規フォルダ名'); if(!name) return;
  const rel = (CWD?CWD+'/':'')+name;
  if(await postAct('mkdir', {path:rel})){ S('フォルダを作成: '+name); loadDir(CWD); }
}
async function renameItem(rel, name){
  const nn = prompt('新しい名前', name); if(!nn || nn===name) return;
  const to = (CWD?CWD+'/':'')+nn;
  if(await postAct('rename', {from:rel, to})){
    S('名前変更: '+name+' → '+nn);
    if(CURFILE===rel){ CURFILE=to; setCurLabel(); }
    loadDir(CWD);
  }
}
// 汎用ドロップダウン(行メニュー/ヘッダの新規・アカウントで共用)
function showMenu(e, items){
  e.stopPropagation();
  const m=document.getElementById('rowmenu'); m.innerHTML='';
  for(const it of items){
    if(it.sep){ const d=document.createElement('div'); d.className='sep'; m.appendChild(d); continue; }
    if(it.info){ const d=document.createElement('div'); d.style.cssText='padding:6px 12px;color:#888;font-size:11px;'; d.textContent=it.label; m.appendChild(d); continue; }
    const b=document.createElement('button'); b.textContent=it.label;
    b.onclick=(ev)=>{ ev.stopPropagation(); closeRowMenu(); it.fn(); };
    m.appendChild(b);
  }
  m.classList.add('show');
  const mw=m.offsetWidth||190, mh=m.offsetHeight||170;
  m.style.left=Math.min(e.clientX, window.innerWidth-mw-8)+'px';
  m.style.top=Math.min(e.clientY+6, window.innerHeight-mh-8)+'px';
}
function openRowMenu(e, item){
  const items=[
    {label:'✎ 名前変更', fn:()=>renameItem(item.rel, item.name)},
    {label:'🔑 権限変更 ('+(item.perms||'')+')', fn:()=>chmodItem(item.rel, item.name, item.perms)},
    {label:'🌐 ブラウザで開く(実行)', fn:()=>openInBrowser(item.rel)},
  ];
  if(!item.is_dir) items.push({label:'⬇ ダウンロード', fn:()=>downloadItem(item.rel)});
  items.push({sep:true}, {label:'🗑 削除', fn:()=>deleteItem(item.rel, item.name, item.is_dir)});
  showMenu(e, items);
}
function newMenu(e){ showMenu(e, [
  {label:'＋ 新規ファイル', fn:newFile},
  {label:'📁 新規フォルダ', fn:newDir},
  {label:'⬆ アップロード', fn:()=>document.getElementById('up').click()},
]); }
function userMenu(e){ showMenu(e, [
  {info:true, label:<?= json_encode($e) ?>},
  {sep:true},
  {label:'🚪 ログアウト', fn:()=>{ location.href='?action=logout'; }},
]); }
function closeRowMenu(){ document.getElementById('rowmenu').classList.remove('show'); }
function downloadItem(rel){ window.location.href='?action=download&path='+encodeURIComponent(rel); }
function setCurLabel(txt){
  const el=document.getElementById('curfile');
  if(txt){ el.textContent=txt; return; }
  el.textContent = CURFILE ? ((DIRTY?'● ':'')+'📄 '+CURFILE) : '（ファイル未選択）';
}
// 未保存があれば 保存/破棄/やめる を聞く。続行してよければ true。
function askUnsaved(name){
  return new Promise(res=>{
    const box=document.getElementById('unsavedbox');
    box.querySelector('.ub-name').textContent=name;
    box.classList.add('show');
    const done=v=>{ box.classList.remove('show'); res(v); };
    box.querySelector('.ub-save').onclick=()=>done('save');
    box.querySelector('.ub-discard').onclick=()=>done('discard');
    box.querySelector('.ub-cancel').onclick=()=>done('cancel');
  });
}
async function guardUnsaved(){
  if(!DIRTY) return true;
  const c=await askUnsaved(CURFILE||'(無題)');
  if(c==='cancel') return false;
  if(c==='save'){ return await saveFile(); }   // 保存失敗なら中断
  return true;   // discard
}
document.addEventListener('click', closeRowMenu);
document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeRowMenu(); });

// ---- 検索 ----
function onSearchInput(){ clearTimeout(searchTimer); searchTimer=setTimeout(runSearch, 350); }
async function runSearch(){
  const q=document.getElementById('q').value.trim(), mode=document.getElementById('qmode').value;
  if(q.length<2){ if(SEARCHING){ SEARCHING=false; loadDir(CWD); } return; }
  SEARCHING=true; S('検索中…');
  try{ const r=await api('search',{q, mode}); renderSearchResults(await r.json(), q); }
  catch(e){ S('検索エラー: '+e.message); }
}
function renderSearchResults(d, q){
  document.getElementById('crumbs').textContent='🔍 「'+q+'」: '+d.results.length+'件'+(d.capped?'（多数のため打ち切り）':'');
  const list=document.getElementById('list'); list.innerHTML='';
  const back=document.createElement('div'); back.className='row dir'; back.textContent='← 一覧に戻る';
  back.onclick=()=>{ document.getElementById('q').value=''; SEARCHING=false; loadDir(CWD); };
  list.appendChild(back);
  for(const it of d.results){
    const row=document.createElement('div'); row.className='row sresult';
    const nm=document.createElement('span'); nm.className='nm'; nm.textContent='📄 '+it.path; row.appendChild(nm);
    if(it.snippet){ const sn=document.createElement('span'); sn.className='sp2'; sn.textContent=(it.line?('L'+it.line+': '):'')+it.snippet; row.appendChild(sn); }
    row.onclick=()=>{ openFile(it.path, it.path.split('/').pop()).then(()=>{
      if(it.line && editor && !PREVIEWING){ setTimeout(()=>{ editor.revealLineInCenter(it.line); editor.setPosition({lineNumber:it.line, column:1}); editor.focus(); }, 120); }
    }); };
    list.appendChild(row);
  }
  if(!d.results.length){ const none=document.createElement('div'); none.className='row'; none.style.color='#888'; none.textContent='該当なし'; list.appendChild(none); }
}

// ---- ドラッグ&ドロップ アップロード ----
function showDrop(b){ document.getElementById('dropzone').classList.toggle('show', b); }
function hasFiles(e){ return e.dataTransfer && Array.from(e.dataTransfer.types||[]).includes('Files'); }
window.addEventListener('dragenter', e=>{ if(hasFiles(e)){ e.preventDefault(); dragDepth++; showDrop(true); } });
window.addEventListener('dragover', e=>{ if(hasFiles(e)) e.preventDefault(); });
window.addEventListener('dragleave', e=>{ dragDepth=Math.max(0,dragDepth-1); if(dragDepth===0) showDrop(false); });
window.addEventListener('drop', e=>{ e.preventDefault(); dragDepth=0; showDrop(false);
  if(e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) uploadFiles(e.dataTransfer.files); });

async function chmodItem(rel, name, cur){
  const m = prompt('「'+name+'」のパーミッション (3桁8進数, 例: 755 / 644)', cur||'644');
  if(!m) return;
  if(!/^[0-7]{3}$/.test(m)){ S('形式エラー: 3桁の8進数で (例: 755)'); return; }
  if(await postAct('chmod', {path:rel, mode:m})){ S('権限変更: '+name+' → '+m); loadDir(CWD); }
}
async function deleteItem(rel, name, isDir){
  const msg = isDir ? 'フォルダ「'+name+'」を中身ごと削除しますか？（元に戻せません）'
                    : 'ファイル「'+name+'」を削除しますか？';
  if(!confirm(msg)) return;
  if(await postAct('delete', {path:rel})){
    S('削除: '+name);
    if(CURFILE===rel){ CURFILE=null; SAVED_CONTENT=''; editor.setValue(''); DIRTY=false; setCurLabel(); }
    loadDir(CWD);
  }
}
// ================= AI アシスタント =================
let aiHistory = [];          // OpenAIに渡す会話(user/assistant/tool)
let aiPending = new Set();   // 未解決の tool_call_id
let aiBusy = false;
let diffEditor = null, diffApplyFn = null;

function toggleAI(){ document.getElementById('ai').classList.toggle('show'); if(editor) editor.layout(); }

// 学習用: AIが問題点をヒントで指摘 → 左端の💡(ホバーで吹き出し)+ 細い印。波線は使わない。
let aiDecos = null;
function clearAiHints(){ if(aiDecos){ aiDecos.clear(); aiDecos=null; } if(editor) monaco.editor.setModelMarkers(editor.getModel(), 'ai-hints', []); }
async function aiCheck(){
  if(PREVIEWING || !CURFILE){ S('チェックするファイルを開いてください'); return; }
  const btn=document.getElementById('aihintbtn'), busy=document.getElementById('aibusy');
  const orig=btn.textContent; btn.disabled=true; btn.textContent='🔎 確認中…'; busy.classList.add('show'); S('AIがコードを確認中…');
  let d=null;
  try{
    const r=await fetch('?action=aicheck',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF':CSRF},body:JSON.stringify({content:editor.getValue(), filename:CURFILE})});
    d=await r.json();
  }catch(e){ S('通信エラー: '+e.message); }
  finally{ btn.disabled=false; btn.textContent=orig; busy.classList.remove('show'); }
  if(!d) return;
  if(d.error){ S('⚠ '+d.error); return; }
  clearAiHints();
  const model=editor.getModel(), markers=[], decos=[];
  for(const it of (d.issues||[])){
    const ln=Math.max(1, it.line);
    if(it.severity==='error'){
      // 重大(文法エラー等)は赤波線で目立たせる
      markers.push({ startLineNumber:ln, endLineNumber:ln, startColumn:1,
        endColumn:Math.max(model.getLineMaxColumn(ln), 2),
        message:'💡 '+it.hint, severity:monaco.MarkerSeverity.Error });
    } else {
      // それ以外は左端の💡(控えめ)
      decos.push({ range:new monaco.Range(ln,1,ln,1), options:{
        isWholeLine:true, glyphMarginClassName:'ai-hint-glyph',
        glyphMarginHoverMessage:{ value:'💡 '+it.hint },
        linesDecorationsClassName:'ai-hint-linedeco',
        overviewRuler:{ color:'#e0b34d', position:monaco.editor.OverviewRulerLane.Right }
      }});
    }
  }
  monaco.editor.setModelMarkers(model, 'ai-hints', markers);
  aiDecos = editor.createDecorationsCollection(decos);
  if(d.usage){ document.getElementById('aiusage').textContent=d.usage.today+' / '+d.usage.cap+' tok'; }
  const total=markers.length+decos.length;
  S(total ? ('AIヒント '+total+'件（重大は赤波線、その他は左端の 💡・ホバーで表示）') : 'AIは目立った問題を見つけませんでした（保証はしません）');
}

// 学習用: コード内SQLの危険を指摘 + 悪い入力での「展開後SQL」をホバー(Markdown)で表示
async function sqlCheck(){
  if(PREVIEWING || !CURFILE){ S('チェックするファイルを開いてください'); return; }
  const btn=document.getElementById('sqlbtn'), busy=document.getElementById('aibusy');
  const orig=btn.textContent; btn.disabled=true; btn.textContent='🗄 確認中…'; busy.classList.add('show'); S('AIがSQLを確認中…');
  let d=null;
  try{
    const r=await fetch('?action=sqlcheck',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF':CSRF},body:JSON.stringify({content:editor.getValue(), filename:CURFILE})});
    d=await r.json();
  }catch(e){ S('通信エラー: '+e.message); }
  finally{ btn.disabled=false; btn.textContent=orig; busy.classList.remove('show'); }
  if(!d) return;
  if(d.error){ S('⚠ '+d.error); return; }
  clearAiHints();
  const decos=(d.issues||[]).map(it=>{
    const ln=Math.max(1, it.line), isErr=(it.severity==='error');
    return { range:new monaco.Range(ln,1,ln,1), options:{
      isWholeLine:true,
      glyphMarginClassName: isErr?'ai-glyph-sql-err':'ai-glyph-sql',
      glyphMarginHoverMessage:{ value: it.hint },   // Markdown(展開後SQLをコードで表示)
      linesDecorationsClassName: isErr?'ai-hint-linedeco-err':'ai-hint-linedeco',
      overviewRuler:{ color: isErr?'#e05555':'#e0b34d', position:monaco.editor.OverviewRulerLane.Right }
    }};
  });
  aiDecos = editor.createDecorationsCollection(decos);
  if(d.usage){ document.getElementById('aiusage').textContent=d.usage.today+' / '+d.usage.cap+' tok'; }
  S(decos.length ? ('SQLの指摘 '+decos.length+'件：該当行の左端アイコン(🗄/🚨)にマウスを乗せると展開例が出ます') : 'AIは目立ったSQLの問題を見つけませんでした（保証はしません）');
}
const aimsgs = ()=>document.getElementById('aimsgs');
function aiScroll(){ const e=aimsgs(); e.scrollTop=e.scrollHeight; }

function addBubble(cls, text){
  const d=document.createElement('div'); d.className='m '+cls; d.textContent=text; aimsgs().appendChild(d); aiScroll(); return d;
}
function setBusy(b){ aiBusy=b; document.getElementById('aisend').disabled=b; document.getElementById('aisend').textContent=b?'…':'送信'; }

async function sendAI(){
  if(aiBusy) return;
  const inEl=document.getElementById('aiin'); const text=inEl.value.trim();
  if(!text) return;
  inEl.value='';
  aiHistory.push({role:'user', content:text});
  addBubble('user', text);
  await callAI();
}

async function callAI(){
  setBusy(true);
  const payload={
    model_key: document.getElementById('aimodel').value,
    current_file: (CURFILE && !PREVIEWING) ? {path:CURFILE, content:editor.getValue()} : null,
    messages: aiHistory,
  };
  let data;
  try{
    const r=await fetch('?action=ai',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF':CSRF},body:JSON.stringify(payload)});
    data=await r.json();
  }catch(e){ addBubble('sys','通信エラー: '+e.message); setBusy(false); return; }
  if(data.error){ addBubble('sys','⚠ '+data.error); setBusy(false); return; }
  if(data.usage){ document.getElementById('aiusage').textContent=data.usage.today+' / '+data.usage.cap+' tok'; }

  const msg=data.message||{};
  aiHistory.push(msg);                                  // assistantメッセージをそのまま履歴へ
  if(msg.content) addBubble('ai', msg.content);

  const calls=msg.tool_calls||[];
  if(calls.length===0){ setBusy(false); return; }
  aiPending=new Set(calls.map(c=>c.id));
  for(const c of calls) renderToolCard(c);
  setBusy(false);   // ユーザーのカード操作待ち。全部解決したら自動で継続。
}

function resolveTool(id, resultText, cardEl){
  aiHistory.push({role:'tool', tool_call_id:id, content:resultText});
  aiPending.delete(id);
  if(cardEl){ cardEl.querySelectorAll('button').forEach(b=>b.disabled=true);
    const d=document.createElement('div'); d.className='done'; d.textContent='✓ '+resultText.split('\n')[0]; cardEl.appendChild(d); }
  if(aiPending.size===0){ callAI(); }   // 全ツール解決 → モデルに続きを促す
}

function renderToolCard(call){
  let args={}; try{ args=JSON.parse(call.function.arguments||'{}'); }catch(e){}
  const name=call.function.name;
  const card=document.createElement('div'); card.className='card';
  const wrap=document.createElement('div'); wrap.className='m ai'; wrap.style.maxWidth='95%'; wrap.appendChild(card); aimsgs().appendChild(wrap);

  const t=document.createElement('div'); t.className='t'; card.appendChild(t);
  const btns=document.createElement('div'); btns.className='btns';

  if(name==='propose_edit'){
    t.textContent='✏ このファイルの編集を提案';
    if(args.summary){ const s=document.createElement('div'); s.className='why'; s.textContent=args.summary; card.appendChild(s); }
    const bDiff=document.createElement('button'); bDiff.textContent='差分を見る';
    bDiff.onclick=()=>openDiff(CURFILE||'(無題)', editor.getValue(), args.new_content||'', ()=>{
      editor.setValue(args.new_content||''); saveFile(); closeDiff();
      resolveTool(call.id, '差分を適用して保存しました', card);
    });
    const bApply=document.createElement('button'); bApply.className='pri'; bApply.textContent='適用して保存';
    bApply.onclick=()=>{ editor.setValue(args.new_content||''); saveFile(); resolveTool(call.id,'適用して保存しました',card); };
    const bNo=document.createElement('button'); bNo.textContent='却下';
    bNo.onclick=()=>resolveTool(call.id,'ユーザーが却下しました',card);
    btns.append(bDiff,bApply,bNo);
  }
  else if(name==='request_open_file'){
    t.textContent='📂 ファイルを見せてほしい';
    const p=document.createElement('div'); p.className='path'; p.textContent=args.path||''; card.appendChild(p);
    if(args.reason){ const s=document.createElement('div'); s.className='why'; s.textContent=args.reason; card.appendChild(s); }
    const bOk=document.createElement('button'); bOk.className='pri'; bOk.textContent='開いて渡す';
    bOk.onclick=async()=>{
      try{ const r=await api('read',{path:args.path}); const txt=await r.text();
        openFile(args.path, args.path.split('/').pop());
        resolveTool(call.id, 'ファイル '+args.path+' の内容:\n'+txt, card);
      }catch(e){ resolveTool(call.id,'読めませんでした: '+e.message, card); }
    };
    const bNo=document.createElement('button'); bNo.textContent='拒否';
    bNo.onclick=()=>resolveTool(call.id,'ユーザーが拒否しました',card);
    btns.append(bOk,bNo);
  }
  else if(name==='propose_new_file'){
    t.textContent='➕ 新規ファイルの作成を提案';
    const p=document.createElement('div'); p.className='path'; p.textContent=args.path||''; card.appendChild(p);
    if(args.summary){ const s=document.createElement('div'); s.className='why'; s.textContent=args.summary; card.appendChild(s); }
    const bOk=document.createElement('button'); bOk.className='pri'; bOk.textContent='作成';
    bOk.onclick=async()=>{
      const ok=await postAct('save',{path:args.path, content:args.content||''});
      if(ok){ loadDir(CWD); openFile(args.path, args.path.split('/').pop());
        resolveTool(call.id,'作成しました: '+args.path, card); }
      else { resolveTool(call.id,'作成に失敗しました', card); }
    };
    const bNo=document.createElement('button'); bNo.textContent='拒否';
    bNo.onclick=()=>resolveTool(call.id,'ユーザーが拒否しました',card);
    btns.append(bOk,bNo);
  }
  else { t.textContent='未知のツール: '+name;
    const bNo=document.createElement('button'); bNo.textContent='スキップ';
    bNo.onclick=()=>resolveTool(call.id,'スキップ',card); btns.append(bNo);
  }
  card.appendChild(btns); aiScroll();
}

function openDiff(title, original, modified, applyFn){
  document.getElementById('difftitle').textContent='差分: '+title;
  document.getElementById('diffwrap').classList.add('show');
  if(!diffEditor){ diffEditor=monaco.editor.createDiffEditor(document.getElementById('diffed'),{theme:'vs-dark',automaticLayout:true,readOnly:true}); }
  const lang=langOf(title);
  diffEditor.setModel({ original: monaco.editor.createModel(original, lang), modified: monaco.editor.createModel(modified, lang) });
  diffApplyFn=applyFn;
  document.getElementById('diffapply').onclick=()=>{ if(diffApplyFn) diffApplyFn(); };
}
function closeDiff(){ document.getElementById('diffwrap').classList.remove('show'); }

// Ctrl+Enter で送信
document.getElementById('aiin').addEventListener('keydown', e=>{
  if((e.ctrlKey||e.metaKey)&&e.key==='Enter'){ e.preventDefault(); sendAI(); }
});
</script>
</body>
</html>
