<?php

namespace App\Helpers;

/**
 * Class WxPay
 * @link https://blog.csdn.net/weixin_34233421/article/details/88770267
 * @package App\Helpers
 */
class WxPay
{
    //微信支付下单接口
    const UNIFIED_ORDER_URL = 'https://api.mch.weixin.qq.com/pay/unifiedorder';

    //微信订单查询接口
    const FIND_ORDER_URL = 'https://api.mch.weixin.qq.com/pay/orderquery';

    //微信公众号appid
    private $appid = '';

    //微信公众号appsecret
    private $secret = '';

    //商家号
    private $mchid = '';

    //支付密钥
    private $key = '';

    //证书所在绝对路径
    private $sslcert_path = '';

    //证书所在绝对路径
    private $sslkey_path = '';

    public function __construct($appid = '', $secret = '', $mchid = '', $key = '')
    {
        if (!empty($appid)) $this->appid = $appid;
        if (!empty($secret)) $this->secret = $secret;
        if (!empty($mchid)) $this->mchid = $mchid;
        if (!empty($key)) $this->key = $key;
    }

    /**
     * 微信 H5 支付，公众号支付，扫码支付
     *
     * @param $params
     * @return array 返回支付时所需要的数据
     * @throws \Exception
     */
    public function unify($params)
    {
        $data['appid'] = $this->appid;
        $data['mch_id'] = $this->mchid;
        $data['nonce_str'] = Wxpay::getNonceStr();

        $data['body'] = '在线缴费';
        $data['spbill_create_ip'] = $_SERVER["REMOTE_ADDR"];
        $data['trade_type'] = "JSAPI";
        $data['openid'] = '';
        $data['out_trade_no'] = '';
        $data['total_fee'] = 0;
        $data['notify_url'] = "";

        $data = array_filter(array_merge($data, $params));
        $data['sign'] = $sign = $this->createSign($data);

        $result = $this->https_post(self::UNIFIED_ORDER_URL, $this->toXml($data));
        $ret = $this->xmlToArray($result);
        if ($ret['return_code'] == 'SUCCESS' && $ret['return_msg'] == 'OK') {
            if ($data['trade_type'] == 'JSAPI') {
                $payConfig = [
                    'appId' => $this->appid,
                    'timeStamp' => time(),
                    'nonceStr' => $data['nonce_str'],
                    'package' => 'prepay_id=' . $ret['prepay_id'],
                    'signType' => 'MD5'
                ];

                $payConfig['paySign'] = $this->createSign($payConfig);
                return $payConfig;
            }

            return $ret;
        }

        throw new \Exception('请求微信下单接口异常...');
    }

    /**
     * 订单查询
     *
     * @param $out_trade_no 微信支付交易单号或商家自定义订单编号
     * @return mixed
     * @throws \Exception
     */
    public function findOrder($out_trade_no)
    {
        $data['appid'] = $this->appid;
        $data['mch_id'] = $this->mchid;
        $data['nonce_str'] = Wxpay::getNonceStr();
        $data['out_trade_no'] = $out_trade_no;
        $data['sign'] = $sign = $this->createSign($data);

        $result = $this->https_post(self::FIND_ORDER_URL, $this->toXml($data));
        $ret = $this->xmlToArray($result);
        if ($ret['return_code'] == 'SUCCESS' && $ret['return_msg'] == 'OK') {
            return $ret;
        }

        throw new \Exception('查询微信支付订单失败...');
    }

    /**
    * 退款订单查询
    * @params string $transaction_id : 微信订单号
    * @params string $out_trade_no : 商家订单号（与微信订单号二选一）
    * */
    public function findRefundOrder($out_trade_no)
    {
        $data['appid'] = $this->appid;
        $data['mch_id'] = $this->mchid;
        $data['nonce_str'] = self::getNonceStr();
        $data['out_trade_no'] = $out_trade_no;
        $data['sign'] = $this->createSign($data);

        $url = 'https://api.mch.weixin.qq.com/pay/refundquery';
        $result = $this->https_post($url, $this->toXml($data));
        $ret = $this->xmlToArray($result);
        if ($ret['return_code'] == 'SUCCESS' && $ret['return_msg'] == 'OK') {
            return $ret;
        }

        throw new \Exception('查询微信支付退款订单失败...');
    }

