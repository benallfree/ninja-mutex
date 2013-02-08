<?php
/**
 * This file is part of ninja-mutex.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package  Arvenil\Ninja\Mutex
 */
namespace Arvenil\Ninja\Mutex;

/**
 * Mock PDO to mimic *_lock functionality
 *
 * @author Kamil Dziedzic <arvenil@klecza.pl>
 */
class MockPDO extends \PDO
{
    /**
     * @var string[]
     */
    protected static $data = array();

    protected $_mock_pdostatment;

    /**
     * @var string[]
     */
    protected $current = array();

    public function __construct($dsn, $user, $password)
    {
        $this->_mock_pdostatment = new MockPDOStatment();
    }

    public function query($statement)
    {
        if (preg_match('/RELEASE_LOCK\("(.*)"\)/', $statement, $m)) {
            return $this->_mock_release_lock($m[1]);
        } elseif (preg_match('/GET_LOCK\("(.*)", *(.*)\)/', $statement, $m)) {
            return $this->_mock_get_lock($m[1], $m[2]);
        } elseif (preg_match('/IS_FREE_LOCK\("(.*)"\)/', $statement, $m)) {
            return $this->_mock_is_free_lock($m[1]);
        }
    }

    protected function _mock_get_lock($key, $timeout)
    {
        // http://dev.mysql.com/doc/refman/5.0/en/miscellaneous-functions.html#function_get-lock
        //
        // "If you have a lock obtained with GET_LOCK(),
        // it is released when you (...) execute a new GET_LOCK()"
        //
        // SELECT IS_FREE_LOCK( 'a' ) , GET_LOCK( 'a', 0 ) , IS_FREE_LOCK( 'a' ) , GET_LOCK( 'a', 0 )
        // IS_FREE_LOCK('a') GET_LOCK('a', 0) IS_FREE_LOCK('a') GET_LOCK('a', 0)
        // 1                 1                0                 1
        if (current($this->_mock_is_free_lock($key)) || isset($this->current[$key])) {
            // This part is made to reflect behaviour that second GET_LOCK() releases all current locks
            foreach ($this->current as $k => $v) {
                unset(self::$data[$k]);
                unset($this->current[$k]);
            }

            self::$data[$key] = true;
            $this->current[$key] = true;
            return $this->_mock_pdostatment->_mock_set_fetch("1");
        }

        // We use sleep because GET_LOCK(str,timeout) accept timeout in seconds
        sleep($timeout);
        return $this->_mock_pdostatment->_mock_set_fetch("0");
    }

    protected function _mock_is_free_lock($key)
    {
        if (isset(self::$data[$key])) {
            return $this->_mock_pdostatment->_mock_set_fetch("0");
        }

        return $this->_mock_pdostatment->_mock_set_fetch("1");
    }

    protected function _mock_release_lock($key)
    {
        unset(self::$data[$key]);
        unset($this->current[$key]);
        return $this->_mock_pdostatment->_mock_set_fetch("1");
    }
}

class MockPDOStatment extends \PDOStatement
{
    protected $_mock_fetch;


    public function _mock_set_fetch($result)
    {
        $this->_mock_fetch = $result;
        return $this;
    }

    public function fetch()
    {
        return $this->_mock_fetch;
    }
}