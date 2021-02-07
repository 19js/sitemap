# 获取站点地图

> 生成百度站点地图

## 示例
```bash
  php index.php
```
```php 
namespace Tom\Sitemap;
require_once "./vendor/autoload.php";
   /**
     * @param $url 网站首页地址
     * @param $dir sitemap.xml存储文件夹
     * @param int $type 0:响应式 1：wap 2: 自适应 3：pc
     */
Sitemap::handle('https://www.19js.club','./');
```

## LICENCE
MIT