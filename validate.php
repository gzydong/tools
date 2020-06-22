<?php
/**
 * PHP 基本验证函数库文件
 * 该文件保存了基本的验证函数 , 一般用来引用
 * @author     远东
 * @version    v1.0
 */


/**
 * PHP验证字符串的长度是否在指定范围 ( 使用的是mb_strlen()函数 )
 * @param   string $string 字符串
 * @param   int $min 允许最小长度(默认为 2 )
 * @param   int $max 允许最大长度(默认256 )
 * @param   string $char 使用的字符集编码格式( 默认 utf-8 )
 * @param   boolean $isEmpty 允许为空( true 允许 false 默认不允许 )
 * @return  boolean 验证通过返回 true 没有通过返回 false
 */
function checkLength($string, $min = 2, $max = 256, $char = 'utf-8', $isEmpty = false)
{
    if (!empty($string)) {
        $len = mb_strlen($string, $char);
        $isEmpty = $min > $len || $max < $max;
    }
    return $isEmpty;
}


/**
 * PHP验证字符串是否为一个合法的email地址
 * @param   string $email 字符串
 * @return  boolean 验证通过返回 true 没有通过返回 false
 */
function checkEmail($email)
{
    $pattern = '/^[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+(?:\.[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+)*@(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?$/';
    $fullPattern = '/^[^@]*<[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+(?:\.[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+)*@(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?>$/';
    return preg_match($pattern, $email) || preg_match($fullPattern, $email);
}


/**
 * PHP验证字符串是否为一个合法的 url 地址
 * @param   string $url 字符串
 * @param   array $protocol 允许的URI协议数组(默认为 http , https )
 * @return  boolean 验证通过返回 true 没有通过返回 false
 */
function checkUrl($url, array $protocol = array('http', 'https'))
{
    return preg_match('/^(' . implode('|', $protocol) . '):\/\/(([A-Z0-9][A-Z0-9_-]*)(\.[A-Z0-9][A-Z0-9_-]*)+)/i', $url);
}


/**
 * PHP验证字符串是否为一个数字
 * @param   string $num 字符串
 * @return  boolean 验证通过返回 true 没有通过返回 false
 */
function checkNumber($num)
{
    return is_numeric($num) && (preg_match('/^\s*[+-]?\d+\s*$/', $num) || preg_match('/^\s*[-+]?[0-9]*\.?[0-9]+([eE][-+]?[0-9]+)?\s*$/', $num));
}


/**
 * 判断0或正整数
 * @param string $int 验证字符串
 * @param bool $isZero  判断是否可为0
 * @return bool
 */
function isInt(string $int,$isZero = false){
    $reg = $isZero ? '/^[+]{0,1}(\d+)$/' :'/^[1-9]\d*$/';
    return is_numeric($int) && preg_match($reg, $int);
}


/**
 * 验证手机号是否正确
 * @param string $mobile 手机号
 * @return bool
 */
function isMobile($mobile)
{
    return (boolean)preg_match('/^[1][3,4,5,7,8][0-9]{9}$/', $mobile);
}


/**
 * 验证登录密码格式
 * @param $password 用户输入密码
 * @return bool
 */
function isPassword($password) {
    return (boolean)preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{8,16}$/', $password);
}
