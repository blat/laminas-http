<?php

namespace LaminasTest\Http;

use Laminas\Http\Exception\InvalidArgumentException;
use Laminas\Http\Exception\RuntimeException;
use Laminas\Http\Header\GenericHeader;
use Laminas\Http\Headers;
use Laminas\Http\Response;
use PHPUnit\Framework\TestCase;

use function array_shift;
use function count;
use function file_get_contents;
use function floor;
use function md5;
use function microtime;
use function min;
use function print_r;
use function sprintf;
use function str_repeat;
use function str_replace;
use function strtolower;

use const DIRECTORY_SEPARATOR;

class ResponseTest extends TestCase
{
    /** @psalm-return iterable<string, array{0: string}> */
    public function validHttpVersions(): iterable
    {
        yield 'http/1.0' => ['1.0'];
        yield 'http/1.1' => ['1.1'];
        yield 'http/2'   => ['2'];
    }

    /**
     * @psalm-return iterable<string, array{
     *     response: string,
     *     expectedVersion: string,
     *     expectedStatus: string,
     *     expectedContent: string
     * }>
     */
    public function validResponseHttpVersionProvider(): iterable
    {
        $responseTemplate = "HTTP/%s 200 OK\r\n\r\nFoo Bar";
        foreach ($this->validHttpVersions() as $testCase => $data) {
            $version = array_shift($data);
            yield $testCase => [
                'response'        => sprintf($responseTemplate, $version),
                'expectedVersion' => $version,
                'expectedStatus'  => '200',
                'expectedContent' => 'Foo Bar',
            ];
        }
    }

    /**
     * @dataProvider validResponseHttpVersionProvider
     * @param string $string Response string
     * @param string $expectedVersion
     * @param string $expectedStatus
     * @param string $expectedContent
     */
    public function testResponseFactoryFromStringCreatesValidResponse(
        $string,
        $expectedVersion,
        $expectedStatus,
        $expectedContent
    ) {
        $response = Response::fromString($string);
        $this->assertEquals($expectedVersion, $response->getVersion());
        $this->assertEquals($expectedStatus, $response->getStatusCode());
        $this->assertEquals($expectedContent, $response->getContent());
    }

    /**
     * @dataProvider validHttpVersions
     * @param string $version
     */
    public function testResponseCanRenderStatusLineUsingDefaultReasonPhrase($version)
    {
        $expected = sprintf('HTTP/%s 404 Not Found', $version);
        $response = new Response();
        $response->setVersion($version);
        $response->setStatusCode(Response::STATUS_CODE_404);
        $this->assertEquals($expected, $response->renderStatusLine());
    }

    /**
     * @dataProvider validHttpVersions
     * @param string $version
     */
    public function testResponseCanRenderStatusLineUsingCustomReasonPhrase($version)
    {
        $expected = sprintf('HTTP/%s 404 Foo Bar', $version);
        $response = new Response();
        $response->setVersion($version);
        $response->setStatusCode(Response::STATUS_CODE_404);
        $response->setReasonPhrase('Foo Bar');
        $this->assertEquals($expected, $response->renderStatusLine());
    }

