<?php
// Preenche uma propriedade com dados de demonstração (animais, leite, vacinas, partos, dinheiro, anotações).
// Uso:  php scripts/demonstracao.php email@da.conta "Nome da propriedade"
// Útil para ver o sistema funcionando antes de lançar os dados de verdade. Não apaga nada.
declare(strict_types=1);

require __DIR__ . '/../app/iniciar.php';

[$_, $email, $nomeProp] = $argv + [null, null, 'Fazenda Demonstração'];
if (!$email) exit("Uso: php scripts/demonstracao.php email@da.conta \"Nome da propriedade\"\n");
$uid = valor('SELECT id FROM usuarios WHERE email = ?', [mb_strtolower($email)]);
if (!$uid) exit("Não achei a conta $email. Crie a conta no site primeiro.\n");

mt_srand(42);
$agora = agora();
$p = valor('SELECT id FROM propriedades WHERE proprietario_id = ? AND nome = ?', [$uid, $nomeProp]);
if (!$p) {
    $p = inserir('propriedades', ['proprietario_id' => $uid, 'nome' => $nomeProp, 'cidade' => 'Manaus', 'estado' => 'AM', 'created_at' => $agora, 'updated_at' => $agora]);
    inserir('propriedade_membros', ['propriedade_id' => $p, 'user_id' => $uid, 'role' => 'proprietario', 'created_at' => $agora]);
}
$GLOBALS['PID_CLI'] = $p;

