<?php
/**
 * gui.mujiu.net 服务器仪表盘接口 —— 只输出聚合数字
 *
 * 数据源：
 *   /proc/stat、/proc/meminfo、/proc/net/dev、/proc/uptime  → CPU / 内存 / 网络 / 运行时长
 *   disk_total_space('/') / disk_free_space('/')            → 磁盘
 *   /var/log/nginx/access.log（尾部 256KB）                  → 近 60 秒请求数与访客数
 *
 * 隐私：不输出 IP、请求路径、主机名、进程名等任何可识别信息，只有聚合后的数字。
 * 开销：采集结果缓存 4 秒，被多人同时轮询时也只会每分钟读十几次日志尾部。
 *
 * 部署：/var/www/gui/api/stats.php（需要 /var/lib/mujiu-stats 目录，属主 www-data）
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');

const SNAPSHOT   = '/var/lib/mujiu-stats/snapshot.json';
const TTL        = 4.0;        // 秒：快照新鲜度（前端每 5 秒轮询一次）
const LOG_FILE   = '/var/log/nginx/access.log';
const TAIL_BYTES = 262144;     // 只读日志尾部 256KB（足够覆盖最近 60 秒）
const WINDOW     = 60;         // 在线口径：最近多少秒内发起过请求

function read_json(string $file): ?array
{
    if (!is_readable($file)) {
        return null;
    }
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);

    return is_array($data) ? $data : null;
}

/** CPU 与网卡的累计计数（用于和上一次采集做差算速率） */
function counters(): array
{
    $cpuTotal = null;
    $cpuIdle  = null;

    $stat = @file('/proc/stat');
    if (is_array($stat) && isset($stat[0]) && str_starts_with($stat[0], 'cpu ')) {
        $fields = preg_split('/\s+/', trim($stat[0])) ?: [];
        $nums   = array_map('floatval', array_slice($fields, 1)); // user nice system idle iowait irq softirq steal
        if (count($nums) >= 4) {
            $cpuIdle  = $nums[3] + ($nums[4] ?? 0.0);          // idle + iowait
            $cpuTotal = array_sum(array_slice($nums, 0, 8));    // 到 steal 为止（guest 会重复计入 user）
        }
    }

    $rx = 0.0;
    $tx = 0.0;
    $dev = @file('/proc/net/dev');
    if (is_array($dev)) {
        foreach ($dev as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$iface, $rest] = explode(':', $line, 2);
            if (trim($iface) === 'lo') {
                continue;
            }
            $f = preg_split('/\s+/', trim($rest)) ?: [];
            if (count($f) >= 9) {
                $rx += (float) $f[0];
                $tx += (float) $f[8];
            }
        }
    }

    return ['t' => microtime(true), 'cpu_total' => $cpuTotal, 'cpu_idle' => $cpuIdle, 'rx' => $rx, 'tx' => $tx];
}

