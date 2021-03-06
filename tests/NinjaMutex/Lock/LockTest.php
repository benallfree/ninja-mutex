<?php
/**
 * This file is part of ninja-mutex.
 *
 * (C) Kamil Dziedzic <arvenil@klecza.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace NinjaMutex\Lock;

use NinjaMutex\AbstractTest;
use NinjaMutex\Mock\PermanentServiceInterface;
use NinjaMutex\UnrecoverableMutexException;

/**
 * Tests for Locks
 *
 * @author Kamil Dziedzic <arvenil@klecza.pl>
 */
class LockTest extends AbstractTest
{
    /**
     * @dataProvider lockImplementorProvider
     * @param LockInterface $lockImplementor
     */
    public function testDisallowToAcquireSelfOwnedLock(LockInterface $lockImplementor)
    {
        $name = 'forfiter';
        $lockImplementor->acquireLock($name, 0);

        $this->assertFalse($lockImplementor->acquireLock($name, 0));

        $lockImplementor->releaseLock($name);
    }

    /**
     * @dataProvider lockImplementorProvider
     * @param LockInterface $lockImplementor
     */
    public function testDisallowToAcquireLockOwnedByOtherLockImplementor(LockInterface $lockImplementor)
    {
        $name = 'forfiter';
        $duplicateLockImplementor = clone $lockImplementor;
        $lockImplementor->acquireLock($name, 0);

        $this->assertFalse($duplicateLockImplementor->acquireLock($name, 0));

        $lockImplementor->releaseLock($name);
    }

    /**
     * @dataProvider lockImplementorProvider
     * @param LockInterface $lockImplementor
     */
    public function testDisallowLockImplementorToReleaseLockAcquiredByOtherImplementor(LockInterface $lockImplementor)
    {
        $name = 'forfiter';
        $lockImplementor->acquireLock($name, 0);

        $duplicateLockImplementor = clone $lockImplementor;
        $this->assertFalse($duplicateLockImplementor->releaseLock($name));

        $lockImplementor->releaseLock($name);
    }

    /**
     * @dataProvider lockImplementorProvider
     * @param LockInterface $lockImplementor
     */
    public function testIfLocksAreNotSharedBetweenImplementors(LockInterface $lockImplementor)
    {
        $name = 'forfiter';
        $lockImplementor->acquireLock($name, 0);

        $duplicateLockImplementor = clone $lockImplementor;
        $duplicateLockImplementor->releaseLock($name);
        $this->assertFalse($duplicateLockImplementor->acquireLock($name, 0));

        $lockImplementor->releaseLock($name);
    }

    /**
     * @dataProvider lockImplementorProvider
     * @param LockInterface $lockImplementor
     */
    public function testIfLockReleasedByOneImplementorCanBeAcquiredByOther(LockInterface $lockImplementor)
    {
        $name = 'forfiter';
        $lockImplementor->acquireLock($name, 0);
        $lockImplementor->releaseLock($name);

        $duplicateLockImplementor = clone $lockImplementor;
        $this->assertTrue($duplicateLockImplementor->acquireLock($name, 0));

        $duplicateLockImplementor->releaseLock($name);
    }

    /**
     * @dataProvider lockImplementorProvider
     * @param LockInterface $lockImplementor
     */
    public function testAcquireAndReleaseLock(LockInterface $lockImplementor)
    {
        $name = 'forfiter';
        $this->assertTrue($lockImplementor->acquireLock($name, 0));
        $this->assertTrue($lockImplementor->isLocked($name));
        $this->assertTrue($lockImplementor->releaseLock($name));
        $this->assertFalse($lockImplementor->isLocked($name));
    }

    /**
     * @dataProvider lockImplementorProvider
     * @param LockInterface $lockImplementor
     */
    public function testIfLockIsReleasedAfterLockImplementorIsDestroyed(LockInterface $lockImplementor)
    {
        $name = 'forfiter';
        $duplicateLockImplementor = clone $lockImplementor;
        $duplicateLockImplementor->acquireLock($name, 0);
        unset($duplicateLockImplementor);

        $this->assertTrue($lockImplementor->acquireLock($name, 0));

        $lockImplementor->releaseLock($name);
    }

    /**
     * @issue https://github.com/arvenil/ninja-mutex/pull/4
     *
     * @dataProvider lockImplementorWithBackendProvider
     * @param LockInterface             $lockImplementor
     * @param PermanentServiceInterface $backend
     */
    public function testIfLockDestructorThrowsWhenBackendIsUnavailable(LockInterface $lockImplementor, PermanentServiceInterface $backend)
    {
        $name = "forfiter";

        $this->assertFalse($lockImplementor->isLocked($name));
        $this->assertTrue($lockImplementor->acquireLock($name, 0));
        $this->assertTrue($lockImplementor->isLocked($name));

        // make backend unavailable
        $backend->setAvailable(false);

        try {
            // explicit __destructor() call, should throw UnrecoverableMutexException
            $lockImplementor->__destruct();
        } catch (UnrecoverableMutexException $e) {
            // make backend available again
            $backend->setAvailable(true);
            // release lock
            $this->assertTrue($lockImplementor->releaseLock($name));
            $this->assertFalse($lockImplementor->releaseLock($name));
            $this->assertFalse($lockImplementor->isLocked($name));

            return;
        }

        $this->fail('An expected exception has not been raised.');
    }

}
