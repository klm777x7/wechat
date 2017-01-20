<?php

namespace klm\wechat;

use klm\wechat\Http;

class Wechat {

    private $appId;
    private $appSec;
    private $token;
    private $aesKey;

    public function __construct($config) {
        foreach ($config as $name => $value) {
            $this->$name = $value;
        }
        $this->init();
    }

    private function init() {
        $this->inspect();
    }

    private function inspect() {
        if (empty($this->appId)) {
            throw new \Exception("appId must be set.");
        }
        if (empty($this->appSec)) {
            throw new \Exception("appSec must be set.");
        }
        if (empty($this->token)) {
            throw new \Exception("token must be set.");
        }
        if (empty($this->aesKey)) {
            throw new \Exception("aesKey must be set.");
        }
    }

    public function serverSetup() {
        if (isset($_GET['echostr'])) {
            if ($this->checkSignature()) {
                echo $_GET['echostr'];
                exit;
            }
        }
    }

    public function serverListen() {
        $data = $this->getPostData();
        pr($data);
    }

    private function getPostData() {
        return $this->xmlToArray(file_get_contents('php://input', 'r'));
    }

    private function checkSignature() {
        if (!isset($_GET["signature"]) || !isset($_GET["timestamp"]) || !isset($_GET["nonce"])) {
            return false;
        }
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $encodeStr = self::getSHA1($this->token, $timestamp, $nonce);
        if ($encodeStr == $signature) {
            return true;
        } else {
            return false;
        }
    }

    public static function arrayToXml($arr) {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    public static function xmlToArray($xml) {
        libxml_disable_entity_loader(true);
        $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $values;
    }

    /**
     * 用SHA1算法生成安全签名
     * @param string $token 票据
     * @param string $timestamp 时间戳
     * @param string $nonce 随机字符串
     */
    public static function getSHA1($token, $timestamp, $nonce) {
        $array = array($token, $timestamp, $nonce);
        sort($array, SORT_STRING);
        $str = implode($array);
        return sha1($str);
    }

    public function getAccessToken() {
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->appId}&secret={$this->appSec}";
        return $this->requestApi($url);
    }

    public function oaGetCode($scope = 'snsapi_base', $state = 1) {
        if (isset($_GET['code'])) {
            return $_GET['code'];
        } else {
            if (!isset($_GET['state'])) {
                $rediectUrl = urlencode(self::getCurrentUrl());
                $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$this->appId}&redirect_uri={$rediectUrl}&response_type=code&scope={$scope}&state={$state}#wechat_redirect";
                //header("Location: $url");
                echo "waiting";
                echo "<script language=\"javascript\" type=\"text/javascript\">window.location.href=\"{$url}\";</script>";
                exit;
            } else {
                return false;
            }
        }
    }

    public function oaGetAccessToken($code) {
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$this->appId}&secret={$this->appSec}&code={$code}&grant_type=authorization_code";
        return $this->requestApi($url);
    }

    public function oaGetUserInfo($openId, $accessToken) {
        $url = "https://api.weixin.qq.com/sns/userinfo?access_token={$accessToken}&openid={$openId}&lang=zh_CN";
        return $this->requestApi($url);
    }

    public function getUserInfo($openId, $accessToken) {
        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token={$accessToken}&openid={$openId}&lang=zh_CN";
        return $this->requestApi($url);
    }

    public function setMenu($data, $accessToken) {
        $url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token={$accessToken}";
        $this->requestApiWithData($url, $data);
    }

    /**
     * 
     * 获取当前URL
     */
    public static function getCurrentUrl() {
        $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https: //' : 'http://';
        $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
        $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self . (isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : $path_info);
        return $sys_protocal . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . $relate_url;
    }

    private function requestApi($url) {
        $stdObj = json_decode(Http::get($url));
        if (!is_object($stdObj) || isset($stdObj->errcode)) {
            throw new \Exception("{$stdObj->errcode} $stdObj->errmsg");
        }
        return (array) $stdObj;
    }

    private function requestApiWithData($url, $data) {
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
        $jsonRet = Http::post($url, $jsonData);
        $stdObj = json_decode($jsonRet);
        if ($stdObj->errcode == 0) {
            return true;
        } else {
            throw new \Exception("{$stdObj->errcode} $stdObj->errmsg");
        }
    }

}
