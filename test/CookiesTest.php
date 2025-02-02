<?php

namespace LaminasTest\Http;

use Laminas\Http\Cookies;
use Laminas\Http\Header\SetCookie;
use Laminas\Http\Headers;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\Response;
use PHPUnit\Framework\TestCase;

class CookiesTest extends TestCase
{
    public function testFromResponseInSetCookie()
    {
        $response = new Response();
        $headers  = new Headers();
        $header   = new SetCookie('foo', 'bar');
        $header->setDomain('www.zend.com');
        $header->setPath('/');
        $headers->addHeader($header);
        $response->setHeaders($headers);

        $response = Cookies::fromResponse($response, 'https://www.zend.com');
        $this->assertSame($header, $response->getCookie('https://www.zend.com', 'foo'));
    }

    public function testFromResponseInCookie()
    {
        $response = new Response();
        $headers  = new Headers();
        $header   = new SetCookie('foo', 'bar');
        $header->setDomain('www.zend.com');
        $header->setPath('/');
        $headers->addHeader($header);
        $response->setHeaders($headers);

        $response = Cookies::fromResponse($response, 'https://www.zend.com');
        $this->assertSame($header, $response->getCookie('https://www.zend.com', 'foo'));
    }

    public function testRequestCanHaveArrayCookies()
    {
        $_COOKIE    = [
            'test' => [
                'a' => 'value_a',
                'b' => 'value_b',
            ],
        ];
        $request    = new Request();
        $fieldValue = $request->getCookie('test')->getFieldValue();
        $this->assertSame('test[a]=value_a; test[b]=value_b', $fieldValue);

        $_COOKIE    = [
            'test' => [
                'a' => [
                    'a1' => 'va1',
                    'a2' => 'va2',
                ],
                'b' => [
                    'b1' => 'vb1',
                    'b2' => 'vb2',
                ],
            ],
        ];
        $request    = new Request();
        $fieldValue = $request->getCookie('test')->getFieldValue();
        $this->assertSame('test[a][a1]=va1; test[a][a2]=va2; test[b][b1]=vb1; test[b][b2]=vb2', $fieldValue);
    }
}
