# public/site — 产品宣传页

随面板一起发布的静态宣传页，访问 `https://<面板域名>/site/`。

- 源文件在独立目录/仓库 `hysteria-site`（`index.html` + `assets/`），整目录同步过来；
- 首页 `index.html` 有「服务商列表」区块（`#providers`），运行时从 `GET /api/public/providers` 拉取
  （`providers.listed=true` 且状态正常的服务商，60s 缓存）；新增/下架在平台后台「服务商」里控制
- Laravel 只在 `routes/web.php` 兜底 `/site`、`/site/` 两个入口，静态资源由 nginx 直接出；
- 改完直接生效，不需要 `artisan` 命令；nginx 片段见项目 README「3.10 宣传站」。

```bash
# 从独立仓库同步（示例；不要覆盖本文件）
rsync -a ../hysteria-site/index.html ../hysteria-site/assets public/site/
```

## 内容口径

- 只写产品真实能力（安装脚本、两级后台、租户隔离、采集器 API、独立鉴权），不编客户数、不编价格。
- 代码块里的命令/输出来自真实部署：`/api/install.sh`、`/auth`、`/join/{code}`。
- 「个人 / 公司」两栏分开写：个人讲省事，公司讲隔离与可控。

## 改内容注意

- 换环境时全站替换 `hysteria.ipx.sarl` 与 `auth.ipx.sarl`。
- 导航锚点 `#scenes`、`#capabilities`、`#architecture`、`#quickstart`、`#faq`，改 id 要同步。
- 主色在 `assets/styles.css` 的 `--acc`。
