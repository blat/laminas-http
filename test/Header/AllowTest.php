<?php

namespace LaminasTest\Http\Header;

use Laminas\Http\Header\Allow;
use Laminas\Http\Header\Exception\InvalidArgumentException;
use Laminas\Http\Header\HeaderInterface;
use PHPUnit\Framework\TestCase;

class AllowTest extends TestCase
{
    public function testAllowFromStringCreatesValidAllowHeader()
    {
        $allowHeader = Allow::fromString('Allow: GET, POST, PUT');
        $this->assertInstanceOf(HeaderInterface::class, $allowHeader);
        $this->assertInstanceOf(Allow::class, $allowHeader);
        $this->assertEquals(['GET', 'POST', 'PUT'], $allowHeader->getAllowedMethods());
    }

    public function testAllowFromStringSupportsExtensionMethods()
    {
        $allowHeader = Allow::fromString('Allow: GET, POST, PROCREATE');
        $this->assertTrue($allowHeader->isAllowedMethod('PROCREATE'));
    }

    public function testAllowFromStringWithNonPostMethod()
    {
        $allowHeader = Allow::fromString('Allow: GET');
        $this->assertEquals('GET', $allowHeader->getFieldValue());
    }

    public function testAllowGetFieldNameReturnsHeaderName()
    {
        $allowHeader = new Allow();
        $this->assertEquals('Allow', $allowHeader->getFieldName());
    }

    public function testAllowListAllDefinedMethods()
    {
        $methods     = [
            'OPTIONS' => false,
            'GET'     => true,
            'HEAD'    => false,
            'POST'    => true,
            'PUT'     => false,
            'DELETE'  => false,
            'TRACE'   => false,
            'CONNECT' => false,
            'PATCH'   => false,
        ];
        $allowHeader = new Allow();
        $this->assertEquals($methods, $allowHeader->getAllMethods());
    }

    public function testAllowGetDefaultAllowedMethods()
    {
        $allowHeader = new Allow();
        $this->assertEquals(['GET', 'POST'], $allowHeader->getAllowedMethods());
    }

    public function testAllowGetFieldValueReturnsProperValue()
    {
        $allowHeader = new Allow();
        $allowHeader->allowMethods(['GET', 'POST', 'TRACE']);
        $this->assertEquals('GET, POST, TRACE', $allowHeader->getFieldValue());
    }

    public function testAllowToStringReturnsHeaderFormattedString()
    {
        $allowHeader = new Allow();
        $allowHeader->allowMethods(['GET', 'POST', 'TRACE']);
        $this->assertEquals('Allow: GET, POST, TRACE', $allowHeader->toString());
    }

    public function testAllowChecksAllowedMethod()
    {
        $allowHeader = new Allow();
        $allowHeader->allowMethods(['GET', 'POST', 'TRACE']);
        $this->assertTrue($allowHeader->isAllowedMethod('TRACE'));
    }

    /**
     * @see http://en.wikipedia.org/wiki/HTTP_response_splitting
     *
     * @group ZF2015-04
     */
    public function testPreventsCRLFAttackViaFromString()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header value detected');

        Allow::fromString("Allow: GET\r\n\r\nevilContent");
    }

    /** @psalm-return array<string, array{0: string|string[]}> */
    public function injectionMethods(): array
    {
        return [
            'string' => ["\rG\r\nE\nT"],
            'array'  => [["\rG\r\nE\nT"]],
        ];
    }

    /**
     * @see http://en.wikipedia.org/wiki/HTTP_response_splitting
     *
     * @group ZF2015-04
     * @dataProvider injectionMethods
     * @param array|string $methods
     */
    public function testPreventsCRLFAttackViaAllowMethods($methods)
    {
        $header = new Allow();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('valid method');

        $header->allowMethods($methods);
    }

    /**
     * @see http://en.wikipedia.org/wiki/HTTP_response_splitting
     *
     * @group ZF2015-04
     * @dataProvider injectionMethods
     * @param array|string $methods
     */
    public function testPreventsCRLFAttackViaDisallowMethods($methods)
    {
        $header = new Allow();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('valid method');

        $header->disallowMethods($methods);
    }
}
