<?php
/**
 * PULSESTREAM — tiny analytics dashboard.
 * Admin only - requires password authentication.
 */
require_once __DIR__ . '/includes/db.php';

// ---- Admin Authentication Check ----
session_start();
$isAdmin = isset($_SESSION['ps_admin']) && $_SESSION['ps_admin'] === true;
$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
    if ($_POST['admin_password'] === ADMIN_PASSWORD) {
        $_SESSION['ps_admin'] = true;
        $isAdmin = true;
    } else {
        $loginError = 'Incorrect password';
    }
}

// Redirect if not authenticated
if (!$isAdmin) {
    // Show login form
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>PULSESTREAM — Analytics Login</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
  .login-container { max-width: 400px; margin: 80px auto; padding: 40px; }
  .login-form { display: flex; flex-direction: column; gap: 16px; }
  .login-form input { padding: 12px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.05); color: #fff; border-radius: 8px; font-size: 14px; }
  .login-form input::placeholder { color: rgba(255,255,255,0.5); }
  .login-error { color: #ff6b6b; font-size: 13px; }
</style>
</head>
<body class="grid-bg">
<div class="glow glow-a"></div>
<div class="login-container">
  <a class="pill" href="index.php">&larr; Back to app</a>
  <h1 class="metallic" style="font-size: 32px; margin: 24px 0 8px;">Analytics</h1>
  <p class="muted mono-badge">Admin access required</p>
  
  <form class="login-form" style="margin-top: 32px;" method="POST">
    <input type="password" name="admin_password" placeholder="Enter admin password" autofocus required>
    <button class="btn btn-primary" type="submit" style="width: 100%;">Unlock Analytics</button>
    <?php if ($loginError): ?><p class="login-error"><?= htmlspecialchars($loginError) ?></p><?php endif; ?>
  </form>
</div>
</body>
</html>
    <?php
    exit;
}

$pdo = ps_db();
$overview = ['total_visits' => 0, 'unique_visitors' => 0, 'total_attempts' => 0, 'total_downloads' => 0, 'failed_attempts' => 0];
$platforms = $recent = $days = [];
$dbError = null;

if ($pdo) {
    try {
        $overview  = $pdo->query('SELECT * FROM v_traffic_overview')->fetch() ?: $overview;
        $platforms = $pdo->query('SELECT * FROM v_platform_breakdown')->fetchAll();
        $days      = $pdo->query('SELECT * FROM v_last_30_days')->fetchAll();
        $recent    = $pdo->query('SELECT created_at, platform, action, status, title, format, quality FROM download_attempts ORDER BY id DESC LIMIT 40')->fetchAll();
    } catch (Throwable $e) {
        $dbError = 'Import sql/pulsestream.sql in phpMyAdmin first.';
    }
} else {
    $dbError = 'MySQL is not running or credentials in config.php are wrong.';
}
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>PULSESTREAM — Analytics</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="grid-bg">
<div class="glow glow-a"></div>
<main class="shell" style="padding-top:48px">
  <a class="pill" href="index.php">&larr; Back to app</a>
  <h1 class="metallic" style="font-size:clamp(28px,5vw,44px);margin:18px 0 4px">Analytics</h1>
  <p class="muted mono-badge">VISITOR &amp; EXTRACTION TELEMETRY</p>

  <?php if ($dbError): ?><div class="alert" style="margin-top:20px"><?= $e($dbError) ?></div><?php endif; ?>

  <section class="stat-grid" style="margin-top:24px">
    <?php foreach ([
      'Total visits' => $overview['total_visits'],
      'Unique users' => $overview['unique_visitors'],
      'Attempts' => $overview['total_attempts'],
      'Downloads' => $overview['total_downloads'],
      'Failures' => $overview['failed_attempts'],
    ] as $label => $val): ?>
      <div class="glass stat"><span class="stat-num"><?= number_format((int) $val) ?></span><span class="mono-badge"><?= $e(strtoupper($label)) ?></span></div>
    <?php endforeach; ?>
  </section>

  <section class="glass card" style="margin-top:22px">
    <h2 class="card-title">Platform breakdown</h2>
    <div class="table-wrap"><table>
      <thead><tr><th>Platform</th><th>Attempts</th><th>Downloads</th><th>Users</th><th>Errors</th></tr></thead>
      <tbody>
      <?php foreach ($platforms as $r): ?>
        <tr><td><span class="tag tag-<?= $e($r['platform']) ?>"><?= $e(strtoupper($r['platform'])) ?></span></td>
        <td><?= (int) $r['attempts'] ?></td><td><?= (int) $r['downloads'] ?></td>
        <td><?= (int) $r['users'] ?></td><td><?= (int) $r['errors'] ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$platforms): ?><tr><td colspan="5" class="muted">No data yet.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>

  <section class="glass card" style="margin-top:22px">
    <h2 class="card-title">Last 30 days</h2>
    <div class="table-wrap"><table>
      <thead><tr><th>Date</th><th>Visits</th><th>Fetches</th><th>Downloads</th></tr></thead>
      <tbody>
      <?php foreach ($days as $r): ?>
        <tr><td class="mono-badge"><?= $e($r['stat_date']) ?></td><td><?= (int) $r['visits'] ?></td><td><?= (int) $r['fetches'] ?></td><td><?= (int) $r['downloads'] ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$days): ?><tr><td colspan="4" class="muted">No data yet.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>

  <section class="glass card" style="margin:22px 0 60px">
    <h2 class="card-title">Recent activity</h2>
    <div class="table-wrap"><table>
      <thead><tr><th>When</th><th>Platform</th><th>Action</th><th>Media</th><th>Format</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td class="mono-badge"><?= $e($r['created_at']) ?></td>
          <td><span class="tag tag-<?= $e($r['platform']) ?>"><?= $e(strtoupper($r['platform'])) ?></span></td>
          <td><?= $e($r['action']) ?></td>
          <td class="ellipsis"><?= $e($r['title'] ?: '—') ?></td>
          <td class="mono-badge"><?= $e(strtoupper((string) $r['format'])) ?> <?= $e($r['quality']) ?></td>
          <td class="<?= $r['status'] === 'error' ? 'bad' : 'good' ?>"><?= $e($r['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$recent): ?><tr><td colspan="6" class="muted">No data yet.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>
</main>
</body>
</html>
