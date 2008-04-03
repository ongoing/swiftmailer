<?php

require_once 'Swift/Tests/SwiftUnitTestCase.php';
require_once 'Swift/Transport/LoadBalancedTransport.php';
require_once 'Swift/Transport/TransportException.php';
require_once 'Swift/Transport.php';

class Swift_Transport_LoadBalancedTransportTest
  extends Swift_Tests_SwiftUnitTestCase
{
  
  public function testEachTransportIsUsedInTurn()
  {
    $context = new Mockery();
    $message1 = $context->mock('Swift_Mime_Message');
    $message2 = $context->mock('Swift_Mime_Message');
    $t1 = $context->mock('Swift_Transport');
    $t2 = $context->mock('Swift_Transport');
    $con1 = $context->states('Connection 1')->startsAs('off');
    $con2 = $context->states('Connection 2')->startsAs('off');
    $context->checking(Expectations::create()
      -> ignoring($message1)
      -> ignoring($message2)
      -> allowing($t1)->isStarted() -> returns(false) -> when($con1->is('off'))
      -> allowing($t1)->isStarted() -> returns(true) -> when($con1->is('on'))
      -> allowing($t1)->start() -> when($con1->is('off')) -> then($con1->is('on'))
      -> one($t1)->send($message1) -> returns(1) -> when($con1->is('on'))
      -> never($t1)->send($message2)
      -> ignoring($t1)
      -> allowing($t2)->isStarted() -> returns(false) -> when($con2->is('off'))
      -> allowing($t2)->isStarted() -> returns(true) -> when($con2->is('on'))
      -> allowing($t2)->start() -> when($con2->is('off')) -> then($con2->is('on'))
      -> one($t2)->send($message2) -> returns(1) -> when($con2->is('on'))
      -> never($t2)->send($message1)
      -> ignoring($t2)
      );
    
    $transport = $this->_getTransport(array($t1, $t2));
    $transport->start();
    $this->assertEqual(1, $transport->send($message1));
    $this->assertEqual(1, $transport->send($message2));
    
    $context->assertIsSatisfied();
  }
  
  public function testTransportsAreReusedInRotatingFashion()
  {
    $context = new Mockery();
    $message1 = $context->mock('Swift_Mime_Message');
    $message2 = $context->mock('Swift_Mime_Message');
    $message3 = $context->mock('Swift_Mime_Message');
    $message4 = $context->mock('Swift_Mime_Message');
    $t1 = $context->mock('Swift_Transport');
    $t2 = $context->mock('Swift_Transport');
    $con1 = $context->states('Connection 1')->startsAs('off');
    $con2 = $context->states('Connection 2')->startsAs('off');
    $context->checking(Expectations::create()
      -> ignoring($message1)
      -> ignoring($message2)
      -> allowing($t1)->isStarted() -> returns(false) -> when($con1->is('off'))
      -> allowing($t1)->isStarted() -> returns(true) -> when($con1->is('on'))
      -> allowing($t1)->start() -> when($con1->is('off')) -> then($con1->is('on'))
      -> one($t1)->send($message1) -> returns(1) -> when($con1->is('on'))
      -> never($t1)->send($message2)
      -> one($t1)->send($message3) -> returns(1) -> when($con1->is('on'))
      -> never($t1)->send($message4)
      -> ignoring($t1)
      -> allowing($t2)->isStarted() -> returns(false) -> when($con2->is('off'))
      -> allowing($t2)->isStarted() -> returns(true) -> when($con2->is('on'))
      -> allowing($t2)->start() -> when($con2->is('off')) -> then($con2->is('on'))
      -> one($t2)->send($message2) -> returns(1) -> when($con2->is('on'))
      -> never($t2)->send($message1)
      -> one($t2)->send($message4) -> returns(1) -> when($con2->is('on'))
      -> never($t2)->send($message3)
      -> ignoring($t2)
      );
    
    $transport = $this->_getTransport(array($t1, $t2));
    $transport->start();
    
    $this->assertEqual(1, $transport->send($message1));
    $this->assertEqual(1, $transport->send($message2));
    $this->assertEqual(1, $transport->send($message3));
    $this->assertEqual(1, $transport->send($message4));
    
    $context->assertIsSatisfied();
  }
  
  public function testMessageCanBeTriedOnNextTransportIfExceptionThrown()
  {
    $e = new Swift_Transport_TransportException('b0rken');
    
    $context = new Mockery();
    $message = $context->mock('Swift_Mime_Message');
    $t1 = $context->mock('Swift_Transport');
    $t2 = $context->mock('Swift_Transport');
    $con1 = $context->states('Connection')->startsAs('off');
    $con2 = $context->states('Connection')->startsAs('off');
    $context->checking(Expectations::create()
      -> ignoring($message)
      -> allowing($t1)->isStarted() -> returns(false) -> when($con1->is('off'))
      -> allowing($t1)->isStarted() -> returns(true) -> when($con1->is('on'))
      -> one($t1)->start() -> when($con1->isNot('on')) -> then($con1->is('on'))
      -> one($t1)->send($message) -> throws($e) -> when($con1->is('on'))
      -> ignoring($t1)
      -> allowing($t2)->isStarted() -> returns(false) -> when($con2->is('off'))
      -> allowing($t2)->isStarted() -> returns(true) -> when($con2->is('on'))
      -> one($t2)->start() -> when($con2->isNot('on')) -> then($con2->is('on'))
      -> one($t2)->send($message) -> returns(1) -> when($con2->is('on'))
      -> ignoring($t2)
      );
    
    $transport = $this->_getTransport(array($t1, $t2));
    $transport->start();
    $this->assertEqual(1, $transport->send($message));
    $context->assertIsSatisfied();
  }
  
  public function testMessageIsTriedOnNextTransportIfZeroReturned()
  {
    $context = new Mockery();
    $message = $context->mock('Swift_Mime_Message');
    $t1 = $context->mock('Swift_Transport');
    $t2 = $context->mock('Swift_Transport');
    $con1 = $context->states('Connection')->startsAs('off');
    $con2 = $context->states('Connection')->startsAs('off');
    $context->checking(Expectations::create()
      -> ignoring($message)
      -> allowing($t1)->isStarted() -> returns(false) -> when($con1->is('off'))
      -> allowing($t1)->isStarted() -> returns(true) -> when($con1->is('on'))
      -> one($t1)->start() -> when($con1->isNot('on')) -> then($con1->is('on'))
      -> one($t1)->send($message) -> returns(0) -> when($con1->is('on'))
      -> ignoring($t1)
      -> allowing($t2)->isStarted() -> returns(false) -> when($con2->is('off'))
      -> allowing($t2)->isStarted() -> returns(true) -> when($con2->is('on'))
      -> one($t2)->start() -> when($con2->isNot('on')) -> then($con2->is('on'))
      -> one($t2)->send($message) -> returns(1) -> when($con2->is('on'))
      -> ignoring($t2)
      );
    
    $transport = $this->_getTransport(array($t1, $t2));
    $transport->start();
    $this->assertEqual(1, $transport->send($message));
    $context->assertIsSatisfied();
  }
  
  public function testZeroIsReturnedIfAllTransportsReturnZero()
  {
    $context = new Mockery();
    $message = $context->mock('Swift_Mime_Message');
    $t1 = $context->mock('Swift_Transport');
    $t2 = $context->mock('Swift_Transport');
    $con1 = $context->states('Connection')->startsAs('off');
    $con2 = $context->states('Connection')->startsAs('off');
    $context->checking(Expectations::create()
      -> ignoring($message)
      -> allowing($t1)->isStarted() -> returns(false) -> when($con1->is('off'))
      -> allowing($t1)->isStarted() -> returns(true) -> when($con1->is('on'))
      -> one($t1)->start() -> when($con1->isNot('on')) -> then($con1->is('on'))
      -> one($t1)->send($message) -> returns(0) -> when($con1->is('on'))
      -> ignoring($t1)
      -> allowing($t2)->isStarted() -> returns(false) -> when($con2->is('off'))
      -> allowing($t2)->isStarted() -> returns(true) -> when($con2->is('on'))
      -> one($t2)->start() -> when($con2->isNot('on')) -> then($con2->is('on'))
      -> one($t2)->send($message) -> returns(0) -> when($con2->is('on'))
      -> ignoring($t2)
      );
    
    $transport = $this->_getTransport(array($t1, $t2));
    $transport->start();
    $this->assertEqual(0, $transport->send($message));
    $context->assertIsSatisfied();
  }
  
  public function testTransportsWhichThrowExceptionsAreNotRetried()
  {
    $e = new Swift_Transport_TransportException('maur b0rken');
    
    $context = new Mockery();
    $message1 = $context->mock('Swift_Mime_Message');
    $message2 = $context->mock('Swift_Mime_Message');
    $message3 = $context->mock('Swift_Mime_Message');
    $message4 = $context->mock('Swift_Mime_Message');
    $t1 = $context->mock('Swift_Transport');
    $t2 = $context->mock('Swift_Transport');
    $con1 = $context->states('Connection')->startsAs('off');
    $con2 = $context->states('Connection')->startsAs('off');
    $context->checking(Expectations::create()
      -> ignoring($message1)
      -> ignoring($message2)
      -> ignoring($message3)
      -> ignoring($message4)
      -> allowing($t1)->isStarted() -> returns(false) -> when($con1->is('off'))
      -> allowing($t1)->isStarted() -> returns(true) -> when($con1->is('on'))
      -> one($t1)->start() -> when($con1->isNot('on')) -> then($con1->is('on'))
      -> one($t1)->send($message1) -> throws($e) -> when($con1->is('on'))
      -> never($t1)->send($message2)
      -> never($t1)->send($message3)
      -> never($t1)->send($message4)
      -> ignoring($t1)
      -> allowing($t2)->isStarted() -> returns(false) -> when($con2->is('off'))
      -> allowing($t2)->isStarted() -> returns(true) -> when($con2->is('on'))
      -> one($t2)->start() -> when($con2->isNot('on')) -> then($con2->is('on'))
      -> one($t2)->send($message1) -> returns(1) -> when($con2->is('on'))
      -> one($t2)->send($message2) -> returns(1) -> when($con2->is('on'))
      -> one($t2)->send($message3) -> returns(1) -> when($con2->is('on'))
      -> one($t2)->send($message4) -> returns(1) -> when($con2->is('on'))
      -> ignoring($t2)
      );
    
    $transport = $this->_getTransport(array($t1, $t2));
    $transport->start();
    $this->assertEqual(1, $transport->send($message1));
    $this->assertEqual(1, $transport->send($message2));
    $this->assertEqual(1, $transport->send($message3));
    $this->assertEqual(1, $transport->send($message4));
  }
  
  public function testExceptionIsThrownIfAllTransportsDie()
  {
    $e = new Swift_Transport_TransportException('b0rken');
    
    $context = new Mockery();
    $message = $context->mock('Swift_Mime_Message');
    $t1 = $context->mock('Swift_Transport');
    $t2 = $context->mock('Swift_Transport');
    $con1 = $context->states('Connection')->startsAs('off');
    $con2 = $context->states('Connection')->startsAs('off');
    $context->checking(Expectations::create()
      -> ignoring($message)
      -> allowing($t1)->isStarted() -> returns(false) -> when($con1->is('off'))
      -> allowing($t1)->isStarted() -> returns(true) -> when($con1->is('on'))
      -> one($t1)->start() -> when($con1->isNot('on')) -> then($con1->is('on'))
      -> one($t1)->send($message) -> throws($e) -> when($con1->is('on'))
      -> ignoring($t1)
      -> allowing($t2)->isStarted() -> returns(false) -> when($con2->is('off'))
      -> allowing($t2)->isStarted() -> returns(true) -> when($con2->is('on'))
      -> one($t2)->start() -> when($con2->isNot('on')) -> then($con2->is('on'))
      -> one($t2)->send($message) -> throws($e) -> when($con2->is('on'))
      -> ignoring($t2)
      );
    
    $transport = $this->_getTransport(array($t1, $t2));
    $transport->start();
    try
    {
      $transport->send($message);
      $this->fail('All transports failed so Exception should be thrown');
    }
    catch (Exception $e)
    {
    }
    $context->assertIsSatisfied();
  }
  
  public function testStoppingTransportStopsAllDelegates()
  {
    $context = new Mockery();
    $t1 = $context->mock('Swift_Transport');
    $t2 = $context->mock('Swift_Transport');
    $con1 = $context->states('Connection')->startsAs('on');
    $con2 = $context->states('Connection')->startsAs('on');
    $context->checking(Expectations::create()
      -> allowing($t1)->isStarted() -> returns(true) -> when($con1->is('on'))
      -> one($t1)->stop() -> when($con1->is('on')) -> then($con1->is('off'))
      -> ignoring($t1)
      -> allowing($t2)->isStarted() -> returns(true) -> when($con2->is('on'))
      -> one($t2)->stop() -> when($con2->is('on')) -> then($con2->is('off'))
      -> ignoring($t2)
      );
    
    $transport = $this->_getTransport(array($t1, $t2));
    $transport->start();
    $transport->stop();
    $context->assertIsSatisfied();
  }
  
  public function testTransportShowsAsNotStartedIfAllDelegatesDead()
  {
    $e = new Swift_Transport_TransportException('b0rken');
    
    $context = new Mockery();
    $message = $context->mock('Swift_Mime_Message');
    $t1 = $context->mock('Swift_Transport');
    $t2 = $context->mock('Swift_Transport');
    $con1 = $context->states('Connection')->startsAs('off');
    $con2 = $context->states('Connection')->startsAs('off');
    $context->checking(Expectations::create()
      -> ignoring($message)
      -> allowing($t1)->isStarted() -> returns(false) -> when($con1->is('off'))
      -> allowing($t1)->isStarted() -> returns(true) -> when($con1->is('on'))
      -> one($t1)->start() -> when($con1->isNot('on')) -> then($con1->is('on'))
      -> one($t1)->send($message) -> throws($e) -> when($con1->is('on'))
      -> ignoring($t1)
      -> allowing($t2)->isStarted() -> returns(false) -> when($con2->is('off'))
      -> allowing($t2)->isStarted() -> returns(true) -> when($con2->is('on'))
      -> one($t2)->start() -> when($con2->isNot('on')) -> then($con2->is('on'))
      -> one($t2)->send($message) -> throws($e) -> when($con2->is('on'))
      -> ignoring($t2)
      );
    
    $transport = $this->_getTransport(array($t1, $t2));
    $transport->start();
    $this->assertTrue($transport->isStarted());
    try
    {
      $transport->send($message);
      $this->fail('All transports failed so Exception should be thrown');
    }
    catch (Exception $e)
    {
      $this->assertFalse($transport->isStarted());
    }
    $context->assertIsSatisfied();
  }
  
  public function testRestartingTransportRestartsDeadDelegates()
  {
    $e = new Swift_Transport_TransportException('b0rken');
    
    $context = new Mockery();
    $message1 = $context->mock('Swift_Mime_Message');
    $message2 = $context->mock('Swift_Mime_Message');
    $t1 = $context->mock('Swift_Transport');
    $t2 = $context->mock('Swift_Transport');
    $con1 = $context->states('Connection')->startsAs('off');
    $con2 = $context->states('Connection')->startsAs('off');
    $context->checking(Expectations::create()
      -> ignoring($message1)
      -> ignoring($message2)
      -> allowing($t1)->isStarted() -> returns(false) -> when($con1->is('off'))
      -> allowing($t1)->isStarted() -> returns(true) -> when($con1->is('on'))
      -> exactly(2)->of($t1)->start() -> when($con1->isNot('on')) -> then($con1->is('on'))
      -> one($t1)->send($message1) -> throws($e) -> when($con1->is('on')) -> then($con1->is('off'))
      -> one($t1)->send($message2) -> returns(10) -> when($con1->is('on'))
      -> ignoring($t1)
      -> allowing($t2)->isStarted() -> returns(false) -> when($con2->is('off'))
      -> allowing($t2)->isStarted() -> returns(true) -> when($con2->is('on'))
      -> one($t2)->start() -> when($con2->isNot('on')) -> then($con2->is('on'))
      -> one($t2)->send($message1) -> throws($e) -> when($con2->is('on'))
      -> never($t2)->send($message2)
      -> ignoring($t2)
      );
    
    $transport = $this->_getTransport(array($t1, $t2));
    $transport->start();
    $this->assertTrue($transport->isStarted());
    try
    {
      $transport->send($message1);
      $this->fail('All transports failed so Exception should be thrown');
    }
    catch (Exception $e)
    {
      $this->assertFalse($transport->isStarted());
    }
    //Restart and re-try
    $transport->start();
    $this->assertTrue($transport->isStarted());
    $this->assertEqual(10, $transport->send($message2));
    $context->assertIsSatisfied();
  }
  
  // -- Private helpers
  
  private function _getTransport(array $transports)
  {
    $transport = new Swift_Transport_LoadBalancedTransport();
    $transport->setTransports($transports);
    return $transport;
  }
  
}