A complete solution for E-commerce Business with exclusive features & super responsive layout

### 自动释放冻结资金配置

在服务器添加计划任务

```shell
* * * * * cd 以/开始的项目绝对路径 && php可执行文件以/开始的绝对路径 artisan schedule:run >> /dev/null 2>&1
```

示例

```shell
* * * * * cd /www/wwwroot/cgwl.pro && 
/www/server/php/74/bin/php artisan schedule:run >> /dev/null 2>&1
```


```访问量
https://www.cgwl.shop/viewcron
