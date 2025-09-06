<?php
$proxyHost = getenv('WG_CONTAINER') ?: 'wireguard';
$proxyPort = getenv('WG_PORT')      ?: '51822';
$cacheTTL  = (int) ($_ENV['WG_CACHE_TTL'] ?? 2);
$cacheKey  = 'wg-proxy-cache';

function fmt($n) {
    $s = new NumberFormatter('en', NumberFormatter::DECIMAL);
    $s->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 1);
    foreach (['','K','M','G','T'] as $u) {
        if ($n < 1024) return $s->format($n).$u;
        $n /= 1024;
    }
    return $s->format($n).'P';
}

function dur($s) {
    if ($s == 0) return 'N/A';
    if ($s < 60) return floor($s).'s';
    if ($s < 3600) return floor($s/60).'m';
    if ($s < 86400) return floor($s/3600).'h';
    return floor($s/86400).'d';
}

function sec($now, $then) {
    return (!empty($then) && $then > 0) ? ($now - $then) : 0;
}

function getWireGuard(): array {
    global $proxyHost, $proxyPort, $cacheKey, $cacheTTL;
    if (extension_loaded('apcu') && ($wg = apcu_fetch($cacheKey, $hit)) !== false && $hit) return $wg;
    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
    $json = @file_get_contents("http://{$proxyHost}:{$proxyPort}", false, $ctx);
    if ($json === false || !is_array($wg = json_decode($json, true))) {
        http_response_code(503);
        die('wg-proxy unreachable');
    }
    if (extension_loaded('apcu')) apcu_store($cacheKey, $wg, $cacheTTL);
    return $wg;
}

function buildTable(): array {
    $wg = getWireguard();
    $active = $totalRx = $totalTx = 0;
    $now = new DateTimeImmutable();
    $rows = '';
    foreach ($wg as $iface => $d) {
        foreach ($d['peers'] as $p) {
            $s = sec($now->getTimestamp(), $p['latest_handshake']);
            $up = $s > 0 && $s < 300;
            if ($up) { $active++; $totalRx += $p['rx']; $totalTx += $p['tx']; }
            $rows .= '<tr>
              <td>'.htmlspecialchars($iface).'</td>
              <td>'.htmlspecialchars($p['peer_name'] ?: 'Unnamed').'</td>
              <td>'.htmlspecialchars(substr($p['public_key'],0,16)).'…</td>
              <td>'.htmlspecialchars($p['endpoint'] ?: '—').'</td>
              <td><span class="'.($up?'status-active':'status-idle').'">'.($up?'Active':'Idle').'</span><span class="last-handshake">('.dur($s).')</span></td>
              <td>'.fmt($p['rx']).'</td>
              <td>'.fmt($p['tx']).'</td>
            </tr>';
        }
    }
    return ['active'=>$active,'rx'=>fmt($totalRx),'tx'=>fmt($totalTx),'updated'=>$now->format('r'), 'rows'=>$rows];
}

if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/event-stream') !== false) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    while (true) {
        $data = buildTable();
        echo "event: update\n";
        echo 'data: '.json_encode($data)."\n\n";
        ob_flush();
        flush();
        sleep(5);
    }
    exit;
}

$init = buildTable();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>WG Simple Dash</title>
<style>
    :root {
    --bg: #121212;
    --bg2: #1e1e1e;
    --bg-hover: #252525;
    --fg: #f5f5f5;
    --fg2: #a0a0a0;
    --accent: #5667f7;
    --green: #4caf50;
    --red: #f44336;
    --border: #333;
    --radius: 0.75rem;
    --font-mono: "SFMono", "Cascadia Code", "Consolas", monospace;
    font-size: 16px;
    font-family: var(--font-mono);
    }
    *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: var(--bg); color: var(--fg); padding: 1.5rem; max-width: 1400px; margin: auto; line-height: 1.5; }
    header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; flex-wrap: wrap; color: var(--accent); }
    h1 { font-size: 2rem; font-weight: 700; letter-spacing: 0.5px; }
    .subtitle { color: var(--fg2); font-size: 0.9rem; }
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .card { background: var(--bg2); border-radius: var(--radius); padding: 1rem 1.25rem; border-left: 4px solid var(--accent); box-shadow: 0 4px 8px rgba(0,0,0,.25); transition: transform .2s ease; }
    .card:hover { transform: translateY(-2px); }
    .label { font-size: .8rem; color: var(--fg2); text-transform: uppercase; letter-spacing: .5px; }
    .value { display: block; font-size: 1.75rem; font-weight: 700; margin-top: .25rem; font-variant-numeric: tabular-nums; font-family: var(--font-mono); }
    table { width: 100%; border-collapse: collapse; background: var(--bg2); border-radius: var(--radius); overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,.25); }
    thead { background: var(--bg-hover); color: var(--accent); }
    th,td { padding: .75rem 1rem; text-align: left; white-space: nowrap; font-variant-numeric: tabular-nums; overflow: hidden; text-overflow: ellipsis; }
    tr { display: grid; grid-template-columns: minmax(3rem,.5fr) minmax(5rem,1fr) minmax(0,1fr) minmax(3rem,1fr) minmax(5rem,.5fr) minmax(5rem,.5fr) minmax(5rem,.5fr); }
    tbody tr:nth-child(even) { background: rgba(255,255,255,.03); }
    tbody tr:hover { background: var(--border); }
    td { font-size: .9rem; }
    .status-active { color: #4CBB17; }
    .status-idle { color: #fff; }
    .last-handshake { font-size: .7rem; margin-left: .3rem; }
    .first-update { text-align: left; }
    .last-update { text-align: right; }
    footer { margin-top: 2rem; display: flex; justify-content: space-between; color: var(--accent); font-size: .8rem; }
    @media (max-width: 600px) {
    body { padding: 1rem; }
    header { flex-direction: column; align-items: flex-start; }
    h1 { font-size: 1.5rem; }
    th,td { padding: .5rem; font-size: .8rem; }
    footer { flex-direction: column; gap: .25rem; }
    }
</style>
</head>
<body>
<header>
  <div>
    <h1>WG Simple Dash</h1>
    <span class="subtitle">Secure tunnel monitoring</span>
  </div>
</header>
<section class="stats">
  <div class="card">
    <span class="label">Active Connections</span>
    <span id="active" class="value status-idle"><?= $init['active'] ?></span>
  </div>
  <div class="card">
    <span class="label">Received</span>
    <span id="rx" class="value"><?= $init['rx'] ?></span>
  </div>
  <div class="card">
    <span class="label">Sent</span>
    <span id="tx" class="value"><?= $init['tx'] ?></span>
  </div>
</section>
<table>
  <thead>
    <tr>
      <th>Interface</th>
      <th>Peer Name</th>
      <th>Public Key</th>
      <th>Endpoint</th>
      <th>Status</th>
      <th>Received</th>
      <th>Sent</th>
    </tr>
  </thead>
  <tbody id="tbody"><?= $init['rows'] ?></tbody>
</table>
<footer>
  <span id="first-update">Monitoring since <?= date('r'); ?></span>
  <span id="last-update">Updated at <?= date('r'); ?></span>
</footer>
<script>
const source = new EventSource('<?= $_SERVER['PHP_SELF'] ?>');
source.addEventListener('update', e => {
  const d = JSON.parse(e.data);
  document.getElementById('active').textContent = d.active;
  document.getElementById('rx').textContent = d.rx;
  document.getElementById('tx').textContent = d.tx;
  document.getElementById('tbody').innerHTML = d.rows;
  document.getElementById('last-update').textContent = 'Updated at ' + d.updated;
});
</script>
</body>
</html>