    /**
     * 申请退款
     *
     * @param $out_trade_no 商户订单号
     * @param $out_refund_no 商户退款单号
     * @param $total_fee 订单金额
     * @param $refund_fee 退款金额
     * @param string $refund_desc 退款原因
     * @return mixed|null
     * @throws \Exception
     */
    public function refund($out_trade_no, $out_refund_no, $total_fee, $refund_fee, $refund_desc = '退款')
    {
        $data['appid'] = $this->appid;
        $data['mch_id'] = $this->mchid;
        $data['nonce_str'] = self::getNonceStr();
        $data['out_trade_no'] = $out_trade_no;
        $data['out_refund_no'] = $out_refund_no;
        $data['total_fee'] = $total_fee * 100;
        $data['refund_fee'] = $refund_fee * 100;
        $data['refund_desc'] = $refund_desc;
        $data['notify_url'] = "";
        $data['sign'] = $this->createSign($data);

        $url = 'https://api.mch.weixin.qq.com/secapi/pay/refund';
        $result = $this->https_post($url, $this->toXml($data), true);
        $ret = $this->xmlToArray($result);
        if ($ret['return_code'] == 'SUCCESS' && $ret['return_msg'] == 'OK') {
            return $ret;
        }

        throw new \Exception('微信退款失败...');
    }

    /**
     * 企业付款至用户零钱
     * @params string $openid : 用户openid
     * @params int $total_fee : 付款金额，单位分
     * @params string $out_trade_no : 商家订单号
     * @params string $username : 微信用户名称（注意微信昵称若为空时支付会出错）
     * @params string $desc : 付款描述
     * @params string $check_name : 是否检测用户名
     * */
    public function payForUser($openid, $total_fee, $out_trade_no, $username = '魔盒CMS', $desc = '魔盒CMS付款给用户', $check_name = 'NO_CHECK')
    {
        $data['amount'] = $total_fee * 100;
        $data['check_name'] = $check_name;
        $data['desc'] = $desc;
        $data['mch_appid'] = $this->appid;
        $data['mchid'] = $this->mchid;
        $data['nonce_str'] = self::getNonceStr();
        $data['openid'] = $openid;
        $data['partner_trade_no'] = $out_trade_no;
        $data['re_user_name'] = $username;
        $data['spbill_create_ip'] = $_SERVER["REMOTE_ADDR"];
        $data['sign'] = $this->createSign($data);

        $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
        $result = $this->https_post($url, $this->toXml($data), true);
        $ret = $this->xmlToArray($result);
        if ($ret['return_code'] == 'SUCCESS' && $ret['result_code'] == 'SUCCESS') {
            //支付成功返回商户订单号、微信订单号、微信支付成功时间
            $result['partner_trade_no'] = $ret['partner_trade_no'];
            $result['payment_no'] = $ret['payment_no'];
            $result['payment_time'] = $ret['payment_time'];
            return $ret;
        }

        throw new \Exception('付款给用户失败...');
    }

