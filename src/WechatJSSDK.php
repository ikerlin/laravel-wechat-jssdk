<?php
namespace WechatDev;

use Carbon\Carbon;
use Cache;
use Request;

class WechatJSSDK
{
    private static $appId;
    private static $appSecret;
    private static $ticketKey;
    private static $tokenKey;
	private static $shareLink;
	
    private $errMsg;
	
    public static function set($appId, $appSecret)
    {
        self::$appId     = $appId;
        self::$appSecret = $appSecret;
        self::$ticketKey = "WechatJSSDK:" . $appId . ":jsapi_ticket";
        self::$tokenKey  = "WechatJSSDK:" . $appId . ":access_token";
        return new self();
    }
	
	public function setShareLink($link, $prefix=false)
	{
		if($prefix){
			$_link = parse_url($link);

			if(!isset($_link['host'])){
				$link = $_SERVER['HTTP_HOST'].$link;
			}
		
			if(!isset($_link['scheme'])){
				$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
				$link = $protocol.$link;
			}			
		}
		
		self::$shareLink = $link;
		
		return $this;
	}

    public function getSignPackage()
    {
        $jsapiTicket = $this->getJsApiTicket();
        
        if ($this->errMsg)
            return $this->errMsg;
        
        // 注意 URL 一定要动态获取，不能 hardcode. 与页面分享的URL一致
		$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
		$url = isset(self::$shareLink) ? self::$shareLink : "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        
        $timestamp = time();
        $nonceStr  = str_random(16);
        
        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        
        $signature = sha1($string);
        
        $signPackage = array(
            "appId" => self::$appId,
            "nonceStr" => $nonceStr,
            "timestamp" => $timestamp,
            "url" => $url,
            "signature" => $signature,
            "rawString" => $string
        );
        
        return $signPackage;
    }
    
    private function getJsApiTicket()
    {
        if (Cache::has(self::$ticketKey)) {
            $data = unserialize(Cache::get(self::$ticketKey));
        }
        
        if (!isset($data) OR $data->expire_time < time()) {
            $accessToken = $this->getAccessToken();
            if (empty($accessToken))
                return;
            // 如果是企业号用以下 URL 获取 ticket
            // $url = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token=$accessToken";
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
            $res = json_decode($this->httpGet($url));
            if (isset($res->ticket)) {
                $ticket             = $res->ticket;
                $data               = new \stdClass;
                $data->expire_time  = time() + 7000;
                $data->jsapi_ticket = $ticket;
                Cache::put(self::$ticketKey, serialize($data), Carbon::now()->addMinutes(120));
            } else {
                $ticket       = null;
                $this->errMsg = $res;
            }
        } else {
            $ticket = $data->jsapi_ticket;
        }
        
        return $ticket;
    }
    
    private function getAccessToken()
    {
        if (Cache::has(self::$tokenKey)) {
            $data = unserialize(Cache::get(self::$tokenKey));
        }
        
        if (!isset($data) OR $data->expire_time < time()) {
            // 如果是企业号用以下URL获取access_token
            // $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=".self::$appId."&corpsecret=".self::$appSecret;
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . self::$appId . "&secret=" . self::$appSecret;
            $res = json_decode($this->httpGet($url));
            if (isset($res->access_token)) {
                $access_token       = $res->access_token;
                $data               = new \stdClass;
                $data->expire_time  = time() + 7000;
                $data->access_token = $access_token;
                Cache::put(self::$tokenKey, serialize($data), Carbon::now()->addMinutes(120));
            } else {
                $access_token = null;
                $this->errMsg = $res;
            }
        } else {
            $access_token = $data->access_token;
        }
        
        return $access_token;
    }
    
    private function httpGet($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        // 为保证第三方服务器与微信服务器之间数据传输的安全性，所有微信接口采用https方式调用，必须使用下面2行代码打开ssl安全校验。
        // 如果在部署过程中代码在此处验证失败，请到 http://curl.haxx.se/ca/cacert.pem 下载新的证书判别文件。
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 2);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_URL, $url);
        
        $res = curl_exec($curl);
        curl_close($curl);
        
        return $res;
    }
}
