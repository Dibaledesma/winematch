<?php

declare(strict_types=1);

namespace WineMatch\Tests;

/** Cliente HTTP mínimo sobre curl con bote de cookies, para las pruebas de extremo a extremo. */
final class Http
{
    /** @var array<string, string> */
    public array $cookies = [];
    /** @var array<string, list<string>> cabeceras de la ultima respuesta (nombres en minusculas) */
    public array $cabeceras = [];

    /** @param array<string, string> $datos @param array<string, string> $cabeceras */
    public function peticion(string $metodo, string $ruta, array $datos = [], array $cabeceras = []): array
    {
        $url = Servidor::url() . $ruta;
        $lineas = ['Accept: text/html'];
        if ($this->cookies !== []) {
            $pares = [];
            foreach ($this->cookies as $k => $v) {
                $pares[] = $k . '=' . $v;
            }
            $lineas[] = 'Cookie: ' . implode('; ', $pares);
        }
        if ($metodo === 'POST') {
            $lineas[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        foreach ($cabeceras as $k => $v) {
            $lineas[] = $k . ': ' . $v;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => $lineas,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($metodo === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($datos));
        }
        $respuesta = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        if ($respuesta === false) {
            throw new \RuntimeException('Fallo curl: ' . $info['error']);
        }

        $cab = substr($respuesta, 0, (int) $info['header_size']);
        $cuerpo = substr($respuesta, (int) $info['header_size']);
        $this->cabeceras = [];
        foreach (explode("\r\n", $cab) as $l) {
            if (str_contains($l, ':')) {
                [$k, $v] = explode(':', $l, 2);
                $this->cabeceras[strtolower(trim($k))][] = trim($v);
            }
        }
        foreach ($this->cabeceras['set-cookie'] ?? [] as $sc) {
            if (preg_match('/^([^=]+)=([^;]*)/', $sc, $m) === 1) {
                if ($m[2] === '' || str_contains($sc, 'deleted')) {
                    unset($this->cookies[$m[1]]);
                } else {
                    $this->cookies[$m[1]] = $m[2];
                }
            }
        }
        return ['status' => (int) $info['http_code'], 'body' => $cuerpo];
    }

    public function cabecera(string $nombre): ?string
    {
        return $this->cabeceras[strtolower($nombre)][0] ?? null;
    }

    public function origen(): string
    {
        return Servidor::url();
    }

    /** Registra un usuario y deja el bote de cookies con la sesion iniciada. */
    public function registrarYEntrar(string $email, string $password = 'vino-tinto-de-casa'): array
    {
        $r = $this->peticion('POST', '/registro', ['nombre' => 'Tester', 'email' => $email, 'password' => $password], ['Origin' => $this->origen()]);
        if ($r['status'] !== 303) {
            throw new \RuntimeException('Registro HTTP falló: ' . $r['status'] . ' ' . substr($r['body'], 0, 300));
        }
        return $r;
    }
}
