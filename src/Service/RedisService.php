<?php

namespace App\Service;

use Redis;
use RedisException;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Console\Command\LockableTrait;

class RedisService
{
    private Redis $redis;
    private const SET_OPTIONS = ['EX' => 120];

    use LockableTrait;
    public function __construct(string $dsn)
    {
        $this->redis = RedisAdapter::createConnection(
            $dsn,
            [
                'class' => Redis::class,
                'persistent' => 0,
                'persistent_id' => null,
                'timeout' => 60,
                'read_timeout' => 0,
                'retry_interval' => 0,
                'tcp_keepalive' => 0,
                'lazy' => null,
                'redis_cluster' => false,
                'redis_sentinel' => null,
                'failover' => 'none',
                'ssl' => null,
            ]
        );
    }

    /**
     * @throws RedisException
     */
    public function get(string $key)
    {
        return $this->redis->get($key);
    }

    /**
     * @throws RedisException
     */
    public function set(string $key, string $value): string
    {
        if ($this->lock($key, true)) {
            $this->redis->set($key, $value, self::SET_OPTIONS);
            $this->release();
        }

        return $key;
    }

    /**
     * @throws RedisException
     */
    public function del(string $key): void
    {
        if ($this->lock($key, true)) {
            $this->redis->del($key);
            $this->release();
        }
    }
}
