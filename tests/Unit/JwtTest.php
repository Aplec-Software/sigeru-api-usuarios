<?php

namespace Tests\Unit;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Jwt;
use Tests\Support\TestCase;

class JwtTest extends TestCase
{
    private function clavePublica(): string
    {
        return openssl_pkey_get_details(openssl_pkey_get_private(self::$clavePrivada))['key'];
    }

    public function testFirmaYVerificaUnToken(): void
    {
        $token = Jwt::crear(['iss' => Auth::EMISOR, 'sub' => 7, 'exp' => time() + 60], self::$clavePrivada);
        $payload = Jwt::verificar($token, $this->clavePublica(), Auth::EMISOR);
        $this->assertSame(7, $payload['sub']);
    }

    public function testRechazaUnTokenModificado(): void
    {
        $token = Jwt::crear(['iss' => Auth::EMISOR, 'sub' => 7, 'rol' => 'vecino', 'exp' => time() + 60], self::$clavePrivada);
        [$cab, , $firma] = explode('.', $token);
        $falso = rtrim(strtr(base64_encode(json_encode(['iss' => Auth::EMISOR, 'sub' => 7, 'rol' => 'administrador', 'exp' => time() + 60])), '+/', '-_'), '=');
        $this->expectException(HttpException::class);
        Jwt::verificar("$cab.$falso.$firma", $this->clavePublica(), Auth::EMISOR);
    }

    public function testRechazaElAlgoritmoNone(): void
    {
        $cab = rtrim(base64_encode(json_encode(['alg' => 'none', 'typ' => 'JWT'])), '=');
        $pay = rtrim(base64_encode(json_encode(['iss' => Auth::EMISOR, 'sub' => 1, 'exp' => time() + 60])), '=');
        $this->expectException(HttpException::class);
        Jwt::verificar("$cab.$pay.", $this->clavePublica(), Auth::EMISOR);
    }

    public function testRechazaUnTokenVencido(): void
    {
        $token = Jwt::crear(['iss' => Auth::EMISOR, 'sub' => 7, 'exp' => time() - 10], self::$clavePrivada);
        $this->expectException(HttpException::class);
        Jwt::verificar($token, $this->clavePublica(), Auth::EMISOR);
    }
}
