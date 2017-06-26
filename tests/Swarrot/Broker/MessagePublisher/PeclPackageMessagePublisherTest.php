<?php

namespace Swarrot\Broker\MessagePublisher;

use Swarrot\Broker\Message;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

class PeclPackageMessagePublisherTest extends TestCase
{
    protected function setUp()
    {
        if (!class_exists('AMQPConnection')) {
            $this->markTestSkipped('The AMQP extension is not available');
        }
        parent::setUp();
    }

    public function test_publish_with_valid_message()
    {
        $exchange = $this->prophesize('\AMQPExchange');
        $exchange
            ->publish(
                Argument::exact('body'),
                Argument::exact(null),
                Argument::exact(0),
                Argument::exact([])
            )
            ->shouldBeCalledTimes(1)
        ;

        $provider = new PeclPackageMessagePublisher($exchange->reveal());
        $return = $provider->publish(
            new Message('body')
        );

        $this->assertNull($return);
    }

    public function test_it_should_remove_nested_arrays_from_headers()
    {
        $exchange = $this->prophesize('\AMQPExchange');
        $exchange
            ->publish(
                Argument::exact('body'),
                Argument::exact(null),
                Argument::exact(0),
                Argument::exact([
                    'headers' => [
                        'header' => 'value',
                        'integer' => 42,
                    ]
                ])
            )
            ->shouldBeCalledTimes(1)
        ;
        $provider = new PeclPackageMessagePublisher($exchange->reveal());
        $return = $provider->publish(
            new Message('body', [
                'headers' => [
                    'header' => 'value',
                    'integer' => 42,
                    'array' => ['foo', 'bar'],
                    'another_array' => ['foo' => ['bar', 'burger']],
                ]
            ])
        );

        $this->assertNull($return);
    }

    public function test_publish_with_application_headers()
    {
        $exchange = $this->prophesize('\AMQPExchange');
        $exchange
            ->publish(
                Argument::exact('body'),
                Argument::exact(null),
                Argument::exact(0),
                Argument::exact([
                    'headers' => [
                        'another_header' => 'another_value',
                        'string' => 'foobar',
                        'integer' => 42,
                    ]
                ])
            )
            ->shouldBeCalledTimes(1)
        ;
        $provider = new PeclPackageMessagePublisher($exchange->reveal());
        $return = $provider->publish(
            new Message('body', [
                'application_headers' => [
                    'string' => ['S', 'foobar'],
                    'integer' => ['I', 42],
                    'array' => ['A', ['foo', 'bar']]
                ],
                'headers' => [
                    'another_header' => 'another_value'
                ]
            ])
        );

        $this->assertNull($return);
    }

    public function test_it_should_remove_delivery_mode_property_if_equal_to_zero()
    {
        $exchange = $this->prophesize('\AMQPExchange');
        $exchange
            ->publish(
                Argument::exact('body'),
                Argument::exact(null),
                Argument::exact(0),
                Argument::exact([])
            )
            ->shouldBeCalledTimes(1)
        ;
        $provider = new PeclPackageMessagePublisher($exchange->reveal());
        $return = $provider->publish(
            new Message('body', [
                'delivery_mode' => 0
            ])
        );

        $this->assertNull($return);
    }

    public function test_publish_with_publisher_confirms()
    {
        if (version_compare("1.8.0", phpversion('amqp')) === 1) {
            $this->markTestSkipped("The AMQP Extension version does not support publisher confirms");
        }

        $channel = $this->prophesize('\AMQPChannel');
        $channel
            ->setConfirmCallback(
                Argument::type('\Closure'),
                Argument::type('\Closure')
            )
            ->shouldBeCalledTimes(1)
        ;
        $channel
            ->confirmSelect()
            ->shouldBeCalledTimes(1)
        ;
        $channel
            ->waitForConfirm(
                Argument::exact(10)
            )
            ->shouldBeCalledTimes(1)
        ;
        $exchange = $this->prophesize('\AMQPExchange');
        $exchange
            ->getChannel()
            ->willReturn($channel->reveal())
        ;
        $exchange
            ->publish(
                Argument::exact('body'),
                Argument::exact(null),
                Argument::exact(0),
                Argument::exact([])
            )
            ->shouldBeCalledTimes(1)
        ;
        $provider = new PeclPackageMessagePublisher($exchange->reveal(), AMQP_NOPARAM, null, true, 10);
        $return = $provider->publish(
            new Message('body', [
                'delivery_mode' => 0
            ])
        );

        $this->assertNull($return);
    }
}
