<?php

declare(strict_types=1);

namespace IranMessenger\Tests\Http;

use IranMessenger\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testDecodesJsonObject(): void
    {
        $response = new Response(200, '{"ok":true,"result":{"id":1}}');

        self::assertTrue($response->isSuccessful());
        self::assertSame(200, $response->statusCode());
        self::assertSame(['ok' => true, 'result' => ['id' => 1]], $response->json());
    }

    public function testInvalidJsonReturnsNull(): void
    {
        $response = new Response(502, '<html>Bad gateway</html>');

        self::assertFalse($response->isSuccessful());
        self::assertNull($response->json());
        self::assertSame('<html>Bad gateway</html>', $response->body());
    }

    public function testJsonScalarIsNotTreatedAsObject(): void
    {
        self::assertNull((new Response(200, '"OK"'))->json());
    }
}
