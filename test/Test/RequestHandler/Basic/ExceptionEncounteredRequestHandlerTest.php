<?php

namespace Winegard\AmazonAlexa\Test\RequestHandler\Basic;

use Winegard\AmazonAlexa\Helper\ResponseHelper;
use Winegard\AmazonAlexa\Request\Request;
use Winegard\AmazonAlexa\RequestHandler\Basic\ExceptionEncounteredRequestHandler;
use Winegard\AmazonAlexa\Response\Response;
use Winegard\AmazonAlexa\Response\ResponseBody;
use PHPUnit\Framework\TestCase;

/**
 * @author Nicholas Bekeris <nick.bekeris@winegard.com>
 */
class ExceptionEncounteredRequestHandlerTest extends TestCase
{
    public function testSupportsRequestAndOutput()
    {
        $responseHelper = $this->getMockBuilder(ResponseHelper::class)
                               ->disableOriginalConstructor()
                               ->getMock();

        $request        = Request::fromAmazonRequest('{"request":{"type":"System.ExceptionEncountered"}}', 'true', 'true');
        $output         = 'Just a simple Test';
        $requestHandler = new ExceptionEncounteredRequestHandler($responseHelper, $output, ['my_skill_id']);

        $responseBody               = new ResponseBody();
        $responseBody->outputSpeech = $output;
        $responseHelper->expects(static::once())->method('respond')->willReturn(new Response([], '1.0', $responseBody));

        static::assertTrue($requestHandler->supportsRequest($request));
        static::assertSame($output, $requestHandler->handleRequest($request)->response->outputSpeech);
    }

    public function testNotSupportsRequest()
    {
        $request        = Request::fromAmazonRequest('{"request":{"type":"IntentRequest", "intent":{"name":"InvalidIntent"}}}', 'true', 'true');
        $output         = 'Just a simple Test';
        $requestHandler = new ExceptionEncounteredRequestHandler(new ResponseHelper(), $output, ['my_skill_id']);

        static::assertFalse($requestHandler->supportsRequest($request));
    }
}
