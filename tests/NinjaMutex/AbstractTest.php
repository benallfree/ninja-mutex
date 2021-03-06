<?php
/**
 * This file is part of ninja-mutex.
 *
 * (C) Kamil Dziedzic <arvenil@klecza.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace NinjaMutex;

use Memcache;
use Memcached;
use NinjaMutex\Lock\FlockLock;
use NinjaMutex\Lock\MemcacheLock;
use NinjaMutex\Lock\MemcachedLock;
use NinjaMutex\Lock\MySqlLock;
use NinjaMutex\Mock\MockMemcache;
use NinjaMutex\Mock\MockMemcached;
use NinjaMutex\Mock\MockPredisClient;
use NinjaMutex\Lock\PredisRedisLock;
use Predis;
use org\bovigo\vfs;

abstract class AbstractTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        vfs\vfsStreamWrapper::register();
        vfs\vfsStreamWrapper::setRoot(new vfs\vfsStreamDirectory('nfs'));
        mkdir('/tmp/mutex/');
    }

    public function tearDown()
    {
        foreach (new \DirectoryIterator(vfs\vfsStream::url('nfs')) as $file) {
            if (!$file->isDot()) {
                unlink($file->getPathname());
            }
        }
        rmdir(vfs\vfsStream::url('nfs'));

        foreach (new \DirectoryIterator('/tmp/mutex/') as $file) {
            if (!$file->isDot()) {
                unlink($file->getPathname());
            }
        }
        rmdir('/tmp/mutex/');
    }

    public function lockImplementorProvider()
    {
        $data = array(
            // Just mocks
            $this->provideFlockMockLock(),
            $this->provideMemcacheMockLock(),
            $this->provideMemcachedMockLock(),
            $this->provideMysqlMockLock(),
            $this->providePredisRedisMockLock(),
            // Real locks
            $this->provideFlockLock(),
            $this->provideMemcacheLock(),
            $this->provideMemcachedLock(),
            $this->provideMysqlLock(),
            $this->providePredisRedisLock(),
        );

        return $data;
    }

    public function lockImplementorWithBackendProvider()
    {
        $data = array(
            // Just mocks
            $this->provideMemcacheMockLock(),
            $this->provideMemcachedMockLock(),
            $this->providePredisRedisMockLock(),
        );

        return $data;
    }

    /**
     * @return array
     */
    protected function provideMemcacheMockLock()
    {
        $memcacheMock = new MockMemcache();

        return array(new MemcacheLock($memcacheMock), $memcacheMock);
    }

    /**
     * @return array
     */
    protected function provideMemcachedMockLock()
    {
        $memcachedMock = new MockMemcached();

        return array(new MemcachedLock($memcachedMock), $memcachedMock);
    }

    /**
     * @return array
     */
    protected function provideFlockMockLock()
    {
        return array(new FlockLock(vfs\vfsStream::url('nfs/')));
    }

    /**
     * @return array
     */
    protected function provideMysqlMockLock()
    {
        return array(new MySqlLock('', '', '', 'NinjaMutex\Mock\MockPDO'));
    }

    /**
     * @return array
     */
    protected function providePredisRedisMockLock()
    {
        $predisMock = new MockPredisClient();

        return array(new PredisRedisLock($predisMock), $predisMock);
    }

    /**
     * @return array
     */
    protected function provideMemcacheLock()
    {
        $memcache = new Memcache();
        $memcache->connect('127.0.0.1', 11211);

        return array(new MemcacheLock($memcache));
    }

    /**
     * @return array
     */
    protected function provideMemcachedLock()
    {
        $memcached = new Memcached();
        $memcached->addServer('127.0.0.1', 11211);

        return array(new MemcachedLock($memcached));
    }

    /**
     * @return array
     */
    protected function provideFlockLock()
    {
        return array(new FlockLock('/tmp/mutex/'));
    }

    /**
     * @return array
     */
    protected function provideMysqlLock()
    {
        return array(new MySqlLock('root', '', '127.0.0.1'));
    }

    /**
     * @return array
     */
    protected function providePredisRedisLock()
    {
        return array(new PredisRedisLock(new Predis\Client()));
    }
}
