# mujiu-gui

> https://gui.mujiu.net/ · 图形化个人主页

单文件 HTML（内联 CSS + JS），首页零依赖、零构建，配一张头像即可运行；
唯一的服务端代码是仪表盘接口 `api/stats.php`。

## 页面结构

- **顶栏** — 品牌标识 + 6 套主题色点
- **Hero** — 姓名、身份、简介、标签芯片、CTA 按钮，配手绘头像（`avatar.jpg`）
- **教育背景** — 天津大学（TJU）& 香港理工大学（PolyU）· 计算机科学与技术
- **这个站怎么搭的** — 只描述站点与服务器的实际构成（前端 / 后端 / 部署），不写个人能力清单
- **作品 / 项目** — 指向 mujiu.net / ask.mujiu.net / game.mujiu.net 三个子站
- **更新日志** — 时间线，最早的记录来自服务器访问日志（2026-08-26 起）
- **服务器仪表盘** — 实时 CPU / 内存 / 磁盘 / 网络 / 在线访客
- **社交账号** — GitHub
- **联系** — inbox@mujiu.net

## 主题系统

`THEMES` 对象定义了 6 套配色：

| 名称 | 说明 |
| --- | --- |
| `dark` | 默认，沿用主站色值 `#1D2A35` / `#05CE91` / `#FF9D00` |
| `light` | 浅色 |
| `blue-matrix` | 黑底荧光绿 |
| `espresso` | 暖褐 |
| `green-goblin` | 高对比黑黄绿 |
| `ubuntu` | Ubuntu 紫 |

每套含 `body / primary / secondary / t100 / t200 / t300 / panel` 色值，通过 CSS 变量切换，
顶部色点由 JS 自动生成。

选择结果写进 `mujiu_theme` cookie（`domain=.mujiu.net`，一年有效期），所以主站与
game / ask / gui 之间互相跟随；`localStorage` 只作回退和旧数据迁移。切回本页或重新聚焦时
会重读 cookie 同步一次。

**新增主题**：在 `THEMES` 里加一项即可，色点会自动出现（另外三个站要同步补色值）。

## 服务器仪表盘（`api/stats.php`）

页面底部每 5 秒请求一次 `api/stats.php`，页面切到后台会暂停轮询。

| 指标 | 来源 |
| --- | --- |
| CPU / 负载 | `/proc/stat`（两次采样求差）+ `sys_getloadavg()` |
| 内存 | `/proc/meminfo`（`MemTotal - MemAvailable`） |
| 磁盘 | `disk_total_space('/')` / `disk_free_space('/')` |
| 上行 / 下行 | `/proc/net/dev` 各网卡累计字节求差（排除 `lo`） |
| 运行时长 | `/proc/uptime` |
| 在线访客 / 每分钟请求 | `/var/log/nginx/access.log` 尾部 256KB，统计最近 60 秒 |

约定：

- **只输出聚合数字** —— 不含 IP、请求路径、主机名等任何可识别信息。
- 采集结果缓存 4 秒（`/var/lib/mujiu-stats/snapshot.json`），这份快照同时是下次算速率的基数；
  多人同时开着页面也只会每分钟读十几次日志尾部。距上次采集超过 60 秒时现场补采 250ms，
  免得把速率平均成一个没意义的数。
- 「在线访客」= 最近 60 秒内发起过请求的不同访客 IP。站点在 Cloudflare 后面，日志里的
  `$remote_addr` 只是 CF 节点 IP，真实访客取自 `CF-Connecting-IP` 头（服务器 `nginx.conf`
  的 `log_format mujiu`，2026-09-24 起才有该字段，解析时兼容旧格式）。
- 服务器上需要 `/var/lib/mujiu-stats` 目录（属主 `www-data`）存快照。
- nginx 只放行 `^/api/stats\.php$` 这一个 PHP 路径（见 `mujiu-server/nginx/gui`），
  站点里其他 `.php` 不会被执行。

## 本地预览

```bash
python3 -m http.server 8000
# 浏览器打开 http://localhost:8000/
```

本地没有 PHP，仪表盘会显示"暂时取不到服务器数据"，其余功能正常。

## 部署

```bash
rsync -rltvz --no-owner --no-group --exclude='.DS_Store' ./ <server>:/var/www/gui/
ssh <server> 'chown -R www-data:www-data /var/www/gui'
```

用 `-rlt` 而不是 `-a`：macOS 上 `-a` 会把 uid 501 带到服务器。nginx 配置以
`mujiu-server/nginx/gui` 为准（本目录 `deploy/nginx.conf` 是早期的旧副本）。
`api/stats.php` 随站点一起发布，属主同样是 `www-data`。

## 可优化项

- 字体目前从 Google Fonts CDN 加载，国内访问可能偏慢。主站 mujiu.net 已改用
  `@fontsource` 本地自托管，可按同样方式优化。
- 头像 `avatar.jpg` 未做压缩/WebP 处理，可进一步瘦身。
- 仪表盘接口目前没有频率限制，公开可读（只有聚合数字）。

## License

个人项目，代码随意参考。
