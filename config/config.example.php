<?php
// Copie para config/config.php e preencha. O script deploy/instalar.sh faz isso sozinho.
// Este arquivo fica FORA da pasta public/: o navegador nunca consegue abri-lo.
return [
    'nome_site' => 'Pecuária Digital',
    // Endereço público, sem barra no fim. Ex.: https://pecuariadigital.com.br
    'url' => 'http://localhost:8000',

    'db' => [
        'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=pecuaria;charset=utf8mb4',
        'usuario' => 'pecuaria',
        'senha' => 'troque-esta-senha',
    ],

    // true só no seu computador. Em produção deixe false (não mostra detalhes de erro).
    'debug' => false,

    // Fuso usado para "hoje", vencimentos e relatórios.
    'fuso' => 'America/Sao_Paulo',

    // Permite que qualquer pessoa crie conta pela tela de cadastro.
    'cadastro_aberto' => true,
];
