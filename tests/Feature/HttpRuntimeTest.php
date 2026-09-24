<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;
use Tests\MongoTestCase;

class HttpRuntimeTest extends MongoTestCase
{
    public function test_native_multipart_put_and_cors_over_http(): void
    {
        $o = $this->organization();
        $authorization = $this->token($o)['Authorization'];
        $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $error);
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        $command = [PHP_BINARY];
        if ($extension = getenv('TEST_PHP_EXTENSION')) {
            array_push($command, '-d', 'extension='.$extension);
        }
        array_push($command, '-S', '127.0.0.1:'.$port, base_path('tests/fixtures/http-router.php'));
        $server = new Process($command, base_path(), ['APP_ENV' => 'testing', 'MONGODB_DATABASE' => config('database.connections.mongodb.database'), 'MONGODB_URI' => config('database.connections.mongodb.dsn'), 'APP_KEY' => config('app.key'), 'JWT_SECRET' => config('fundraiser.jwt_secret'), 'LOG_CHANNEL' => 'null']);
        $server->start();
        try {
            $ready = false;
            for ($i = 0; $i < 100; $i++) {
                $conn = @fsockopen('127.0.0.1', $port);
                if ($conn) {
                    fclose($conn);
                    $ready = true;
                    break;
                } usleep(20000);
            }
            $this->assertTrue($ready, $server->getErrorOutput());
            $file = UploadedFile::fake()->image('test.png');
            $boundary = 'nabaku-test-boundary';
            $body = "--$boundary\r\nContent-Disposition: form-data; name=\"file\"; filename=\"test.png\"\r\nContent-Type: image/png\r\n\r\n".file_get_contents($file->getRealPath())."\r\n--$boundary--\r\n";
            $context = stream_context_create(['http' => ['method' => 'PUT', 'header' => "Authorization: $authorization\r\nAccept: application/json\r\nOrigin: http://localhost:3000\r\nContent-Type: multipart/form-data; boundary=$boundary", 'content' => $body, 'ignore_errors' => true, 'timeout' => 10]]);
            $stream = fopen('http://127.0.0.1:'.$port.'/api/v1/file/upload', 'r', false, $context);
            $headers = stream_get_meta_data($stream)['wrapper_data'];
            $response = stream_get_contents($stream);
            fclose($stream);
            $this->assertStringContainsString('200', $headers[0], $response);
            $this->assertSame('https://images.example.test/native-put.png', json_decode($response, true)['data']['url']);
            $this->assertTrue((bool) array_filter($headers, fn ($h) => strtolower($h) === 'access-control-allow-origin: http://localhost:3000'));
            $this->assertTrue((bool) array_filter($headers, fn ($h) => str_starts_with(strtolower($h), 'x-request-id:')));
        } finally {
            $server->stop();
        }
    }
}
