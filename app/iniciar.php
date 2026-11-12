<?php
declare(strict_types=1);

require __DIR__ . '/nucleo.php';
require __DIR__ . '/acesso.php';
require __DIR__ . '/modulos.php';
require __DIR__ . '/regras.php';
require __DIR__ . '/crud.php';
require __DIR__ . '/graficos.php';
require __DIR__ . '/ui.php';
require __DIR__ . '/totp.php';
require __DIR__ . '/importar.php';
foreach (glob(__DIR__ . '/rotas/*.php') as $arquivo) require $arquivo;

date_default_timezone_set((string) (config('fuso') ?: 'America/Sao_Paulo'));
mb_internal_encoding('UTF-8');
ini_set('display_errors', config('debug') ? '1' : '0');

if (PHP_SAPI !== 'cli') {
    cabecalhos_seguranca();
    iniciar_sessao();
}