    /**
    * 普通红包
    * @params string $out_trade_no : 商家订单号
    * @params string $openid : 接收红包用户的openid
    * @params int $total_fee : 红包金额，单位分
    * @params int $total_num : 红包发放总人数
    * @params string $wishing : 红包祝福语
    * @params string $act_name : 活动名称
    * @params string $remark : 备注
    * @params string $scene_id ：场景值ID。发放红包使用场景，红包金额大于200或者小于1元时必传。PRODUCT_1:商品促销、PRODUCT_2:抽奖、PRODUCT_3:虚拟物品兑奖 、PRODUCT_4:企业内部福利、PRODUCT_5:渠道分润、PRODUCT_6:保险回馈、PRODUCT_7:彩票派奖、PRODUCT_8:税务刮奖
    * */
    public function redPack($openid, $total_fee, $out_trade_no, $total_num = 1, $wishing = '感谢您光临***平台进行购物', $act_name = '***购物发红包', $remark = '购物领红包')
    {
        $data['mch_billno'] = $out_trade_no;
        $data['mch_id'] = $this->mchid;
        $data['wxappid'] = $this->appid;
        $data['send_name'] = '发送红包者的名称';
        $data['re_openid'] = $openid;
        $data['total_amount'] = $total_fee;
        $data['total_num'] = $total_num;
        $data['wishing'] = $wishing;
        $data['client_ip'] = $_SERVER["REMOTE_ADDR"];
        $data['act_name'] = $act_name;
        $data['remark'] = $remark;
        $data['nonce_str'] = self::getNonceStr();
        $data['sign'] = $this->createSign($data);

        $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack';
        $result = $this->https_post($url, $this->toXml($data), true);
        $ret = $this->xmlToArray($result);
        if ($ret['return_code'] == 'SUCCESS' && $ret['result_code'] == 'SUCCESS') {
            return $ret;
        }

        throw new \Exception('发放普通红包失败...');
    }

    /**
    * 裂变红包：一次可以发放一组红包。首先领取的用户为种子用户，种子用户领取一组红包当中的一个，并可以通过社交分享将剩下的红包给其他用户。
     * 裂变红包充分利用了人际传播的优势。
    * @params string $out_trade_no : 商家订单号
    * @params string $openid : 接收红包用户的openid
    * @params int $total_fee : 红包金额，单位分
    * @params int $total_num : 红包发放总人数
    * @params string $wishing : 红包祝福语
    * @params string $act_name : 活动名称
    * @params string $remark : 备注
    * @params string $scene_id ：场景值ID。发放红包使用场景，红包金额大于200或者小于1元时必传。PRODUCT_1:商品促销、PRODUCT_2:抽奖、PRODUCT_3:虚拟物品兑奖 、PRODUCT_4:企业内部福利、PRODUCT_5:渠道分润、PRODUCT_6:保险回馈、PRODUCT_7:彩票派奖、PRODUCT_8:税务刮奖
    * */
    public function redPackGroup($openid, $total_fee, $out_trade_no, $total_num, $wishing = '感谢您光临***进行购物', $act_name = '**购物发红包', $remark = '购物领红包')
    {
        $data['mch_billno'] = $out_trade_no;
        $data['mch_id'] = $this->mchid;
        $data['wxappid'] = $this->appid;
        $data['send_name'] = '发送红包者的名称';
        $data['re_openid'] = $openid;
        $data['total_amount'] = $total_fee;
        $data['amt_type'] = 'ALL_RAND';   //ALL_RAND—全部随机,商户指定总金额和红包发放总人数，由微信支付随机计算出各红包金额
        $data['total_num'] = $total_num;
        $data['wishing'] = $wishing;
        $data['client_ip'] = $_SERVER["REMOTE_ADDR"];
        $data['act_name'] = $act_name;
        $data['remark'] = $remark;
        $data['nonce_str'] = self::getNonceStr();
        $data['sign'] = $this->createSign($data);

        $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/sendgroupredpack';
        $result = $this->https_post($url, $this->toXml($data), true);
        $ret = $this->xmlToArray($result);
        if ($ret['return_code'] == 'SUCCESS' && $ret['result_code'] == 'SUCCESS') {
            return $ret;
        }

        throw new \Exception('发放裂变红包失败...');
    }

