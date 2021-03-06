<?php

namespace Enqueue\Bundle\Tests\Unit\Events;

use Enqueue\Bundle\Events\AsyncListener;
use Enqueue\Bundle\Events\ProxyEventDispatcher;
use Enqueue\Test\ClassExtensionTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent;

class ProxyEventDispatcherTest extends TestCase
{
    use ClassExtensionTrait;

    public function testShouldBeSubClassOfContainerAwareEventDispatcher()
    {
        $this->assertClassExtends(ContainerAwareEventDispatcher::class, ProxyEventDispatcher::class);
    }

    public function testShouldSetSyncModeForGivenEventNameOnDispatchAsyncListenersOnly()
    {
        $asyncListenerMock = $this->createAsyncLisenerMock();
        $asyncListenerMock
            ->expects($this->once())
            ->method('resetSyncMode')
        ;
        $asyncListenerMock
            ->expects($this->once())
            ->method('syncMode')
            ->with('theEvent')
        ;

        $trueEventDispatcher = new EventDispatcher();
        $dispatcher = new ProxyEventDispatcher(new Container(), $trueEventDispatcher, $asyncListenerMock);

        $event = new GenericEvent();
        $dispatcher->dispatchAsyncListenersOnly('theEvent', $event);
    }

    public function testShouldCallAsyncEventButNotOtherOnDispatchAsyncListenersOnly()
    {
        $otherEventWasCalled = false;
        $trueEventDispatcher = new EventDispatcher();
        $trueEventDispatcher->addListener('theEvent', function () use (&$otherEventWasCalled) {
            $this->assertInstanceOf(ProxyEventDispatcher::class, func_get_arg(2));

            $otherEventWasCalled = true;
        });

        $asyncEventWasCalled = false;
        $dispatcher = new ProxyEventDispatcher(new Container(), $trueEventDispatcher, $this->createAsyncLisenerMock());
        $dispatcher->addListener('theEvent', function () use (&$asyncEventWasCalled) {
            $this->assertInstanceOf(ProxyEventDispatcher::class, func_get_arg(2));

            $asyncEventWasCalled = true;
        });

        $event = new GenericEvent();
        $dispatcher->dispatchAsyncListenersOnly('theEvent', $event);

        $this->assertFalse($otherEventWasCalled);
        $this->assertTrue($asyncEventWasCalled);
    }

    public function testShouldCallOtherEventIfDispatchedFromAsyncEventOnDispatchAsyncListenersOnly()
    {
        $otherEventWasCalled = false;
        $trueEventDispatcher = new EventDispatcher();
        $trueEventDispatcher->addListener('theOtherEvent', function () use (&$otherEventWasCalled) {
            $this->assertNotInstanceOf(ProxyEventDispatcher::class, func_get_arg(2));

            $otherEventWasCalled = true;
        });

        $asyncEventWasCalled = false;
        $dispatcher = new ProxyEventDispatcher(new Container(), $trueEventDispatcher, $this->createAsyncLisenerMock());
        $dispatcher->addListener('theEvent', function () use (&$asyncEventWasCalled) {
            $this->assertInstanceOf(ProxyEventDispatcher::class, func_get_arg(2));

            $asyncEventWasCalled = true;

            func_get_arg(2)->dispatch('theOtherEvent');
        });

        $event = new GenericEvent();
        $dispatcher->dispatchAsyncListenersOnly('theEvent', $event);

        $this->assertTrue($otherEventWasCalled);
        $this->assertTrue($asyncEventWasCalled);
    }

    public function testShouldNotCallAsyncEventIfDispatchedFromOtherEventOnDispatchAsyncListenersOnly()
    {
        $trueEventDispatcher = new EventDispatcher();
        $trueEventDispatcher->addListener('theOtherEvent', function () {
            func_get_arg(2)->dispatch('theOtherAsyncEvent');
        });

        $dispatcher = new ProxyEventDispatcher(new Container(), $trueEventDispatcher, $this->createAsyncLisenerMock());
        $dispatcher->addListener('theAsyncEvent', function () {
            func_get_arg(2)->dispatch('theOtherEvent');
        });
        $asyncEventWasCalled = false;
        $dispatcher->addListener('theOtherAsyncEvent', function () use (&$asyncEventWasCalled) {
            $asyncEventWasCalled = true;
        });

        $event = new GenericEvent();
        $dispatcher->dispatchAsyncListenersOnly('theEvent', $event);

        $this->assertFalse($asyncEventWasCalled);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|AsyncListener
     */
    private function createAsyncLisenerMock()
    {
        return $this->createMock(AsyncListener::class);
    }
}
