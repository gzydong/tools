<?php
namespace App\Helpers;

/**
 * Json Web Token 加密及验证方法
 */
class Jwt
{

    /**
     * @var string  使用HMAC生成信息摘要时所使用的密钥
     */
    private static $key = 'web-secretkey';

    private static $header = array(
        'alg' => 'HS256', //生成signature的算法
        'typ' => 'JWT'    //类型
    );

    /**
     * 获取jwt token
     * @param array $private      私有加密数据
     * @param array $payload      jwt载荷  格式如下非必须
     * @return bool|string
     */
    public static function getToken(array $private,$payload=[])
    {
        if(!is_array($payload)){
            return false;
        }

        $payload = self::getPayload($private,$payload);

        $base64header = self::base64UrlEncode(json_encode(self::$header, JSON_UNESCAPED_UNICODE));

        $base64payload = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));

        $token = $base64header . '.' . $base64payload . '.' . self::signature($base64header . '.' . $base64payload, self::$key, self::$header['alg']);
        return $token;
    }

    /**
     * 包装实际需要传递的数据
     * @param array $private          私有加密数据
     * @param array $payload          jwt载荷
     * @return array
     */
    private static function getPayload(array $private,$payload=[])
    {
        $detaultPayload = [
            'iss'       => 'jwt-admin', //该JWT的签发者
            'exp'       => time() + 60 * 60 * 2, //过期时间
            'sub'       => 'wl-user', //主题 面向的用户
            'aud'       => 'every',//用户
            'nbf'       => time(), //生效时间
            'iat'       => time(), //签发时间
            'jti'       => md5(uniqid('JWT') . time()), //该Token唯一标识
            'private'   => $private,  //私有信息
        ];

        return array_merge($detaultPayload, $payload);
    }

    /**
     * 验证token是否有效,默认验证exp,nbf,iat时间
     * @param string $Token
     * @param bool $isExp  是否验证过期时间
     * @return bool|mixed
     */
    public static function verifyToken(string $Token,$isExp=true)
    {
        $tokens = explode('.', $Token);
        if (count($tokens) != 3)
            return false;

        list($base64Header, $base64Payload, $sign) = $tokens;

        //获取jwt算法
        $base64DecodeHeader = json_decode(self::base64UrlDecode($base64Header), JSON_OBJECT_AS_ARRAY);

        if (empty($base64DecodeHeader['alg']))
            return false;

        //签名验证
        if (self::signature($base64Header . '.' . $base64Payload, self::$key, $base64DecodeHeader['alg']) !== $sign)
            return false;

        $payload = json_decode(self::base64UrlDecode($base64Payload), JSON_OBJECT_AS_ARRAY);

        //签发时间大于当前服务器时间验证失败
        if (isset($payload['iat']) && $payload['iat'] > time())
            return false;

        if($isExp){
            //过期时间小宇当前服务器时间验证失败
            if (isset($payload['exp']) && $payload['exp'] < time() - 10)
                return false;
        }

        //该nbf时间之前不接收处理该Token
        if (isset($payload['nbf']) && $payload['nbf'] > time())
            return false;

        return $payload;
    }

    /**
     * base64UrlEncode  https://jwt.io/ 中base64UrlEncode编码实现
     * @param string $input 需要编码的字符串
     * @return string
     */
    private static function base64UrlEncode(string $input)
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }

    /**
     * base64UrlEncode https://jwt.io/ 中base64UrlEncode解码实现
     * @param string $input 需要解码的字符串
     * @return bool|string
     */
    private static function base64UrlDecode(string $input)
    {
        return base64_decode(str_pad(strtr($input, '-_', '+/'), strlen($input) % 4, '=', STR_PAD_RIGHT));
    }

    /**
     * HMACSHA256签名  https://jwt.io/ 中HMACSHA256签名实现
     * @param string $input 为base64UrlEncode(header).".".base64UrlEncode(payload)
     * @param string $key
     * @param string $alg 算法方式
     * @return mixed
     */
    private static function signature(string $input, string $key, $alg = 'HS256')
    {
        $alg_config = array(
            'HS256' => 'sha256',
        );

        return self::base64UrlEncode(hash_hmac($alg_config[$alg], $input, $key, true));
    }
}
