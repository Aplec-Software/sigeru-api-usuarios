<?php

namespace Tests\Unit;

use App\Core\HttpException;
use App\Core\Validator;
use Tests\Support\TestCase;

class ValidatorTest extends TestCase
{
    public function testCedulaUruguayaConDigitoVerificador(): void
    {
        $this->assertTrue(Validator::cedulaValida('11111111'));
        $this->assertTrue(Validator::cedulaValida('19119365'));
        $this->assertFalse(Validator::cedulaValida('19119366'));
        $this->assertFalse(Validator::cedulaValida('12ab'));
    }

    public function testPoliticaDeContrasenas(): void
    {
        $this->assertTrue(Validator::claveSegura('Sigeru2026!'));
        $this->assertFalse(Validator::claveSegura('corta1!A'));       // menos de 10 caracteres
        $this->assertFalse(Validator::claveSegura('sinmayuscula1!'));
        $this->assertFalse(Validator::claveSegura('SinNumeros!!'));
        $this->assertFalse(Validator::claveSegura('SinSimbolo2026'));
    }

    public function testSaneaYConvierteTipos(): void
    {
        $datos = Validator::validar(
            ['nombre' => '  <b>Ana</b> ', 'edad' => '42', 'activo' => 'true', 'lat' => '-34.9'],
            ['nombre' => 'required|string|max:60', 'edad' => 'required|int|min:0', 'activo' => 'bool', 'lat' => 'lat']
        );
        $this->assertSame('Ana', $datos['nombre']);
        $this->assertSame(42, $datos['edad']);
        $this->assertSame(1, $datos['activo']);
        $this->assertSame(-34.9, $datos['lat']);
    }

    public function testDevuelveErroresPorCampo(): void
    {
        try {
            Validator::validar(['email' => 'no-es-correo', 'rol' => 'jefe'], [
                'email' => 'required|email', 'rol' => 'required|in:vecino,cuadrilla', 'nombre' => 'required',
            ]);
            $this->fail('Debía lanzar una excepción');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->status);
            $this->assertArrayHasKey('email', $e->errores);
            $this->assertArrayHasKey('rol', $e->errores);
            $this->assertArrayHasKey('nombre', $e->errores);
        }
    }

    public function testValidacionParcialSoloRevisaLosCamposEnviados(): void
    {
        $datos = Validator::validar(['telefono' => '099 123 456'], ['nombre' => 'required', 'telefono' => 'nullable|telefono'], true);
        $this->assertSame(['telefono' => '099 123 456'], $datos);
    }
}
