[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Issues](https://img.shields.io/github/issues/tekod/mScan.svg)](https://github.com/tekod/mScan/issues)

# fsCacheController
WordPress library for caching arbitrary data with automatic invalidation 
<br>
<br>

fsCacheController is WordPress library for caching arbitrary data with automatic invalidation.<br>
It is framework-agnostic so no other packages are needed for its proper operation.<br>
<br>
This lib constantly updates log and statistics records to inform administrator about cache usage.<br>
<br>
There is admin dashboard settings page where administrator can enable/disable caching function<br>
and see log records and statistics.<br>
<br>
Typical usage for this library is to substitute code blocks that have to <br>
access database several times to prepare HTML content,<br>
for example: navigation block, category tree, footer, latest blog posts block, related products block,...<br>
<br>
<br>
Usage:<br>
<br>
Put this in "functions.php" of yours theme to initialize controller where parameter is path to configuration file:<br>
```
use FWS\CacheController\CacheController;
require_once 'inc/fsCacheController/CacheController.php';  // optional if you use some autoloader
CacheController::Init(__DIR__.'/config/cachecontroller.config.php');
```
<br>
<br>

Example of configuration is part of this lib.<br>
It is highly recommanded to clone (and rename) configuration file outside of lib directory <br>
and edit content there, just like in code above. <br>
That way you can safely update library without losing configuration.<br>
<br>
<br>

This will echo HTML content identified as "product menu" from cache or include template if not:<br>
```
CacheController::Profile('eCommerce')->Output('product menu', function() {
    get_template_part('template-parts/woocommerce_product_menu');
});
```
<br>
<br>

Here is how to pass parameter to inner function (closure) to create dynamic identifer:<br>
```
CacheController::Profile('Shop')->Output('product-page-'.$ProductID, function() use ($ProductID) {
     $Product= new MyProduct($ProductID);
     $Title= $Product->GetTitle();
     $Image= $Product->GetImage();
     include 'shop/single-product.php';
});
```
<br>
<br>

Instead of echoing content use "Get()" to just return arbitrary data,<br>
typical usecase is to replace fetching values from database with heavy SQL queries<br>
```
$Data= CacheController::Profile('Movies')->Get('full-movie-data-'.$ID, function() use ($ID) {<br>
     return [<br>
         'Movie' => get_post($ID),<br>
         'Genres' => get_posts(['posts_per_page' => -1, 'post_type' => 'genre', 'tax_query' => [.....<br>
         'Actors' => get_posts(['posts_per_page' => -1, 'post_type' => 'actor', 'tax_query' => [.....<br>
         'Photos' => get_posts(['posts_per_page' => -1, 'post_type' => 'photo', 'tax_query' => [.....<br>
         ....<br>
     ];<br>
});<br>
```
<br>
<br>

Note: identifiers must contain only filename-safe chars (regex: "A-Za-z0-9~_!&= \|\.\-\+")<br>
easiest method to ensure that is to pass it through md5 func, like:  CacheController::Profile('IMDB')->Get(md5($URL), function() {...<br>
<br>
<br>

Methods Get and Output has 3rd parameter "OnCacheHit" where you can put code block (closure) <br>
that should be executed if content is returned from cache (meaning that main closure was not executed).<br>
Example for usecase using this feature can be caching template block containing ContactForm7 shortcode<br>
because beside echoing HTML this plugin rely on actions to include some javascript. <br>
Using 3rd parameter you can execute all that side-effects to simulate normal environment <br>
and ensure plugin proper operation, in this case: echoing missing javascript code. <br>
<br>
<br>

This library is perfect for MVC style themes,<br>
you can wrap all getters from "model" with closure and fetch them via Get() method, <br>
just like in last usage example.<br>
Pure simplicity: gather all data from all sources and cache them in single call. <br>
<br>
<br>

---

# Security issues

If you have found a security issue, please contact the author directly at office@tekod.com.

