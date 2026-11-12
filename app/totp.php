<?php
declare(strict_types=1);

// Código de 6 números do aplicativo autenticador (TOTP, RFC 6238): Google Authenticator, Authy, Microsoft…

function base32_decodificar(string $s): string
{
    $alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s));
    $bits = '';
    foreach (str_split($s) as $c) $bits .= str_pad(decbin(strpos($alfabeto, $c)), 5, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}

function base32_codificar(string $dados): string
{
    $alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($dados) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $grupo) $out .= $alfabeto[bindec(str_pad($grupo, 5, '0'))];
    return $out;
}

function totp_novo_segredo(): string
{
    return base32_codificar(random_bytes(20));
}

function totp_codigo(string $segredo, int $passo): string
{
    $hash = hash_hmac('sha1', pack('N*', 0, $passo), base32_decodificar($segredo), true);
    $o = ord($hash[19]) & 0x0f;
    $n = ((ord($hash[$o]) & 0x7f) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);
    return str_pad((string) ($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Aceita o código atual e o de 30 s antes/depois (relógio do celular um pouco fora). */
function totp_conferir(string $segredo, string $codigo): bool
{
    $codigo = preg_replace('/\D/', '', $codigo);
    if (strlen($codigo) !== 6) return false;
    $agora = intdiv(time(), 30);
    foreach ([-1, 0, 1] as $d) if (hash_equals(totp_codigo($segredo, $agora + $d), $codigo)) return true;
    return false;
}

function totp_uri(string $segredo, string $email): string
{
    return 'otpauth://totp/' . rawurlencode('Pecuária Digital:' . $email) . '?secret=' . $segredo . '&issuer=' . rawurlencode('Pecuária Digital') . '&digits=6&period=30';
}
