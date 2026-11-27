# 店铺访问计数（2026-11-27）

商品详情页的访问统一通过 `ShopViewCounter::recordVisit()` 计数。

- 已识别的爬虫、抓取工具、无 User-Agent、非浏览器客户端、HEAD、AJAX、JSON、预加载及非页面导航请求不计数。商品页仍正常响应。
- 同一访客在同一店铺的一个自然日最多计一次；刷新、增加查询参数、浏览该店铺其他普通或数字商品都不会重复增加。自然日沿用 `config('app.timezone')`，与现有定时统计一致。
- 登录访客按账号去重，匿名访客按浏览器会话去重。当前登录的店主访问自己的商品不计数。
- 使用独立的 `shop_views` 文件缓存，Laravel 的原子 `add()` 通过文件锁处理多个 PHP 进程同时访问。普通商品/页面缓存清理以及本地默认 array 缓存不影响去重记录。
- 访问计数与定时同步共用店铺行锁和当天初始化逻辑，避免午夜首个访问被后续同步清掉。统计故障时跳过计数并记录错误，商品页继续展示。

现有每日递增、基础量和历史浏览量不在本次自动清理范围内。过滤主要依据请求特征和 User-Agent，不保证识别伪装成正常浏览器的爬虫；匿名访客清除会话或更换浏览器后会作为新访客。当前文件缓存由应用和调度器共享挂载，适用于现有单主机部署；扩展至多个独立应用主机时需要共享的原子缓存。

验证命令：

```sh
php vendor/phpunit/phpunit/phpunit --filter 'ShopVisitTest|ShopViewCounterTest|SellerDashboardPerformanceTest|AdminDashboardPerformanceTest|SellerHomeEntryTest|MalaysiaMarketTest|SellerWalletDisplayTest'
```

结果：111 项测试、422 条断言通过，包括 6 个独立 PHP 进程同时计数只增加一次、真实商品控制器、爬虫过滤、重复访问、跨日、店主自访及缓存故障。

## 线上发布与验收

已于 2026-11-27 发布，成功编号 `20261127-211139-7a7d031b`。仅同步 `HomeController.php`、`ShopViewCounter.php` 和 `config/cache.php`，无数据库迁移。备份位于 `/opt/kuajing/backups/publish-20261127-211139-7a7d031b/`，完整数据库压缩备份已校验，`SUCCESS` 标记已核对，4 个服务容器均正常运行。发布日志保存在工作区 `output/deploy/20261127-211139-7a7d031b/publish.log`。

首次发布 `20261127-210808-de2943d7` 在暂停服务后因 SSH 断开而中断，源码尚未替换；已先恢复 Web 和调度器，再使用相同校验通过的发布包在独立后台进程中完成发布。恢复与重试均核对过进程及源码状态。

生产 `www-data` 用户专项检查通过：实际访问缓存目录可写、原子重复领取被拒绝、爬虫过滤、正常访问、刷新去重和定时同步。该检查使用独立 SQLite 测试库，不改商家数据。七个主要访问入口、防缓存响应头及核心静态资源校验通过。

真实 HTTP 验收：对 Siok Supply（店铺 ID 112）的公开商品连续发送两次含 Googlebot 标识的 GET 请求和一次 HEAD 请求，三次均返回 HTTP 200，数据库浏览量保持 `4827 → 4827`。原始结果位于工作区 `output/verify-shop-bot-http.json`。
