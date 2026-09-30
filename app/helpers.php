<?php
declare(strict_types=1);
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $route, array $params = []): string { return '?' . http_build_query(['r' => $route] + $params); }
function redirect(string $route, array $params = []): void { header('Location: ' . url($route, $params)); exit; }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): void {
    if (!current_user()) redirect('login');
    $q=db()->prepare('SELECT id,username,display_name,role,is_active FROM users WHERE id=?');$q->execute([current_user()['id']]);$user=$q->fetch();
    if(!$user||(int)$user['is_active']!==1){$_SESSION=[];session_regenerate_id(true);redirect('login');}
    $_SESSION['user']=$user;
}
function require_role(array $roles): void { require_login(); if (!in_array(current_user()['role'], $roles, true)) { http_response_code(403); exit('沒有執行此操作的權限。'); } }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e($_SESSION['_csrf'] ??= bin2hex(random_bytes(32))) . '">'; }
function verify_csrf(): void { if (!hash_equals($_SESSION['_csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) { http_response_code(419); exit('表單已過期，請返回後重新操作。'); } }
function flash(string $type, string $message): void { $_SESSION['_flash'] = [$type, $message]; }
function take_flash(): ?array { $f = $_SESSION['_flash'] ?? null; unset($_SESSION['_flash']); return $f; }
function audit(string $action, string $entity, ?int $id, array $details = []): void {
    $q = db()->prepare('INSERT INTO audit_logs (actor_id,action,entity_type,entity_id,details,ip_address) VALUES (?,?,?,?,?,?)');
    $q->execute([current_user()['id'] ?? null, $action, $entity, $id, $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null, $_SERVER['REMOTE_ADDR'] ?? null]);
}
function money(mixed $n): string { static $decimals=null; if($decimals===null){try{$value=db()->query('SELECT amount_decimals FROM company_settings WHERE id=1')->fetchColumn();$decimals=$value===false?2:(int)$value;}catch(Throwable){$decimals=2;}} return number_format((float)$n, $decimals, '.', ','); }
function amount_cents(string $amount): int { [$whole,$fraction]=array_pad(explode('.', $amount, 2),2,''); return ((int)$whole*100)+(int)str_pad($fraction,2,'0'); }
function normalize_amount(string $amount): string { [$whole,$fraction]=array_pad(explode('.', $amount, 2),2,''); $whole=ltrim($whole,'0')?:'0';$fraction=rtrim($fraction,'0');return $whole.'.'.str_pad(substr($fraction,0,2),2,'0'); }
function role_label(string $r): string { return ['admin'=>'管理者','bookkeeper'=>'記帳人員','approver'=>'審核人員','viewer'=>'唯讀'][$r] ?? $r; }
function status_label(string $s): string { return ['draft'=>'草稿','pending'=>'待審核','approved'=>'已核准','rejected'=>'已退回','posted'=>'已過帳','void'=>'已作廢'][$s] ?? $s; }
function account_type_label(string $s): string { return ['asset'=>'資產','liability'=>'負債','equity'=>'權益','revenue'=>'收入','expense'=>'費用'][$s] ?? $s; }
function layout(string $title, string $body): void {
    $user = current_user(); $flash = take_flash(); $app = e($_ENV['APP_NAME'] ?? '會計帳務基本版');
    echo '<!doctype html><html lang="zh-Hant"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . e($title) . ' - ' . $app . '</title><link rel="stylesheet" href="assets/style.css"></head><body>';
    if ($user) {
        echo '<header class="top"><a class="brand" href="' . url('dashboard') . '">' . $app . '</a><div>' . e($user['display_name']) . ' <span class="muted">' . e(role_label($user['role'])) . '</span> <a class="toplink" href="' . url('password') . '">變更密碼</a> <form class="inline" method="post" action="' . url('logout') . '">' . csrf_field() . '<button class="link">登出</button></form></div></header>';
        echo '<div class="shell"><nav><a href="' . url('dashboard') . '">總覽</a><a href="' . url('vouchers') . '">傳票</a><a href="' . url('accounts') . '">會計科目</a><a href="' . url('periods') . '">會計期間</a><a href="' . url('reports') . '">財務報表</a>';
        if (in_array($user['role'], ['admin','bookkeeper'], true)) { try { if ((int)db()->query('SELECT enabled FROM ai_settings WHERE id=1')->fetchColumn() === 1) echo '<a href="' . url('ai_intake') . '">AI 語音／照片入帳</a>'; } catch (Throwable) {} }
        if (in_array($user['role'], ['admin','bookkeeper'], true)) echo '<a href="' . url('users') . '">使用者</a>';
        if ($user['role'] === 'admin') echo '<a href="' . url('settings') . '">組織設定</a><a href="' . url('audit') . '">操作紀錄</a>';
        echo '</nav><main>';
    } else echo '<main class="login-main">';
    echo '<h1>' . e($title) . '</h1>';
    if ($flash) echo '<div class="alert ' . e($flash[0]) . '">' . e($flash[1]) . '</div>';
    echo $body . '</main></div></body></html>';
}
function post_only(): void { if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); } verify_csrf(); }
function posted_lines(array $input): array {
    $lines = []; $accounts = array_map('intval', $input['account_id'] ?? []); $debits = $input['debit'] ?? []; $credits = $input['credit'] ?? []; $descs = $input['line_description'] ?? [];
    static $precision=null; if($precision===null){$value=db()->query('SELECT amount_decimals FROM company_settings WHERE id=1')->fetchColumn();$precision=$value===false?2:(int)$value;}
    foreach ($accounts as $i => $accountId) {
        $debitText = trim((string)($debits[$i] ?? '')); $creditText = trim((string)($credits[$i] ?? ''));
        if ($accountId === 0 && $debitText === '' && $creditText === '') continue;
        if($debitText==='')$debitText='0'; if($creditText==='')$creditText='0';
        $amountPattern='/^\d{1,16}(?:\.\d{1,18})?$/';
        $debitFraction=str_contains($debitText,'.')?rtrim(explode('.',$debitText,2)[1],'0'):'';
        $creditFraction=str_contains($creditText,'.')?rtrim(explode('.',$creditText,2)[1],'0'):'';
        if (!preg_match($amountPattern, $debitText) || !preg_match($amountPattern, $creditText) || strlen($debitFraction)>$precision || strlen($creditFraction)>$precision) throw new RuntimeException('金額格式錯誤，最多可輸入 '.$precision.' 位非零小數。');
        $debit = normalize_amount($debitText); $credit = normalize_amount($creditText);
        if (($debit > 0) === ($credit > 0)) throw new RuntimeException('每筆分錄須且只能填入借方或貸方其中一側金額。');
        $lines[] = ['account_id'=>$accountId,'description'=>trim((string)($descs[$i] ?? '')),'debit'=>$debit,'credit'=>$credit];
    }
    if (count($lines) < 2) throw new RuntimeException('一張傳票至少需要兩筆分錄。');
    $accountsById = [];
    $ids = array_values(array_unique(array_column($lines, 'account_id')));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $q = db()->prepare("SELECT id FROM accounts WHERE id IN ($placeholders) AND is_active=1 AND is_postable=1"); $q->execute($ids);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) $accountsById[(int)$id] = true;
    foreach ($lines as $line) if (!isset($accountsById[$line['account_id']])) throw new RuntimeException('分錄含有無效、停用或不可過帳的會計科目。');
    $debitTotal = array_sum(array_map(fn($line)=>amount_cents($line['debit']),$lines)); $creditTotal = array_sum(array_map(fn($line)=>amount_cents($line['credit']),$lines));
    if ($debitTotal <= 0 || $debitTotal !== $creditTotal) throw new RuntimeException('借貸合計必須相等且大於零。');
    return $lines;
}
function replace_lines(int $voucherId, array $lines): void {
    $del = db()->prepare('DELETE FROM voucher_lines WHERE voucher_id=?'); $del->execute([$voucherId]);
    $q = db()->prepare('INSERT INTO voucher_lines (voucher_id,line_no,account_id,description,debit,credit) VALUES (?,?,?,?,?,?)');
    foreach ($lines as $i=>$line) $q->execute([$voucherId,$i+1,$line['account_id'],$line['description'],$line['debit'],$line['credit']]);
}
