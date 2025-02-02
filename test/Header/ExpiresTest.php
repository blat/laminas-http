<?php

namespace LaminasTest\Http\Header;

use Laminas\Http\Header\Exception\InvalidArgumentException;
use Laminas\Http\Header\Expires;
use Laminas\Http\Header\HeaderInterface;
use PHPUnit\Framework\TestCase;

class ExpiresTest extends TestCase
{
    public function testExpiresFromStringCreatesValidExpiresHeader()
    {
        $expiresHeader = Expires::fromString('Expires: Sun, 06 Nov 1994 08:49:37 GMT');
        $this->assertInstanceOf(HeaderInterface::class, $expiresHeader);
        $this->assertInstanceOf(Expires::class, $expiresHeader);
    }

    public function testExpiresGetFieldNameReturnsHeaderName()
    {
        $expiresHeader = new Expires();
        $this->assertEquals('Expires', $expiresHeader->getFieldName());
    }

    public function testExpiresGetFieldValueReturnsProperValue()
    {
        $expiresHeader = new Expires();
        $expiresHeader->setDate('Sun, 06 Nov 1994 08:49:37 GMT');
        $this->assertEquals('Sun, 06 Nov 1994 08:49:37 GMT', $expiresHeader->getFieldValue());
    }

    public function testExpiresToStringReturnsHeaderFormattedString()
    {
        $expiresHeader = new Expires();
        $expiresHeader->setDate('Sun, 06 Nov 1994 08:49:37 GMT');
        $this->assertEquals('Expires: Sun, 06 Nov 1994 08:49:37 GMT', $expiresHeader->toString());
    }

    /**
     * Implementation specific tests are covered by DateTest
     *
     * @see LaminasTest\Http\Header\DateTest
     */

    /**
     * @see http://en.wikipedia.org/wiki/HTTP_response_splitting
     *
     * @group ZF2015-04
     */
    public function testPreventsCRLFAttackViaFromString()
    {
        $this->expectException(InvalidArgumentException::class);
        Expires::fromString("Expires: Sun, 06 Nov 1994 08:49:37 GMT\r\n\r\nevilContent");
    }

    public function testExpiresSetToZero()
    {
        $expires = Expires::fromString('Expires: 0');
        $this->assertEquals('Expires: Thu, 01 Jan 1970 00:00:00 GMT', $expires->toString());

        $expires = new Expires();
        $expires->setDate('0');
        $this->assertEquals('Expires: Thu, 01 Jan 1970 00:00:00 GMT', $expires->toString());

        $expires = new Expires();
        $expires->setDate(0);
        $this->assertEquals('Expires: Thu, 01 Jan 1970 00:00:00 GMT', $expires->toString());
    }
}
