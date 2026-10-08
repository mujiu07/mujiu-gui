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

## 本地预览

```bash
python3 -m http.server 8000
# 浏览器打开 http://localhost:8000/
```

本地没有 PHP，仪表盘会显示"暂时取不到服务器数据"，其余功能正常。

## 可优化项

- 字体目前从 Google Fonts CDN 加载，国内访问可能偏慢。主站 mujiu.net 已改用
  `@fontsource` 本地自托管，可按同样方式优化。
- 头像 `avatar.jpg` 未做压缩/WebP 处理，可进一步瘦身。

## License

个人项目，代码随意参考。