function memory(): array
{
    $total = $avail = null;
    $info  = @file('/proc/meminfo');
    if (is_array($info)) {
        foreach ($info as $line) {
            if (str_starts_with($line, 'MemTotal:')) {
                $total = (float) filter_var($line, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            } elseif (str_starts_with($line, 'MemAvailable:')) {
                $avail = (float) filter_var($line, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            }
        }
    }
    if (!$total || $avail === null) {
        return ['pct' => null, 'used_mb' => null, 'total_mb' => null];
    }
    $usedMb = ($total - $avail) / 1024;

    return [
        'pct'      => round(($total - $avail) / $total * 100, 1),
        'used_mb'  => round($usedMb),
        'total_mb' => round($total / 1024),
    ];
}

function disk(): array
{
    $total = @disk_total_space('/');
    $free  = @disk_free_space('/');
    if (!$total || $free === false) {
        return ['pct' => null, 'used_gb' => null, 'total_gb' => null];
    }

    return [
        'pct'      => round(($total - $free) / $total * 100, 1),
        'used_gb'  => round(($total - $free) / 1073741824, 1),
        'total_gb' => round($total / 1073741824, 1),
    ];
}

/** 近 WINDOW 秒的请求数与不同访客数（cfip 优先，回退 remote_addr） */
function recent(): array
{
    $handle = @fopen(LOG_FILE, 'rb');
    if (!$handle) {
        return ['requests' => null, 'visitors' => null];
    }
    $size  = (int) @filesize(LOG_FILE);
    $start = max(0, $size - TAIL_BYTES);
    fseek($handle, $start);
    $buf = (string) fread($handle, max(0, $size - $start));
    fclose($handle);

    // 从中间截断时丢掉第一行残句
    if ($start > 0) {
        $nl  = strpos($buf, "\n");
        $buf = $nl === false ? '' : substr($buf, $nl + 1);
    }

    $cut      = time() - WINDOW;
    $requests = 0;
    $visitors = [];
    foreach (explode("\n", $buf) as $line) {
        if ($line === '') {
            continue;
        }
        $open = strpos($line, '[');
        if ($open === false) {
            continue;
        }
        $close = strpos($line, ']', $open);
        if ($close === false) {
            continue;
        }
        // 日志格式：[24/Sep/2026:09:24:51 +0000]
        $dt = DateTime::createFromFormat('d/M/Y:H:i:s O', substr($line, $open + 1, $close - $open - 1));
        if ($dt === false) {
            continue;
        }
        if ($dt->getTimestamp() < $cut) {
            continue;
        }
        $requests++;

        $ip = '';
        // nginx 在头不存在时记 "-"，要当成"没有"处理，否则直连请求会全被算成一个叫 "-" 的访客
        if (preg_match('/cfip="([^"]*)"/', $line, $m) && $m[1] !== '' && $m[1] !== '-') {
            $ip = $m[1];                       // Cloudflare 转发的真实访客 IP
        } elseif (preg_match('/^(\S+)/', $line, $m)) {
            $ip = $m[1];                       // 直连或旧格式：remote_addr
        }
        if ($ip !== '') {
            $visitors[$ip] = true;
        }
    }

    return ['requests' => $requests, 'visitors' => count($visitors)];
}

// ---------------------------------------------------------------- 取快照 / 采集

$snapshot = read_json(SNAPSHOT);
$now      = microtime(true);

// 4 秒内的快照直接复用（首次采集 CPU 还没有对比基数时强制重算一次）
if (
    $snapshot !== null
    && isset($snapshot['t'], $snapshot['data']['cpu_pct'])
    && ($now - (float) $snapshot['t']) < TTL
    && $snapshot['data']['cpu_pct'] !== null
) {
    $payload          = $snapshot['data'];
    $payload['cached'] = true;
    $payload['age']    = round($now - (float) $snapshot['t'], 2);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

$c          = counters();
$prev       = $snapshot;
$sampleSpan = $prev !== null ? $c['t'] - (float) $prev['t'] : 0.0;

// 没有可比基数，或距上次采集太久（几分钟以前的差会被平均成没意义的数）
// → 现场补采一次：多花 250ms，换来一个真实的瞬时读数
if ($sampleSpan < 0.2 || $sampleSpan > 60.0) {
    usleep(250000);
    $prev       = $c;
    $c          = counters();
    $sampleSpan = $c['t'] - $prev['t'];
}

$cpuPct = null;
$rxBps  = null;
$txBps  = null;

if ($prev !== null && isset($prev['cpu_total'], $prev['cpu_idle']) && $sampleSpan > 0) {
    $dTotal = ($c['cpu_total'] ?? 0.0) - (float) $prev['cpu_total'];
    $dIdle  = ($c['cpu_idle'] ?? 0.0) - (float) $prev['cpu_idle'];
    if ($dTotal > 0) {
        $cpuPct = max(0.0, min(100.0, (1 - $dIdle / $dTotal) * 100));
    }
    $rxBps = max(0.0, ($c['rx'] - (float) $prev['rx']) / $sampleSpan);
    $txBps = max(0.0, ($c['tx'] - (float) $prev['tx']) / $sampleSpan);
}

$mem     = memory();
$dsk     = disk();
$recent  = recent();
$uptime  = 0.0;
$uptimeRaw = @file_get_contents('/proc/uptime');
if (is_string($uptimeRaw) && $uptimeRaw !== '') {
    $uptime = (float) strtok($uptimeRaw, ' ');
}
$load = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: [null, null, null]) : [null, null, null];

$payload = [
    'ok'           => true,
    'ts'           => time(),
    'cpu_pct'      => $cpuPct === null ? null : round($cpuPct, 1),
    'load1'        => $load[0] === null ? null : round((float) $load[0], 2),
    'mem_pct'      => $mem['pct'],
    'mem_used_mb'  => $mem['used_mb'],
    'mem_total_mb' => $mem['total_mb'],
    'disk_pct'     => $dsk['pct'],
    'disk_used_gb' => $dsk['used_gb'],
    'disk_total_gb' => $dsk['total_gb'],
    'rx_bps'       => $rxBps === null ? null : round($rxBps),
    'tx_bps'       => $txBps === null ? null : round($txBps),
    'uptime_s'     => $uptime > 0 ? (int) $uptime : null,
    'visitors_1m'  => $recent['visitors'],
    'requests_1m'  => $recent['requests'],
    'window_s'     => WINDOW,
    'sample_s'     => round($sampleSpan, 2),
    'cached'       => false,
];

// 落盘：既做指标缓存，也留下下一次算差速率的基数
$dir = dirname(SNAPSHOT);
if (!is_dir($dir)) {
    @mkdir($dir, 0750, true);
}
@file_put_contents(
    SNAPSHOT,
    json_encode($c + ['data' => $payload], JSON_UNESCAPED_SLASHES),
    LOCK_EX
);

echo json_encode($payload, JSON_UNESCAPED_SLASHES);
