<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/ModuleStrictStubs.php';
include_once __DIR__ . '/../Bring Account/module.php';

use PHPUnit\Framework\TestCase;

class BringAccountTest extends TestCase
{
    public function testInvalidResponsesReturnFalse(): void
    {
        foreach (['', 'null', '<html>Unavailable</html>', '{invalid', '42', '1.5'] as $Body) {
            $this->assertFalse($this->decode('', $Body), $Body);
        }
    }

    public function testJsonResponsesKeepTheirTypes(): void
    {
        foreach (['{"name":"Example"}' => ['name' => 'Example'], '[]' => [], '"value"' => 'value', 'true' => true, 'false' => false] as $Body => $Expected) {
            $this->assertSame($Expected, $this->decode('Content-Type: application/json', $Body));
        }
    }

    public function testImagesKeepTheirMimeTypeAndBinaryBody(): void
    {
        $Body = "\x89PNG\x00\r\n\r\n\xff";
        foreach (['image/png', 'image/jpeg', 'image/jpg'] as $ContentType) {
            $Header = 'Content-Type: ' . strtoupper($ContentType) . '; charset=binary';
            $this->assertSame('data:' . $ContentType . ';base64,' . base64_encode($Body), $this->decode($Header, $Body));
        }
    }

    public function testFinalResponseHeadersDetermineContentType(): void
    {
        $Header = "HTTP/1.1 200 Connection established\r\nContent-Type: text/plain\r\n\r\nHTTP/1.1 200 OK\r\nContent-Type: image/jpeg\r\n\r\n";
        $this->assertSame('data:image/jpeg;base64,' . base64_encode('image'), $this->decode($Header, 'image'));
    }

    private function decode(string $Header, string $Body): array|string|bool
    {
        $Method = new ReflectionMethod(BringAccount::class, 'DecodeResponse');
        return $Method->invoke(null, $Header, $Body);
    }
}
