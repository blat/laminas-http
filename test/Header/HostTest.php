<?php

namespace LaminasTest\Http\Header;

use Laminas\Http\Header\Exception\InvalidArgumentException;
use Laminas\Http\Header\HeaderInterface;
use Laminas\Http\Header\Host;
use PHPUnit\Framework\TestCase;

class HostTest extends TestCase
{
    public function testHostFromStringCreatesValidHostHeader()
    {
        $hostHeader = Host::fromString('Host: xxx');
        $this->assertInstanceOf(HeaderInterface::class, $hostHeader);
        $this->assertInstanceOf(Host::class, $hostHeader);
    }

    public function testHostGetFieldNameReturnsHeaderName()
    {
        $hostHeader = new Host();
        $this->assertEquals('Host', $hostHeader->getFieldName());
    }

    public function testHostGetFieldValueReturnsProperValue()
    {
        $this->markTestIncomplete('Host needs to be completed');

        $hostHeader = new Host();
        $this->assertEquals('xxx', $hostHeader->getFieldValue());
    }

    public function testHostToStringReturnsHeaderFormattedString()
    {
        $this->markTestIncomplete('Host needs to be completed');

        $hostHeader = new Host();

        // @todo set some values, then test output
        $this->assertEmpty('Host: xxx', $hostHeader->toString());
    }

    /** Implementation specific tests here */

    /**
     * @see http://en.wikipedia.org/wiki/HTTP_response_splitting
     *
     * @group ZF2015-04
     */
    public function testPreventsCRLFAttackViaFromString()
    {
        $this->expectException(InvalidArgumentException::class);
        Host::fromString("Host: xxx\r\n\r\nevilContent");
    }

    /**
     * @see http://en.wikipedia.org/wiki/HTTP_response_splitting
     *
     * @group ZF2015-04
     */
    public function testPreventsCRLFAttackViaConstructor()
    {
        $this->expectException(InvalidArgumentException::class);
        new Host("xxx\r\n\r\nevilContent");
    }
}