    /**
     * 查询红包记录
     * @params string $out_trade_no : 商家订单号
     * */
    public function findRedPack($out_trade_no)
    {
        $data['mch_billno'] = $out_trade_no;
        $data['mch_id'] = $this->mchid;
        $data['appid'] = $this->appid;
        $data['bill_type'] = 'MCHT';           //MCHT:通过商户订单号获取红包信息。
        $data['nonce_str'] = self::getNonceStr();
        $data['sign'] = $this->createSign($data);

        $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/gethbinfo';
        $result = $this->https_post($url, $this->toXml($data), true);
        $ret = $this->xmlToArray($result);
        if ($ret['return_code'] == 'SUCCESS' && $ret['result_code'] == 'SUCCESS') {
            return $ret;
        }

        throw new \Exception('查询红包记录失败...');
    }

    /**
     * 获取用户微信的OPENID
     *
     * @param bool $c
     * @return mixed
     */
    public function openid($c = false)
    {
        if ($_GET['state'] != "zgm") {
            $t = $c ? "snsapi_userinfo" : "snsapi_base";
            $url = urlencode(get_url());
            $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $this->appid . "&redirect_uri=" . $url . "&response_type=code&scope=" . $t . "&state=zgm#wechat_redirect";
            echo "<html><script>window.location.href='$url';</script></html>";
            exit;
        }
        if ($_GET['code']) {
            $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=" . $this->appid . "&secret=" . $this->secret . "&code=" . $_GET['code'] . "&grant_type=authorization_code";
            $wx_db = json_decode($this->https_get($url));
            if ($c) {
                $url_2 = "https://api.weixin.qq.com/sns/userinfo?access_token=" . $wx_db->access_token . "&openid=" . $wx_db->openid . "&lang=zh_CN";
                $db = json_decode($this->https_get($url_2));
                return $db;
            } else {
                return $wx_db->openid;
            }
        }
    }

    /**
     * 发起网络GET请求
     *
     * @param $url URL链接
     * @return mixed|string
     */
    private function https_get($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_HEADER, FALSE);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        if (curl_errno($curl)) {
            return 'Errno' . curl_error($curl);
        } else {
            $result = curl_exec($curl);
        }
        curl_close($curl);
        return $result;
    }

    //对参数排序，生成MD5加密签名
    private function createSign($data)
    {
        $str = '';
        ksort($data);
        foreach ($data as $k => $v) {
            if ($k != 'sign') $str .= $k . '=' . $v . '&';
        }

        $temp = $str . "key={$this->key}";
        return strtoupper(md5($temp));
    }

    //POST提交数据
    private function https_post($url, $data, $ssl = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        if ($ssl) {
            curl_setopt($ch, CURLOPT_SSLCERT, $this->sslcert_path);
            curl_setopt($ch, CURLOPT_SSLKEY, $this->sslkey_path);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return 'Errno: ' . curl_error($ch);
        }
        curl_close($ch);
        return $result;
    }

    /**
     * XML转array
     *
     * @param $xml
     * @return mixed
     */
    private function xmlToArray($xml)
    {
        libxml_disable_entity_loader(true);
        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        return json_decode(json_encode($xmlstring), true);
    }

    /**
     * 输出xml字符
     *
     * @param $data
     * @return string
     * @throws \Exception
     */
    public function toXml($data)
    {
        if (!is_array($data) || count($data) <= 0) {
            throw new \Exception("数组数据异常！");
        }

        $xml = "<xml>";
        foreach ($data as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     *
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return 产生的随机字符串
     */
    public static function getNonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    /**
     * 获取支付回调消息
     *
     * @return array|mixed
     */
    public function getNotifyMessage()
    {
        $xml = isset($GLOBALS["HTTP_RAW_POST_DATA"]) ? $GLOBALS['HTTP_RAW_POST_DATA'] : file_get_contents("php://input");
        return $xml ? $this->xmlToArray($xml) : [];
    }

    /**
     * 验证签名
     *
     * @param array $data 微信支付成功返回的结果数组
     * @return bool
     */
    public function checkSign(array $data)
    {
        $sign = $this->createSign($data);
        return $sign == $data['sign'] ? true : false;
    }
}
