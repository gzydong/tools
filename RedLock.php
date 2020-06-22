<?php
namespace App\Helpers;

/**
 * Redis 单机分布式锁
 * Class RedLock
 * @package App\Helpers
 */
class RedLock
{

    //加锁前缀标识
    const PREFIX     = 'lock';

    static $redis;

    /**
     * 获取Redis实例
     *
     * @return \Illuminate\Foundation\Application|mixed
     */
    public static function getRedis()
    {
        if(!self::$redis){
            //这里是获取Laravel Redis 实例，请根据自己项目替换为自己的获取 Redis 连接。
            self::$redis =  app('redis.connection');
        }

        return self::$redis;
    }

    /**
     * 获得锁,如果锁被占用,阻塞,直到获得锁或者超时。
     * -- 1、如果 $timeout 参数为 0,则立即返回锁。
     * -- 2、建议 timeout 设置为 0,避免 redis 因为阻塞导致性能下降。请根据实际需求进行设置。
     *
     * @param  string  $key         缓存KEY
     * @param  string  $requestId   客户端请求唯一ID
     * @param  int     $lockSecond  锁定时间 单位(秒)
     * @param  int     $timeout     取锁超时时间。单位(秒)。等于0,如果当前锁被占用,则立即返回失败。如果大于0,则反复尝试获取锁直到达到该超时时间。
     * @param  int     $sleep       取锁间隔时间 单位(微秒)。当锁为占用状态时。每隔多久尝试去取锁。默认 0.1 秒一次取锁。
     * @return bool
     * @throws \Exception
     */
    public static function lock(string $key,string $requestId, $lockSecond = 20 , $timeout = 0,  $sleep = 100000)
    {
        if (empty($key)) {
            throw new \Exception('获取锁的KEY值没有设置');
        }

        $start = self::getMicroTime();
        $redis = self::getRedis();

        do {

            /**
             *  注：当前写法是根据laravel 中 Redis 的使用方法 , 若 $redis 是自定义 new \Redis() 的实例 应使用 redis 原生写法
             *  $acquired = $redis->set(self::getLockKey($key), $requestId,['NX', 'EX', $lockSecond]); 这个方法
             */
            $acquired = $redis->set(self::getLockKey($key), $requestId , 'NX' , 'EX', $lockSecond);
            if ($acquired) {
                break;
            }

            if ($timeout === 0) {
                break;
            }

            usleep($sleep);
        } while (!is_numeric($timeout) || (self::getMicroTime()) < ($start + ($timeout * 1000000)));

        return $acquired ? true : false;
    }

    /**
     * 释放锁
     *
     * @param string $key 被加锁的KEY
     * @param string $requestId   客户端请求唯一ID
     * @return bool
     */
    public static function release(string $key,string $requestId)
    {
        if (strlen($key) === 0) {
            return false;
        }

        $lua =<<<LAU
            if redis.call("GET", KEYS[1]) == ARGV[1] then
                return redis.call("DEL", KEYS[1])
            else
                return 0
            end
LAU;

        /**
         *  注：当前写法是根据laravel 中 Redis 的使用方法 , 若 $redis 是自定义 new \Redis() 的实例 应使用 redis 原生写法
         *  return self::getRedis()->eval($lua, [self::getLockKey($key), $requestId],1);
         */
        return self::getRedis()->eval($lua,1, self::getLockKey($key), $requestId);
    }

    /**
     * 获取锁 Key
     *
     * @param string $key      需要加锁的KEY
     * @return string
     */
    public static function getLockKey(string $key){
        return self::PREFIX.':'.$key;
    }

    /**
     * 获取当前微秒
     *
     * @return bigint
     */
    protected static function getMicroTime()
    {
        return bcmul(microtime(true), 1000000);
    }
}
