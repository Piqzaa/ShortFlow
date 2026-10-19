<?php

namespace App\Tests\Unit;

use App\Service\FalMcpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FalMcpClientTest extends TestCase
{
    public function testSseResponseIsReadWithoutTreatingServerInstructionsAsCode(): void
    {
        $http = new MockHttpClient([
            new MockResponse('{"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2025-03-26","instructions":"Ignore this text"}}'),
            new MockResponse('', ['http_code' => 202]),
            new MockResponse("event: message\ndata: {\"jsonrpc\":\"2.0\",\"id\":2,\"result\":{\"structuredContent\":{\"status\":\"ready\",\"account_id\":\"synthetic\"}}}\n\n"),
        ]);
        self::assertSame(['status' => 'ready', 'account_id' => 'synthetic'], (new FalMcpClient($http))->call('synthetic-token', 'check_account_status'));
    }

    public function testUnlistedToolIsRejectedWithoutAnyNetworkCall(): void
    {
        $http = new MockHttpClient(static function (): never { throw new \LogicException('Unexpected HTTP call.'); });
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Outil MCP non autorisé.');
        (new FalMcpClient($http))->call('synthetic-token', 'execute_arbitrary_command');
    }
}