    public function testInvalidHTTP2VersionString()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A valid response status line was not found in the provided string');
        $string   = 'HTTP/2.0 200 OK' . "\r\n\r\n" . 'Foo Bar';
        $response = Response::fromString($string);
    }

    public function testResponseUsesHeadersContainerByDefault()
    {
        $response = new Response();
        $this->assertInstanceOf(Headers::class, $response->getHeaders());
    }

    public function testRequestCanSetHeaders()
    {
        $response = new Response();
        $headers  = new Headers();

        $ret = $response->setHeaders($headers);
        $this->assertInstanceOf(Response::class, $ret);
        $this->assertSame($headers, $response->getHeaders());
    }

    /** @psalm-return iterable<int, array{0: int}> */
    public function validStatusCode(): iterable
    {
        for ($i = 100; $i <= 599; ++$i) {
            yield $i => [$i];
        }
    }

    /**
     * @dataProvider validStatusCode
     * @param int $statusCode
     */
    public function testResponseCanSetStatusCode($statusCode)
    {
        $response = new Response();
        $this->assertSame(200, $response->getStatusCode());
        $response->setStatusCode($statusCode);
        $this->assertSame($statusCode, $response->getStatusCode());
    }

    public function testResponseSetStatusCodeThrowsExceptionOnInvalidCode()
    {
        $response = new Response();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status code');
        $response->setStatusCode(606);
    }

    public function testResponseGetReasonPhraseWillReturnEmptyPhraseAsDefault()
    {
        $response = new Response();
        $response->setCustomStatusCode(998);
        $this->assertSame('HTTP/1.1 998' . "\r\n\r\n", (string) $response);
    }

    public function testResponseCanSetCustomStatusCode()
    {
        $response = new Response();
        $this->assertEquals(200, $response->getStatusCode());
        $response->setCustomStatusCode('999');
        $this->assertEquals(999, $response->getStatusCode());
    }

    public function testResponseSetCustomStatusCodeThrowsExceptionOnInvalidCode()
    {
        $response = new Response();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status code "foo"; must be an integer between 100 and 599, inclusive');

        $response->setStatusCode('foo');
    }

    public function testResponseEndsAtStatusCode()
    {
        $string   = 'HTTP/1.0 200' . "\r\n\r\n" . 'Foo Bar';
        $response = Response::fromString($string);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Foo Bar', $response->getContent());
    }

    public function testResponseHasZeroLengthReasonPhrase()
    {
        // Space after status code is mandatory,
        // though, reason phrase can be empty.
        // @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec6.html#sec6.1
        $string = 'HTTP/1.0 200 ' . "\r\n\r\n" . 'Foo Bar';

        $response = Response::fromString($string);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Foo Bar', $response->getContent());

        // Reason phrase would fallback to default reason phrase.
        $this->assertEquals('OK', $response->getReasonPhrase());
    }

    public function testGzipResponse()
    {
        $responseTest = file_get_contents(__DIR__ . '/_files/response_gzip');

        $res = Response::fromString($responseTest);

        $this->assertEquals('gzip', $res->getHeaders()->get('Content-encoding')->getFieldValue());
        $this->assertEquals('0b13cb193de9450aa70a6403e2c9902f', md5($res->getBody()));
        $this->assertEquals('f24dd075ba2ebfb3bf21270e3fdc5303', md5($res->getContent()));
    }

    public function testGzipResponseWithEmptyBody()
    {
        $responseTest = <<<'REQ'
HTTP/1.1 200 OK
Date: Sun, 25 Jun 2006 19:36:47 GMT
Server: Apache
X-powered-by: PHP/5.1.4-pl3-gentoo
Content-encoding: gzip
Vary: Accept-Encoding
Content-length: 0
Connection: close
Content-type: text/html

REQ;

        $res = Response::fromString($responseTest);

        $this->assertEquals('gzip', $res->getHeaders()->get('Content-encoding')->getFieldValue());
        $this->assertSame('', $res->getBody());
        $this->assertSame('', $res->getContent());
    }

    public function testDeflateResponse()
    {
        $responseTest = file_get_contents(__DIR__ . '/_files/response_deflate');

        $res = Response::fromString($responseTest);

        $this->assertEquals('deflate', $res->getHeaders()->get('Content-encoding')->getFieldValue());
        $this->assertEquals('0b13cb193de9450aa70a6403e2c9902f', md5($res->getBody()));
        $this->assertEquals('ad62c21c3aa77b6a6f39600f6dd553b8', md5($res->getContent()));
    }

    public function testDeflateResponseWithEmptyBody()
    {
        $responseTest = <<<'REQ'
HTTP/1.1 200 OK
Date: Sun, 25 Jun 2006 19:38:02 GMT
Server: Apache
X-powered-by: PHP/5.1.4-pl3-gentoo
Content-encoding: deflate
Vary: Accept-Encoding
Content-length: 0
Connection: close
Content-type: text/html

REQ;

        $res = Response::fromString($responseTest);

        $this->assertEquals('deflate', $res->getHeaders()->get('Content-encoding')->getFieldValue());
        $this->assertSame('', $res->getBody());
        $this->assertSame('', $res->getContent());
    }

    /**
     * Make sure wer can handle non-RFC complient "deflate" responses.
     *
     * Unlike stanrdard 'deflate' response, those do not contain the zlib header
     * and trailer. Unfortunately some buggy servers (read: IIS) send those and
     * we need to support them.
     *
     * @link https://getlaminas.org/issues/browse/Laminas-6040
     */
    public function testNonStandardDeflateResponseLaminas6040()
    {
        $this->markTestSkipped('Not correctly handling non-RFC complient "deflate" responses');
        $responseTest = file_get_contents(__DIR__ . '/_files/response_deflate_iis');

        $res = Response::fromString($responseTest);

        $this->assertEquals('deflate', $res->getHeaders()->get('Content-encoding')->getFieldValue());
        $this->assertEquals('d82c87e3d5888db0193a3fb12396e616', md5($res->getBody()));
        $this->assertEquals('c830dd74bb502443cf12514c185ff174', md5($res->getContent()));
    }

    /**
     * Make sure there no confusion with complient "deflate" responses.
     *
     * @link https://framework.zend.com/issues/browse/ZF-12457.html
     */
    public function testStandardDeflateResponseLaminas12457()
    {
        $responseTest = file_get_contents(__DIR__ . '/_files/response_deflate_iis_valid');

        $res = Response::fromString($responseTest);

        $this->assertEquals('deflate', $res->getHeaders()->get('Content-encoding')->getFieldValue());
        $this->assertEquals('fa2f670a2da7cd7f0aee953ce5785fa8', md5($res->getBody()));
        $this->assertEquals('992ec500e8332df89bbd9b8e998ec8c9', md5($res->getContent()));
    }

    public function testChunkedResponse()
    {
        $responseTest = file_get_contents(__DIR__ . '/_files/response_chunked');

        $res = Response::fromString($responseTest);

        $this->assertEquals('chunked', $res->getHeaders()->get('Transfer-encoding')->getFieldValue());
        $this->assertEquals('0b13cb193de9450aa70a6403e2c9902f', md5($res->getBody()));
        $this->assertEquals('c0cc9d44790fa2a58078059bab1902a9', md5($res->getContent()));
    }

    public function testChunkedResponseCaseInsensitiveLaminas5438()
    {
        $responseTest = file_get_contents(__DIR__ . '/_files/response_chunked_case');

        $res = Response::fromString($responseTest);

        $this->assertEquals('chunked', strtolower($res->getHeaders()->get('Transfer-encoding')->getFieldValue()));
        $this->assertEquals('0b13cb193de9450aa70a6403e2c9902f', md5($res->getBody()));
        $this->assertEquals('c0cc9d44790fa2a58078059bab1902a9', md5($res->getContent()));
    }

    /**
     * @param int $chunksize the data size of the chunk to create
     * @return string a chunk of data for embedding inside a chunked response
     */
    private function makeChunk($chunksize)
    {
        return sprintf("%d\r\n%s\r\n", $chunksize, str_repeat('W', $chunksize));
    }

    /**
     * @return float the time that calling the getBody function took on the response
     */
    private function getTimeForGetBody(Response $response)
    {
        $timeStart = microtime(true);
        $response->getBody();
        return microtime(true) - $timeStart;
    }

    /**
     * @small
     */
    public function testChunkedResponsePerformance()
    {
        $headers = new Headers();
        $headers->addHeaders([
            'Date'              => 'Sun, 25 Jun 2006 19:55:19 GMT',
            'Server'            => 'Apache',
            'X-powered-by'      => 'PHP/5.1.4-pl3-gentoo',
            'Connection'        => 'close',
            'Transfer-encoding' => 'chunked',
            'Content-type'      => 'text/html',
        ]);

        $response = new Response();
        $response->setHeaders($headers);

        // avoid flakiness, repeat test
        $timings = [];
        for ($i = 0; $i < 4; $i++) {
            // get baseline for timing: 2000 x 1 Byte chunks
            $responseData = str_repeat($this->makeChunk(1), 2000);
            $response->setContent($responseData);
            $time1 = $this->getTimeForGetBody($response);

            // 'worst case' response, where 2000 1 Byte chunks are followed by a 10 MB Chunk
            $responseData2 = $responseData . $this->makeChunk(10000000);
            $response->setContent($responseData2);
            $time2 = $this->getTimeForGetBody($response);

            $timings[] = floor($time2 / $time1);
        }

        array_shift($timings); // do not measure first iteration

        // make sure that the worst case packet will have an equal timing as the baseline
        $errMsg = 'Chunked response is not parsing large packets efficiently! Timings:';
        $this->assertLessThan(25, min($timings), $errMsg . print_r($timings, true));
    }

    public function testLineBreaksCompatibility()
    {
        $responseTestLf = $this->readResponse('response_lfonly');
        $resLf          = Response::fromString($responseTestLf);

        $responseTestCrlf = $this->readResponse('response_crlf');
        $resCrlf          = Response::fromString($responseTestCrlf);

        $this->assertEquals(
            $resLf->getHeaders()->toString(),
            $resCrlf->getHeaders()->toString(),
            'Responses headers do not match'
        );

        $this->markTestIncomplete('Something is fishy with the response bodies in the test responses');
        $this->assertEquals($resLf->getBody(), $resCrlf->getBody(), 'Response bodies do not match');
    }

    public function test404IsClientErrorAndNotFound()
    {
        $responseTest = $this->readResponse('response_404');
        $response     = Response::fromString($responseTest);

        $this->assertEquals(404, $response->getStatusCode(), 'Response code is expected to be 404, but it\'s not.');
        $this->assertTrue($response->isClientError(), 'Response is an error, but isClientError() returned false');
        $this->assertFalse($response->isForbidden(), 'Response is an error, but isForbidden() returned true');
        $this->assertFalse($response->isInformational(), 'Response is an error, but isInformational() returned true');
        $this->assertTrue($response->isNotFound(), 'Response is an error, but isNotFound() returned false');
        $this->assertFalse($response->isGone(), 'Response is an error, but isGone() returned true');
        $this->assertFalse($response->isOk(), 'Response is an error, but isOk() returned true');
        $this->assertFalse($response->isServerError(), 'Response is an error, but isServerError() returned true');
        $this->assertFalse($response->isRedirect(), 'Response is an error, but isRedirect() returned true');
        $this->assertFalse($response->isSuccess(), 'Response is an error, but isSuccess() returned true');
    }

    public function test410IsGone()
    {
        $responseTest = $this->readResponse('response_410');
        $response     = Response::fromString($responseTest);

        $this->assertEquals(410, $response->getStatusCode(), 'Response code is expected to be 410, but it\'s not.');
        $this->assertTrue($response->isClientError(), 'Response is an error, but isClientError() returned false');
        $this->assertFalse($response->isForbidden(), 'Response is an error, but isForbidden() returned true');
        $this->assertFalse($response->isInformational(), 'Response is an error, but isInformational() returned true');
        $this->assertFalse($response->isNotFound(), 'Response is an error, but isNotFound() returned true');
        $this->assertTrue($response->isGone(), 'Response is an error, but isGone() returned false');
        $this->assertFalse($response->isOk(), 'Response is an error, but isOk() returned true');
        $this->assertFalse($response->isServerError(), 'Response is an error, but isServerError() returned true');
        $this->assertFalse($response->isRedirect(), 'Response is an error, but isRedirect() returned true');
        $this->assertFalse($response->isSuccess(), 'Response is an error, but isSuccess() returned true');
    }

    public function test500isError()
    {
        $responseTest = $this->readResponse('response_500');
        $response     = Response::fromString($responseTest);

        $this->assertEquals(500, $response->getStatusCode(), 'Response code is expected to be 500, but it\'s not.');
        $this->assertFalse($response->isClientError(), 'Response is an error, but isClientError() returned true');
        $this->assertFalse($response->isForbidden(), 'Response is an error, but isForbidden() returned true');
        $this->assertFalse($response->isInformational(), 'Response is an error, but isInformational() returned true');
        $this->assertFalse($response->isNotFound(), 'Response is an error, but isNotFound() returned true');
        $this->assertFalse($response->isGone(), 'Response is an error, but isGone() returned true');
        $this->assertFalse($response->isOk(), 'Response is an error, but isOk() returned true');
        $this->assertTrue($response->isServerError(), 'Response is an error, but isServerError() returned false');
        $this->assertFalse($response->isRedirect(), 'Response is an error, but isRedirect() returned true');
        $this->assertFalse($response->isSuccess(), 'Response is an error, but isSuccess() returned true');
    }

    /**
     * @group Laminas-5520
     */
    public function test302LocationHeaderMatches()
    {
        $headerName  = 'Location';
        $headerValue = 'http://www.google.com/ig?hl=en';
        $response    = Response::fromString($this->readResponse('response_302'));
        $responseIis = Response::fromString($this->readResponse('response_302_iis'));

        $this->assertEquals($headerValue, $response->getHeaders()->get($headerName)->getFieldValue());
        $this->assertEquals($headerValue, $responseIis->getHeaders()->get($headerName)->getFieldValue());
    }

    public function test300isRedirect()
    {
        $response = Response::fromString($this->readResponse('response_302'));

        $this->assertEquals(302, $response->getStatusCode(), 'Response code is expected to be 302, but it\'s not.');
        $this->assertFalse($response->isClientError(), 'Response is an error, but isClientError() returned true');
        $this->assertFalse($response->isForbidden(), 'Response is an error, but isForbidden() returned true');
        $this->assertFalse($response->isInformational(), 'Response is an error, but isInformational() returned true');
        $this->assertFalse($response->isNotFound(), 'Response is an error, but isNotFound() returned true');
        $this->assertFalse($response->isGone(), 'Response is an error, but isGone() returned true');
        $this->assertFalse($response->isOk(), 'Response is an error, but isOk() returned true');
        $this->assertFalse($response->isServerError(), 'Response is an error, but isServerError() returned true');
        $this->assertTrue($response->isRedirect(), 'Response is an error, but isRedirect() returned false');
        $this->assertFalse($response->isSuccess(), 'Response is an error, but isSuccess() returned true');
    }

    public function test200Ok()
    {
        $response = Response::fromString($this->readResponse('response_deflate'));

        $this->assertEquals(200, $response->getStatusCode(), 'Response code is expected to be 200, but it\'s not.');
        $this->assertFalse($response->isClientError(), 'Response is an error, but isClientError() returned true');
        $this->assertFalse($response->isForbidden(), 'Response is an error, but isForbidden() returned true');
        $this->assertFalse($response->isInformational(), 'Response is an error, but isInformational() returned true');
        $this->assertFalse($response->isNotFound(), 'Response is an error, but isNotFound() returned true');
        $this->assertFalse($response->isGone(), 'Response is an error, but isGone() returned true');
        $this->assertTrue($response->isOk(), 'Response is an error, but isOk() returned false');
        $this->assertFalse($response->isServerError(), 'Response is an error, but isServerError() returned true');
        $this->assertFalse($response->isRedirect(), 'Response is an error, but isRedirect() returned true');
        $this->assertTrue($response->isSuccess(), 'Response is an error, but isSuccess() returned false');
    }

    public function test100Continue()
    {
        $this->markTestIncomplete();
    }

    public function testAutoMessageSet()
    {
        $response = Response::fromString($this->readResponse('response_403_nomessage'));

        $this->assertEquals(403, $response->getStatusCode(), 'Response status is expected to be 403, but it isn\'t');
        $this->assertEquals(
            'Forbidden',
            $response->getReasonPhrase(),
            'Response is 403, but message is not "Forbidden" as expected'
        );

        // While we're here, make sure it's classified as error...
        $this->assertTrue($response->isClientError(), 'Response is an error, but isClientError() returned false');
        $this->assertTrue($response->isForbidden(), 'Response is an error, but isForbidden() returned false');
        $this->assertFalse($response->isInformational(), 'Response is an error, but isInformational() returned true');
        $this->assertFalse($response->isNotFound(), 'Response is an error, but isNotFound() returned true');
        $this->assertFalse($response->isGone(), 'Response is an error, but isGone() returned true');
        $this->assertFalse($response->isOk(), 'Response is an error, but isOk() returned true');
        $this->assertFalse($response->isServerError(), 'Response is an error, but isServerError() returned true');
        $this->assertFalse($response->isRedirect(), 'Response is an error, but isRedirect() returned true');
        $this->assertFalse($response->isSuccess(), 'Response is an error, but isSuccess() returned true');
    }

    public function testToString()
    {
        $responseStr = $this->readResponse('response_404');
        $response    = Response::fromString($responseStr);

        $this->assertEquals(
            strtolower(str_replace("\n", "\r\n", $responseStr)),
            strtolower($response->toString()),
            'Response convertion to string does not match original string'
        );
        $this->assertEquals(
            strtolower(str_replace("\n", "\r\n", $responseStr)),
            strtolower((string) $response),
            'Response convertion to string does not match original string'
        );
    }

    public function testToStringGzip()
    {
        $responseStr = $this->readResponse('response_gzip');
        $response    = Response::fromString($responseStr);

        $this->assertEquals(
            strtolower($responseStr),
            strtolower($response->toString()),
            'Response convertion to string does not match original string'
        );
        $this->assertEquals(
            strtolower($responseStr),
            strtolower((string) $response),
            'Response convertion to string does not match original string'
        );
    }

    public function testGetHeaders()
    {
        $response = Response::fromString($this->readResponse('response_deflate'));
        $headers  = $response->getHeaders();

        $this->assertEquals(8, count($headers), 'Header count is not as expected');
        $this->assertEquals('Apache', $headers->get('Server')->getFieldValue(), 'Server header is not as expected');
        $this->assertEquals(
            'deflate',
            $headers->get('Content-encoding')->getFieldValue(),
            'Content-type header is not as expected'
        );
    }

    public function testGetVersion()
    {
        $response = Response::fromString($this->readResponse('response_chunked'));
        $this->assertEquals(1.1, $response->getVersion(), 'Version is expected to be 1.1');
    }

    public function testUnknownCode()
    {
        $responseStr = $this->readResponse('response_unknown');
        $response    = Response::fromString($responseStr);
        $this->assertEquals(550, $response->getStatusCode());
    }

    /**
     * Make sure a response with some leading whitespace in the response body
     * does not get modified (see Laminas-1924)
     */
    public function testLeadingWhitespaceBody()
    {
        $response = Response::fromString($this->readResponse('response_leadingws'));
        $this->assertEquals(
            $response->getContent(),
            "\r\n\t  \n\r\tx",
            'Extracted body is not identical to expected body'
        );
    }

    /**
     * Test that parsing a multibyte-encoded chunked response works.
     *
     * This can potentially fail on different PHP environments - for example
     * when mbstring.func_overload is set to overload strlen().
     */
    public function testMultibyteChunkedResponse()
    {
        $this->markTestSkipped('Looks like the headers are split with \n and the body with \r\n');
        $md5 = 'ab952f1617d0e28724932401f2d3c6ae';

        $response = Response::fromString($this->readResponse('response_multibyte_body'));
        $this->assertEquals($md5, md5($response->getBody()));
    }

    /**
     * Test automatic clean reason phrase
     */
    public function testOverrideReasonPraseByStatusCode()
    {
        $response = new Response();
        $response->setStatusCode(200);
        $response->setReasonPhrase('Custom reason phrase');
        $this->assertEquals('Custom reason phrase', $response->getReasonPhrase());
        $response->setStatusCode(400);
        $this->assertEquals('Bad Request', $response->getReasonPhrase());
    }

    public function testromStringFactoryCreatesSingleObjectWithHeaderFolding()
    {
        $request = Response::fromString("HTTP/1.1 200 OK\r\nFake: foo\r\n -bar");
        $headers = $request->getHeaders();
        $this->assertEquals(1, $headers->count());

        $header = $headers->get('fake');
        $this->assertInstanceOf(GenericHeader::class, $header);
        $this->assertEquals('Fake', $header->getFieldName());
        $this->assertEquals('foo-bar', $header->getFieldValue());
    }

    /**
     * @see http://en.wikipedia.org/wiki/HTTP_response_splitting
     *
     * @group ZF2015-04
     */
    public function testPreventsCRLFAttackWhenDeserializing()
    {
        $this->expectException(RuntimeException::class);
        Response::fromString(
            "HTTP/1.1 200 OK\r\nAllow: POST\r\nX-Foo: This\ris\r\n\r\nCRLF\nInjection"
        );
    }

    public function test100ContinueFromString()
    {
        $fixture = 'TOKEN=EC%XXXXXXXXXXXXX&TIMESTAMP=2017%2d10%2d10T09%3a02%3a55Z'
            . "&CORRELATIONID=XXXXXXXXXX&ACK=Success&VERSION=65%2e1&BUILD=XXXXXXXXXX\r\n";

        $request = Response::fromString($this->readResponse('response_100_continue'));
        $this->assertEquals(Response::STATUS_CODE_200, $request->getStatusCode());
        $this->assertEquals($fixture, $request->getBody());
    }

    /**
     * Helper function: read test response from file
     *
     * @param string $response
     * @return string
     */
    protected function readResponse($response)
    {
        return file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . $response);
    }
}
