# mujiu-gui

> https://gui.mujiu.net/ · 图形化个人主页

单文件 HTML（内联 CSS + JS），零依赖、零构建，配一张头像即可运行。

## 页面结构

- **顶栏** — 品牌标识 + 6 套主题切换色点
- **Hero** — 姓名、身份、简介、标签芯片、CTA 按钮，配手绘头像（`avatar.jpg`）
- **教育背景** — 天津大学 & 深圳理工大学 · 计算机科学与技术
- **作品 / 项目** — 指向 mujiu.net / ask.mujiu.net / game.mujiu.net 三个子站
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
顶部色点由 JS 自动生成，选择结果存进 `localStorage.mujiu_theme`。

**新增主题**：在 `THEMES` 里加一项即可，色点会自动出现。

## 本地预览

```bash
python3 -m http.server 8000
# 浏览器打开 http://localhost:8000/
```

## 部署

```bash
rsync -avz --exclude='.DS_Store' ./ <server>:/var/www/gui/
```

nginx 配置见 `deploy/nginx.conf`（站点根 `/var/www/gui`）。

## 可优化项

- 字体目前从 Google Fonts CDN 加载，国内访问可能偏慢。主站 mujiu.net 已改用
  `@fontsource` 本地自托管，可按同样方式优化。
- 头像 `avatar.jpg` 未做压缩/WebP 处理，可进一步瘦身。

## License

个人项目，代码随意参考。