transacao(function () use ($p, $agora) {
    $lote = [];
    foreach ([['Lactação', 'Vacas em ordenha'], ['Recria', 'Novilhas e garrotes'], ['Maternidade', 'Vacas perto do parto']] as [$n, $f]) {
        $lote[$n] = inserir('lotes', ['propriedade_id' => $p, 'nome' => $n, 'finalidade' => $f, 'created_at' => $agora, 'updated_at' => $agora]);
    }
    $vac = [];
    foreach ([['Febre aftosa', 180], ['Brucelose', null], ['Raiva', 365], ['Clostridiose', 365], ['Vermífugo', 90]] as [$n, $d]) {
        $vac[$n] = inserir('tipos_vacinas', ['propriedade_id' => $p, 'nome' => $n, 'intervalo_dias' => $d, 'created_at' => $agora, 'updated_at' => $agora]);
    }
    $nomes = ['Mimosa', 'Estrela', 'Malhada', 'Pintada', 'Boneca', 'Jabuticaba', 'Serena', 'Graúna', 'Valente', 'Branquinha', 'Pérola', 'Formosa', 'Cigana', 'Princesa', 'Morena', 'Faceira', 'Dengosa', 'Chuvisco', 'Lua', 'Aurora'];
    $racas = ['Girolando', 'Holandesa', 'Gir', 'Jersey', 'Nelore'];
    $vacas = [];
    $jovens = [];
    for ($i = 0; $i < 20; $i++) {
        $id = inserir('animais', ['propriedade_id' => $p, 'numero_brinco' => (string) (1100 + $i * 7), 'nome' => $nomes[$i], 'sexo' => 'F', 'raca' => $racas[$i % 5],
            'data_nascimento' => date('Y-m-d', strtotime('-' . (3 + $i % 5) . ' years -' . ($i * 11) . ' days')), 'lote_id' => $i < 14 ? $lote['Lactação'] : $lote['Maternidade'],
            'situacao' => 'ativo', 'created_at' => $agora, 'updated_at' => $agora]);
        $vacas[] = $id;
    }
    $touro = inserir('animais', ['propriedade_id' => $p, 'numero_brinco' => '1355', 'nome' => 'Trovão', 'sexo' => 'M', 'raca' => 'Nelore', 'data_nascimento' => date('Y-m-d', strtotime('-5 years')), 'situacao' => 'ativo', 'created_at' => $agora, 'updated_at' => $agora]);
    for ($i = 0; $i < 10; $i++) {
        $sexo = $i % 2 ? 'M' : 'F';
        $nasc = date('Y-m-d', strtotime('-' . (40 + $i * 35) . ' days'));
        $mae = $vacas[$i];
        $b = inserir('animais', ['propriedade_id' => $p, 'numero_brinco' => (string) (1300 + $i * 7), 'nome' => $i % 3 ? null : 'Bezerra ' . ($i + 1), 'sexo' => $sexo, 'raca' => 'Girolando',
            'data_nascimento' => $nasc, 'lote_id' => $lote['Recria'], 'mae_id' => $mae, 'pai_id' => $touro, 'situacao' => 'ativo', 'created_at' => $agora, 'updated_at' => $agora]);
        inserir('nascimentos', ['propriedade_id' => $p, 'vaca_id' => $mae, 'bezerro_id' => $b, 'data_nascimento' => $nasc, 'sexo' => $sexo, 'lote_id' => $lote['Recria'], 'created_at' => $agora, 'updated_at' => $agora]);
        $peso = 32 + mt_rand(0, 6);
        for ($d = 0; $d <= dias_entre($nasc, hoje()) && $i > 2; $d += 30) {
            inserir('pesagens', ['propriedade_id' => $p, 'animal_id' => $b, 'data' => somar_dias($nasc, $d), 'peso_kg' => $peso + $d * 0.72, 'created_at' => $agora, 'updated_at' => $agora]);
        }
        $jovens[] = $b;
    }
    $vendido = inserir('animais', ['propriedade_id' => $p, 'numero_brinco' => '1369', 'nome' => 'Serena', 'sexo' => 'F', 'raca' => 'Holandesa', 'situacao' => 'ativo', 'created_at' => $agora, 'updated_at' => $agora]);

    // Vacinas: algumas em dia, duas atrasadas e o lote de recria.
    foreach (array_slice($vacas, 0, 18) as $k => $v) {
        $data = date('Y-m-d', strtotime('-' . (60 + $k * 9) . ' days'));
        inserir('vacinacoes', ['propriedade_id' => $p, 'animal_id' => $v, 'tipo_vacina_id' => $vac['Febre aftosa'], 'data_aplicacao' => $data, 'proxima_aplicacao' => somar_dias($data, 180), 'responsavel' => 'José Pereira', 'created_at' => $agora, 'updated_at' => $agora]);
    }
    inserir('vacinacoes', ['propriedade_id' => $p, 'lote_id' => $lote['Recria'], 'tipo_vacina_id' => $vac['Clostridiose'], 'data_aplicacao' => date('Y-m-d', strtotime('-362 days')), 'proxima_aplicacao' => date('Y-m-d', strtotime('+3 days')), 'responsavel' => 'José Pereira', 'created_at' => $agora, 'updated_at' => $agora]);

    // Inseminações: partos a caminho e algumas em andamento (retorno de cio).
    foreach ([[14, -263, 'confirmada'], [15, -250, 'confirmada'], [16, -225, 'confirmada'], [17, -180, 'confirmada'], [18, -110, 'em_andamento'], [19, -18, 'em_andamento'], [13, -16, 'em_andamento'], [12, -300, 'falhou']] as [$i, $dias, $sit]) {
        $data = date('Y-m-d', strtotime("$dias days"));
        inserir('inseminacoes', ['propriedade_id' => $p, 'animal_id' => $vacas[$i], 'data_inseminacao' => $data, 'reprodutor_semen' => $i % 2 ? 'Sêmen Gir Leiteiro' : 'Trovão', 'previsao_parto' => somar_dias($data, 283), 'situacao' => $sit, 'created_at' => $agora, 'updated_at' => $agora]);
    }

    // Leite: 12 meses, uma linha por vaca a cada 3 dias (com o mês atual mais recente).
    for ($d = 360; $d >= 0; $d -= 3) {
        $data = somar_dias(hoje(), -$d);
        foreach (array_slice($vacas, 0, 8) as $k => $v) {
            $litros = round(14 + 4 * sin(($d + $k * 20) / 58) + mt_rand(0, 30) / 10, 1);
            inserir('producao_leite', ['propriedade_id' => $p, 'animal_id' => $v, 'lote_id' => $lote['Lactação'], 'data' => $data, 'litros' => $litros, 'valor_litro' => 2.6, 'created_at' => $agora, 'updated_at' => $agora]);
        }
    }

    // Dinheiro: receitas e despesas de 12 meses.
    for ($m = 11; $m >= 0; $m--) {
        $dia = date('Y-m-10', strtotime(date('Y-m-01') . " -$m months"));
        if ($dia > hoje()) $dia = hoje();
        inserir('movimentacoes_financeiras', ['propriedade_id' => $p, 'tipo' => 'receita', 'categoria' => 'Leite', 'descricao' => 'Venda de leite ao laticínio', 'valor' => 9000 + mt_rand(0, 4000), 'data' => $dia, 'created_at' => $agora, 'updated_at' => $agora]);
        inserir('movimentacoes_financeiras', ['propriedade_id' => $p, 'tipo' => 'despesa', 'categoria' => 'Ração e sal', 'descricao' => 'Ração e sal mineral', 'valor' => 3500 + mt_rand(0, 6000), 'data' => $dia, 'created_at' => $agora, 'updated_at' => $agora]);
        if ($m % 4 === 1) inserir('movimentacoes_financeiras', ['propriedade_id' => $p, 'tipo' => 'investimento', 'categoria' => 'Estrutura', 'descricao' => 'Reforma do curral', 'valor' => 7000, 'data' => $dia, 'created_at' => $agora, 'updated_at' => $agora]);
    }
    $_SERVER['REQUEST_METHOD'] = 'CLI';
    $venda = inserir('vendas', ['propriedade_id' => $p, 'data' => somar_dias(hoje(), -5), 'animal_id' => $vendido, 'item' => 'Vaca Serena', 'quantidade' => 1, 'valor' => 4800, 'comprador' => 'Fazenda Esperança', 'categoria' => 'Animais', 'created_at' => $agora, 'updated_at' => $agora]);
    regras_depois('vendas', $venda, ['animal_id' => $vendido, 'item' => 'Vaca Serena', 'categoria' => 'Animais', 'valor' => 4800, 'data' => somar_dias(hoje(), -5)], null);
    $compra = inserir('compras', ['propriedade_id' => $p, 'data' => somar_dias(hoje(), -3), 'produto_animal' => 'Sal mineral 30 kg', 'quantidade' => 10, 'valor' => 1290, 'fornecedor' => 'Agropecuária Central', 'categoria' => 'Insumos', 'created_at' => $agora, 'updated_at' => $agora]);
    regras_depois('compras', $compra, ['produto_animal' => 'Sal mineral 30 kg', 'categoria' => 'Insumos', 'valor' => 1290, 'data' => somar_dias(hoje(), -3)], null);

    foreach ([[0, 0, 'Cio observado de manhã. Avisar o técnico para inseminar amanhã.', 'Inquieta, montando nas outras', null, 1],
              [null, 0, 'Trocar o sal dos cochos do pasto 2.', null, 'Sal mineral acabando', 0],
              [3, -1, 'Produção caiu na ordenha da tarde. Conferir mastite.', 'Mais quieta que o normal', null, 1],
              [null, -2, 'Cerca do piquete 4 arrumada.', null, null, 0]] as [$a, $d, $obs, $comp, $alim, $marc]) {
        inserir('anotacoes_producao', ['propriedade_id' => $p, 'animal_id' => $a === null ? null : $vacas[$a], 'lote_id' => $a === null ? null : $lote['Lactação'], 'data' => somar_dias(hoje(), $d),
            'observacao' => $obs, 'comportamento' => $comp, 'alimentacao' => $alim, 'producao' => $a !== null ? 12.5 : null, 'marcada' => $marc, 'created_at' => $agora, 'updated_at' => $agora]);
    }
});
echo "Pronto: dados de demonstração em \"$nomeProp\".\n";
