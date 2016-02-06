```php
use WechatJSSDK\JSSDK as WechatJSSDK;

Route::get('/', function () {
	$signPackage = WechatJSSDK::set("your_appid","your_appsecrect")->getSignPackage();
	return view('welcome',["signPackage"=>$signPackage]);
});
```