<?php

/**
 * 获取目录下的文件信息
 *
 * @param string $path 目录路径
 *
 * @return array 文件信息
 */
function getPathFileName($path)
{
    $arrReturn = [];
    if (is_dir($path)) {
        $resource = opendir($path);
        if ($resource) {
            while (!!($file = readdir($resource))) {
                if (is_file($path . '/' . $file)) {
                    $arrReturn[] = pathinfo($path . '/' . $file, PATHINFO_FILENAME);
                }
            }
            closedir($resource);
        }
    }
    return $arrReturn;
}

/**
 * PHP获取字符串中英文混合长度
 *
 * @param string $str     字符串
 * @param string $charset 编码( 默认为UTF-8 )
 *
 * @return  int 返回长度，1中文=1位，2英文=1位
 */
function strLength($str, $charset = 'utf-8')
{
    // 字符编码的转换 iconv() 将utf-8的字符编码转换为gb2312的编码
    if ($charset == 'utf-8') {
        $str = iconv('utf-8', 'gb2312', $str);
    }
    $num   = strlen($str);
    $cnNum = 0;
    for ($i = 0; $i < $num; $i++) {
        if (ord(substr($str, $i + 1, 1)) > 127) {
            $cnNum++;
            $i++;
        }
    }
    $enNum  = $num - $cnNum * 2;
    $number = $enNum / 2 + $cnNum;
    return ceil($number);
}

/**
 * 获取唯一码
 *
 * @param string $prefix 默认空
 *
 * @return string 返回前缀加 + 年月日时分秒 + 微妙数 + 6位随机码
 */
function getUniqueCode($prefix = '')
{
    return $prefix . date('YmdHis') . substr(microtime(), 2, 6) . sprintf('%06d', mt_rand(0, 999999), STR_PAD_LEFT);
}

/**
 * 随机生成16位字符串
 *
 * @param int $length
 *
 * @return string 生成的字符串
 */
function getRandomStr($length = 16)
{
    return substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwzxyABCDEFGHIJKLMNOPQRSTUVWZXY'), 0, $length);
}

/**
 * 判断是否为空 0 值不算
 *
 * @param mixed $value 判断的值
 *
 * @return boolean 是空返回 true
 */
function isEmpty($value)
{
    return $value === '' || $value === [] || $value === null || is_string($value) && trim($value) === '';
}

/**
 * 二维数组通过指定的key 进行分类
 *
 * @param array $array
 * @param $index
 * @return array
 */
function arrClassify(array $array,$index){
    $result = [];
    foreach($array as $value){
        $result[$value[$index]][] = $value;
    }

    return $result;
}


/**
 * 生成6位字符的短码字符串
 *
 * @param string $string
 * @return string
 */
function inviteCode(string $string)
{
    $result= sprintf("%u",crc32($string));
    $show = '';
    while($result>0){
        $s = $result % 62;
        if ($s  >35){
            $s = chr($s+61);
        }elseif($s>9 && $s <= 35){
            $s = chr($s+55);
        }
        $show.=$s;
        $result=floor($result/62);
    }

    return $show;
}


/**
 * 手机号脱敏
 * @param $mobile     手机号
 * @return mixed
 */
function mobileFilter($mobile){
    return substr_replace($mobile,'****',3,4);
}

/**
 * 人性化时间处理方法
 * @param $time 时间戳
 * @return false|string
 */
function formatTime($time){
    $input_time = $time;
    $rtime = date("m/d H:i",$time);
    $htime = date("H:i",$time);
    $time = time() - $time;
    if ($time < 60){
        $str = '刚刚';
    }elseif($time < 60 * 60){
        $min = floor($time/60);
        $str = $min.'分钟前';
    }elseif($time < 60 * 60 * 24){
        $h = floor($time/(60*60));
        $str = $h.'小时前 ';
    }elseif($time < 60 * 60 * 24 * 3){
        $d = floor($time/(60*60*24));
        if($d==1){
            $str = '昨天 '.$rtime;
        }else{
            $str = '前天 '.$rtime;
        }
    } else if(date('Y') == date('Y',$input_time)){
        $str = $rtime;
    }else{
        $str = date('Y/m/d H:i',$input_time);
    }

    return $str;
}
