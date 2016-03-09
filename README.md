```php
use WechatDev\WechatJSSDK;

Route::get('/', function () {
	//default
	$signPackage = WechatJSSDK::set("your_appid","your_appsecrect")->getSignPackage();
	
	//or specify share link
	$signPackage = WechatJSSDK::set("your_appid","your_appsecrect")
		->setShareLink('https://google.com')
		->getSignPackage();
		
	//or add protocol and hostname automatically
	$signPackage = WechatJSSDK::set("your_appid","your_appsecrect")
		->setShareLink('/test',true) //https://hostname/test
		->getSignPackage();
		
	return view('welcome',["signPackage"=>$signPackage]);
});
```