<?php

namespace App\Helpers;

/**
 * 微信支付工具类
 *
 * @package App\Helpers
 */
class WxPay
{
    private $appid = null;
    private $secret = null;
    private $mchid = null;
    private $key = null;

    private $sslcert_path = '';//证书所在绝对路径
    private $sslkey_path = '';//证书所在绝对路径

    /**
     * WxPay constructor.
     *
     * @param string $appid 微信公众号appid
     * @param string $secret 微信公众号 appsecret
     * @param string $mchid 商家号
     * @param string $key 支付密钥
     */
    public function __construct($appid, $secret, $mchid, $key)
    {
        $this->appid = $appid;
        $this->secret = $secret;
        $this->mchid = $mchid;
        $this->key = $key;
    }

    /**
     * 微信 H5 支付，公众号支付，扫码支付（下单接口）
     *
     * @param $params
     * @return array 返回支付时所需要的数据
     * @throws \Exception
     */
    public function unify($params)
    {
        $data = [
            'appid'=>$this->appid,
            'mch_id'=>$this->mchid,
            'nonce_str'=>self::getNonceStr(),
            'body'=>'在线缴费',
            'spbill_create_ip'=>$_SERVER["REMOTE_ADDR"],
            'trade_type'=>"JSAPI",
            'openid'=>'',
            'out_trade_no'=>'',
            'total_fee'=>0,
            'notify_url'=>'',
        ];

        $data = array_filter(array_merge($data, $params));
        $data['sign'] = $this->createSign($data);

        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $result = $this->https_post($url, $this->toXml($data));
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
        $data = [
            'appid'=>$this->appid,
            'mch_id'=>$this->mchid,
            'nonce_str'=>self::getNonceStr(),
            'out_trade_no'=>$out_trade_no
        ];

        $data['sign'] = $this->createSign($data);
        $url = 'https://api.mch.weixin.qq.com/pay/orderquery';
        $result = $this->https_post($url, $this->toXml($data));
        $ret = $this->xmlToArray($result);
        if ($ret['return_code'] == 'SUCCESS' && $ret['return_msg'] == 'OK') {
            return $ret;
        }

        throw new \Exception('查询微信支付订单失败...');
    }

    /**
     * 退款订单查询
     *
     * @params string $transaction_id : 微信订单号
     * @params string $out_trade_no : 商家订单号（与微信订单号二选一）
     * */
    public function findRefundOrder($out_trade_no)
    {
        $data = [
            'appid'=>$this->appid,
            'mch_id'=>$this->mchid,
            'nonce_str'=>self::getNonceStr(),
            'out_trade_no'=>$out_trade_no
        ];

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
     * 微信支付申请退款
     *
     * @param string $out_trade_no 商户订单号
     * @param string $out_refund_no 商户退款单号
     * @param int $total_fee 订单金额
     * @param int $refund_fee 退款金额
     * @param string $notify_url 退款通知回调url
     * @param string $refund_desc 退款原因
     * @return mixed
     * @throws \Exception
     */
    public function refund($out_trade_no, $out_refund_no, $total_fee, $refund_fee,$notify_url, $refund_desc = '退款')
    {
        $data = [
            'appid'=>$this->appid,
            'mch_id'=>$this->mchid,
            'nonce_str'=>self::getNonceStr(),
            'out_trade_no'=>$out_trade_no,
            'out_refund_no'=>$out_refund_no,
            'total_fee'=>$total_fee,
            'refund_fee'=>$refund_fee,
            'refund_desc'=>$refund_desc,
            'notify_url'=>$notify_url,
        ];

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
     *
     * @link https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay.php?chapter=14_2
     * @param string $openid 用户openid
     * @param string $total_fee 付款金额，单位分
     * @param string $out_trade_no 商家订单号
     * @param string $username 微信用户名称（注意微信昵称若为空时支付会出错）
     * @param string $desc 付款描述
     * @param string $check_name 是否检测用户名（NO_CHECK：不校验真实姓名，FORCE_CHECK：强校验真实姓名）
     * @return mixed
     * @throws \Exception
     */
    public function payForUser($openid, $total_fee, $out_trade_no, $username, $desc, $check_name = 'NO_CHECK')
    {
        $data = [
            'mch_appid'=>$this->appid,
            'mchid'=>$this->mchid,
            'nonce_str'=>self::getNonceStr(),
            'openid'=>$openid,
            'partner_trade_no'=>$out_trade_no,
            'amount'=>$total_fee,
            'check_name'=>$check_name,
            'desc'=>$desc,
            're_user_name'=>$username,
            'spbill_create_ip'=>$_SERVER["REMOTE_ADDR"]
        ];

        $data['sign'] = $this->createSign($data);
        $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
        $result = $this->https_post($url, $this->toXml($data), true);
        $ret = $this->xmlToArray($result);
        if ($ret['return_code'] == 'SUCCESS') {
            return $ret;
        }

        throw new \Exception('付款给用户失败...');
    }

    /**
     * 普通红包
     *
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
        if ($ret['return_code'] == 'SUCCESS') {
            return $ret;
        }

        throw new \Exception('发放普通红包失败...');
    }

    /**
     * 裂变红包：一次可以发放一组红包。首先领取的用户为种子用户，种子用户领取一组红包当中的一个，并可以通过社交分享将剩下的红包给其他用户。
     * 裂变红包充分利用了人际传播的优势。
     *
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
        if ($ret['return_code'] == 'SUCCESS') {
            return $ret;
        }

        throw new \Exception('发放裂变红包失败...');
    }

    /**
     * 查询红包记录
     *
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
        if ($ret['return_code'] == 'SUCCESS') {
            return $ret;
        }

        throw new \Exception('查询红包记录失败...');
    }


    /**
     * 对参数排序，生成MD5加密签名
     *
     * @param $data
     * @return string
     */
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

    /**
     * 发起网络GET请求
     *
     * @param $url
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

    /**
     * POST请求数据
     *
     * @param $url
     * @param $data
     * @param bool $ssl
     * @return mixed|string
     */
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
     * 产生随机字符串，不长于32位
     *
     * @param int $length
     * @return string
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
