<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Regras de negócio que no original ficavam em gatilhos do banco. Rodam dentro da mesma transação
// da gravação: ou tudo grava, ou nada.
// ---------------------------------------------------------------------------

/** Confere e completa os dados antes de gravar. Devolve os dados ou a mensagem de erro. */
function regras_antes(string $tabela, array $d, ?array $antigo): array|string
{
    $hojeMais1 = somar_dias(hoje(), 1);
    foreach (['data', 'data_aplicacao', 'data_inseminacao', 'data_nascimento'] as $c) {
        if (!empty($d[$c]) && $d[$c] > $hojeMais1 && $tabela !== 'animais') return 'A data não pode ser no futuro.';
    }
    switch ($tabela) {
        case 'animais':
            if (!empty($d['data_nascimento']) && $d['data_nascimento'] > $hojeMais1) return 'A data de nascimento não pode ser no futuro.';
            if ($antigo && (($d['mae_id'] ?? null) === $antigo['id'] || ($d['pai_id'] ?? null) === $antigo['id'])) return 'Um animal não pode ser pai ou mãe dele mesmo.';
            break;
        case 'vacinacoes':
            // Lote é contexto quando há um animal; aplicação coletiva usa só o lote.
            if (!empty($d['animal_id'])) $d['lote_id'] = null;
            if (empty($d['animal_id']) && empty($d['lote_id'])) return 'Informe o brinco ou nome de um animal, ou o nome de um lote.';
            if (empty($d['proxima_aplicacao'])) {
                $intervalo = (int) valor('SELECT intervalo_dias FROM tipos_vacinas WHERE id = ?', [$d['tipo_vacina_id']]);
                $d['proxima_aplicacao'] = $intervalo > 0 ? somar_dias($d['data_aplicacao'], $intervalo) : null;
            } elseif ($d['proxima_aplicacao'] < $d['data_aplicacao']) {
                return 'A próxima aplicação não pode ser antes da aplicação.';
            }
            break;
        case 'inseminacoes':
            if (empty($d['previsao_parto'])) {
                $dias = (int) (valor('SELECT periodo_gestacao_dias FROM propriedades WHERE id = ?', [pid()]) ?: 283);
                $d['previsao_parto'] = somar_dias($d['data_inseminacao'], $dias);
            }
            if ($d['previsao_parto'] <= $d['data_inseminacao']) return 'A previsão de parto deve ser posterior à data da inseminação.';
            break;
        case 'nascimentos':
            if ($d['vaca_id'] === $d['bezerro_id']) return 'A vaca e o bezerro devem ser animais diferentes.';
            break;
        case 'producao_leite':
            if ((float) $d['litros'] < 0 || (float) $d['valor_litro'] < 0) return 'Litros e valor não podem ser negativos.';
            break;
    }
    return $d;
}

/** Depois de gravar: lançamentos automáticos e ligações entre cadastros. */
function regras_depois(string $tabela, string $id, array $d, ?array $antigo): void
{
    switch ($tabela) {
        case 'compras':
        case 'vendas':
            sincronizar_operacao_financeira($tabela, $id, $d);
            if ($tabela === 'vendas') sincronizar_venda_animal($d['animal_id'] ?? null, $antigo['animal_id'] ?? null, $id);
            break;
        case 'nascimentos':
            if ($antigo && $antigo['bezerro_id'] !== $d['bezerro_id']) desfazer_nascimento($antigo);
            q('UPDATE animais SET mae_id = ?, lote_id = COALESCE(?, lote_id), updated_at = ? WHERE id = ? AND propriedade_id = ?',
                [$d['vaca_id'], $d['lote_id'] ?? null, agora(), $d['bezerro_id'], pid()]);
            break;
    }
}

function regras_ao_excluir(string $tabela, array $linha): void
{
    switch ($tabela) {
        case 'compras':
        case 'vendas':
            q('DELETE FROM movimentacoes_financeiras WHERE origem_tipo = ? AND origem_id = ?', [$tabela === 'vendas' ? 'venda' : 'compra', $linha['id']]);
            if ($tabela === 'vendas') sincronizar_venda_animal(null, $linha['animal_id'] ?? null, $linha['id']);
            break;
        case 'nascimentos':
            desfazer_nascimento($linha);
            break;
    }
    inserir('registros_excluidos', ['propriedade_id' => $linha['propriedade_id'], 'tabela' => $tabela, 'registro_id' => $linha['id'], 'excluido_em' => agora()], false);
}

/** Compra vira despesa; venda vira receita. Um lançamento por operação (editar atualiza o mesmo). */
function sincronizar_operacao_financeira(string $tabela, string $id, array $d): void
{
    $venda = $tabela === 'vendas';
    $origem = $venda ? 'venda' : 'compra';
    $fonte = trim((string) ($venda ? ($d['item'] ?? '') : ($d['produto_animal'] ?? ''))) ?: ($venda ? 'Venda sem item' : 'Compra sem produto');
    $dados = [
        'propriedade_id' => pid(), 'tipo' => $venda ? 'receita' : 'despesa', 'categoria' => $d['categoria'],
        'descricao' => mb_substr(($venda ? 'Venda: ' : 'Compra: ') . $fonte, 0, 255), 'valor' => $d['valor'], 'data' => $d['data'],
        'observacao' => $d['observacao'] ?? null, 'updated_at' => agora(),
    ];
    $mov = valor('SELECT id FROM movimentacoes_financeiras WHERE origem_tipo = ? AND origem_id = ?', [$origem, $id]);
    if ($mov) {
        atualizar('movimentacoes_financeiras', $dados, 'id = ?', [$mov]);
    } else {
        $mov = inserir('movimentacoes_financeiras', $dados + ['origem_tipo' => $origem, 'origem_id' => $id, 'created_at' => agora()]);
    }
    q("UPDATE $tabela SET movimentacao_id = ? WHERE id = ?", [$mov, $id]);
}

/** Animal vendido sai do rebanho ativo (e do lote); se a venda some, ele volta a ser ativo. */
function sincronizar_venda_animal(?string $novo, ?string $antigo, string $vendaId): void
{
    if ($antigo && $antigo !== $novo) {
        $outra = valor('SELECT 1 FROM vendas WHERE animal_id = ? AND id <> ?', [$antigo, $vendaId]);
        if (!$outra) q("UPDATE animais SET situacao = 'ativo', updated_at = ? WHERE id = ? AND propriedade_id = ? AND situacao = 'vendido'", [agora(), $antigo, pid()]);
    }
    if ($novo) q("UPDATE animais SET situacao = 'vendido', lote_id = NULL, updated_at = ? WHERE id = ? AND propriedade_id = ?", [agora(), $novo, pid()]);
}

function desfazer_nascimento(array $n): void
{
    q('UPDATE animais SET mae_id = CASE WHEN mae_id = ? THEN NULL ELSE mae_id END,
         lote_id = CASE WHEN ? IS NOT NULL AND lote_id = ? THEN NULL ELSE lote_id END, updated_at = ?
       WHERE id = ? AND propriedade_id = ?',
        [$n['vaca_id'], $n['lote_id'], $n['lote_id'], agora(), $n['bezerro_id'], $n['propriedade_id']]);
}

// ----- Consultas que no original eram funções do banco ---------------------

/**
 * Situação de cada aplicação de vacina (como a visão v_vacinacoes_status):
 * substituida = já teve reforço; vencida/proxima (15 dias)/em_dia pela próxima dose.
 */
function sql_vacinas_status(): string
{
    $h = db()->quote(hoje());
    $h15 = db()->quote(somar_dias(hoje(), 15));
    return "SELECT v.*, NOT EXISTS (
              SELECT 1 FROM vacinacoes w
              WHERE w.propriedade_id = v.propriedade_id AND w.tipo_vacina_id = v.tipo_vacina_id AND w.id <> v.id
                AND ((v.animal_id IS NOT NULL AND w.animal_id = v.animal_id) OR (v.animal_id IS NULL AND w.animal_id IS NULL AND w.lote_id = v.lote_id))
                AND (w.data_aplicacao > v.data_aplicacao OR (w.data_aplicacao = v.data_aplicacao AND (w.created_at > v.created_at OR (w.created_at = v.created_at AND w.id > v.id))))
            ) AS ultima_dose,
            CASE WHEN v.proxima_aplicacao IS NULL THEN 'em_dia' WHEN v.proxima_aplicacao < $h THEN 'vencida'
                 WHEN v.proxima_aplicacao <= $h15 THEN 'proxima' ELSE 'em_dia' END AS status_calculado
          FROM vacinacoes v";
}

/** Resumo do Início (dashboard_resumo). */
function dashboard_resumo(string $p): array
{
    $h = hoje();
    $ini = date('Y-m-01');
    $fim = date('Y-m-01', strtotime($ini . ' +1 month'));
    $n = fn(string $sql, array $par) => (float) (valor($sql, $par) ?? 0);
    return [
        'animais_ativos' => (int) $n("SELECT COUNT(*) FROM animais WHERE propriedade_id = ? AND situacao = 'ativo'", [$p]),
        'leite_hoje' => $n('SELECT COALESCE(SUM(litros), 0) FROM producao_leite WHERE propriedade_id = ? AND data = ?', [$p, $h]),
        'vacinas_vencidas' => (int) $n('SELECT COUNT(*) FROM (' . sql_vacinas_status() . ") s WHERE s.propriedade_id = ? AND s.ultima_dose AND s.status_calculado = 'vencida'", [$p]),
        'partos_30d' => (int) $n("SELECT COUNT(*) FROM inseminacoes WHERE propriedade_id = ? AND situacao IN ('em_andamento','confirmada') AND previsao_parto BETWEEN ? AND ?", [$p, $h, somar_dias($h, 30)]),
        'receitas_mes' => $n("SELECT COALESCE(SUM(valor), 0) FROM movimentacoes_financeiras WHERE propriedade_id = ? AND tipo = 'receita' AND data >= ? AND data < ?", [$p, $ini, $fim]),
        'despesas_mes' => $n("SELECT COALESCE(SUM(valor), 0) FROM movimentacoes_financeiras WHERE propriedade_id = ? AND tipo IN ('despesa','investimento') AND data >= ? AND data < ?", [$p, $ini, $fim]),
        'leite_mes' => $n('SELECT COALESCE(SUM(litros), 0) FROM producao_leite WHERE propriedade_id = ? AND data >= ? AND data < ?', [$p, $ini, $fim]),
    ];
}

/** Central de avisos (central_avisos): o que precisa de atenção, com uma chave que muda quando a situação muda. */
function central_avisos(string $p, bool $incluirVistos = false): array
{
    $h = hoje();
    $lista = [];
    $vac = um('SELECT COUNT(*) AS qtd, MAX(proxima_aplicacao) AS ult FROM (' . sql_vacinas_status() . ") s WHERE s.propriedade_id = ? AND s.ultima_dose AND s.status_calculado = 'vencida'", [$p]);
    if ((int) $vac['qtd'] > 0) {
        $q = (int) $vac['qtd'];
        $lista[] = ['chave' => "vacinas_atrasadas:$q:" . ($vac['ult'] ?? ''), 'nivel' => 'alerta', 'secao' => 'vacinacao',
            'titulo' => $q === 1 ? '1 vacina atrasada' : "$q vacinas atrasadas", 'texto' => 'A data da próxima dose já passou. Aplique e registre para sair da lista.'];
    }
    $partos = um("SELECT COUNT(*) AS qtd, MIN(previsao_parto) AS prox FROM inseminacoes WHERE propriedade_id = ? AND situacao IN ('em_andamento','confirmada') AND previsao_parto BETWEEN ? AND ?", [$p, $h, somar_dias($h, 15)]);
    if ((int) $partos['qtd'] > 0) {
        $q = (int) $partos['qtd'];
        $lista[] = ['chave' => "partos_15d:$q:" . $partos['prox'], 'nivel' => 'aviso', 'secao' => 'agenda',
            'titulo' => $q === 1 ? '1 parto nos próximos 15 dias' : "$q partos nos próximos 15 dias",
            'texto' => 'O primeiro está previsto para ' . substr(data_br($partos['prox']), 0, 5) . '. Prepare a maternidade.'];
    }
    $atual = (float) valor('SELECT COALESCE(SUM(litros), 0) FROM producao_leite WHERE propriedade_id = ? AND data BETWEEN ? AND ?', [$p, somar_dias($h, -7), somar_dias($h, -1)]);
    $anterior = (float) valor('SELECT COALESCE(SUM(litros), 0) FROM producao_leite WHERE propriedade_id = ? AND data BETWEEN ? AND ?', [$p, somar_dias($h, -14), somar_dias($h, -8)]);
    if ($anterior > 0 && $atual < $anterior * 0.8) {
        $lista[] = ['chave' => 'queda_leite:' . somar_dias($h, -1), 'nivel' => 'aviso', 'secao' => 'producao',
            'titulo' => 'O leite caiu ' . round(100 - $atual * 100 / $anterior) . '% nesta semana',
            'texto' => 'Foram ' . num_br(round($atual)) . ' litros nos últimos 7 dias, contra ' . num_br(round($anterior)) . ' na semana anterior.'];
    }
    $semPeso = (int) valor("SELECT COUNT(*) FROM animais a WHERE a.propriedade_id = ? AND a.situacao = 'ativo' AND a.data_nascimento >= ?
        AND NOT EXISTS (SELECT 1 FROM pesagens p WHERE p.animal_id = a.id AND p.data >= ?)", [$p, date('Y-m-d', strtotime("$h -24 months")), somar_dias($h, -60)]);
    if ($semPeso > 0) {
        $lista[] = ['chave' => "sem_pesagem:$semPeso:" . date('o-W'), 'nivel' => 'info', 'secao' => 'bezerros',
            'titulo' => $semPeso === 1 ? '1 animal jovem sem pesagem há mais de 60 dias' : "$semPeso animais jovens sem pesagem há mais de 60 dias",
            'texto' => 'Pesar a cada 30 a 60 dias mostra se estão ganhando peso como deveriam.'];
    }
    if ($incluirVistos || !$lista) return $lista;
    $vistos = array_flip(array_column(todos('SELECT chave FROM notificacoes WHERE user_id = ? AND chave IS NOT NULL', [usuario()['id']]), 'chave'));
    return array_values(array_filter($lista, fn($a) => !isset($vistos[$a['chave']])));
}

/** Notificações escritas para a pessoa (não lidas). */
function notificacoes_abertas(): array
{
    return todos('SELECT id, titulo, mensagem, created_at FROM notificacoes WHERE user_id = ? AND lida = 0 AND chave IS NULL ORDER BY created_at DESC LIMIT 20', [usuario()['id']]);
}

/** Agenda (agenda_periodo): vacinas a vencer, partos e retorno de cio; mais atrasadas e sem pesagem. */
function agenda_periodo(string $p, string $de, string $ate): array
{
    $h = hoje();
    $vs = sql_vacinas_status();
    $vacinas = todos("SELECT v.proxima_aplicacao AS data, 'vacina' AS tipo, v.id AS registro_id, v.animal_id, tv.nome AS titulo,
            COALESCE(a.nome, a.numero_brinco) AS detalhe, l.nome AS lote_nome, a.numero_brinco AS brinco
        FROM ($vs) v JOIN tipos_vacinas tv ON tv.id = v.tipo_vacina_id LEFT JOIN animais a ON a.id = v.animal_id LEFT JOIN lotes l ON l.id = v.lote_id
        WHERE v.propriedade_id = ? AND v.ultima_dose AND v.proxima_aplicacao BETWEEN ? AND ?", [$p, $de, $ate]);
    $partos = todos("SELECT i.previsao_parto AS data, 'parto' AS tipo, i.id AS registro_id, i.animal_id, 'Parto previsto' AS titulo,
            COALESCE(a.nome, a.numero_brinco) AS detalhe, a.numero_brinco AS brinco
        FROM inseminacoes i JOIN animais a ON a.id = i.animal_id
        WHERE i.propriedade_id = ? AND i.situacao IN ('em_andamento','confirmada') AND i.previsao_parto BETWEEN ? AND ?", [$p, $de, $ate]);
    // Retorno de cio: 21 dias depois da inseminação ainda em andamento.
    $cios = todos("SELECT i.data_inseminacao, i.id AS registro_id, i.animal_id, COALESCE(a.nome, a.numero_brinco) AS detalhe, a.numero_brinco AS brinco
        FROM inseminacoes i JOIN animais a ON a.id = i.animal_id
        WHERE i.propriedade_id = ? AND i.situacao = 'em_andamento' AND i.data_inseminacao BETWEEN ? AND ?", [$p, somar_dias($de, -21), somar_dias($ate, -21)]);
    $comLote = fn(array $l) => array_map(fn($r) => ['detalhe' => $r['detalhe'] ?? ($r['lote_nome'] ? 'Lote ' . $r['lote_nome'] : null)] + $r, $l);
    $vacinas = $comLote($vacinas);
    $eventos = array_merge($vacinas, $partos, array_map(fn($c) => ['data' => somar_dias($c['data_inseminacao'], 21), 'tipo' => 'cio', 'registro_id' => $c['registro_id'],
        'animal_id' => $c['animal_id'], 'titulo' => 'Observar retorno de cio', 'detalhe' => $c['detalhe'], 'brinco' => $c['brinco']], $cios));
    usort($eventos, fn($a, $b) => [$a['data'], $a['tipo']] <=> [$b['data'], $b['tipo']]);
    $truncado = count($eventos) > 500;
    $atrasadas = todos("SELECT v.proxima_aplicacao AS data, v.id AS registro_id, v.animal_id, tv.nome AS titulo,
            COALESCE(a.nome, a.numero_brinco) AS detalhe, l.nome AS lote_nome, a.numero_brinco AS brinco
        FROM ($vs) v JOIN tipos_vacinas tv ON tv.id = v.tipo_vacina_id LEFT JOIN animais a ON a.id = v.animal_id LEFT JOIN lotes l ON l.id = v.lote_id
        WHERE v.propriedade_id = ? AND v.ultima_dose AND v.status_calculado = 'vencida' ORDER BY v.proxima_aplicacao LIMIT 100", [$p]);
    $atrasadas = $comLote($atrasadas);
    $semPesagem = todos("SELECT a.id AS animal_id, a.nome, a.numero_brinco AS brinco, a.data_nascimento,
            (SELECT MAX(p.data) FROM pesagens p WHERE p.animal_id = a.id) AS ultima_pesagem
        FROM animais a WHERE a.propriedade_id = ? AND a.situacao = 'ativo' AND a.data_nascimento >= ?
          AND NOT EXISTS (SELECT 1 FROM pesagens p WHERE p.animal_id = a.id AND p.data >= ?)
        ORDER BY a.data_nascimento LIMIT 100", [$p, date('Y-m-d', strtotime("$h -24 months")), somar_dias($h, -60)]);
    return ['eventos' => array_slice($eventos, 0, 500), 'truncado' => $truncado, 'atrasadas' => $atrasadas, 'sem_pesagem' => $semPesagem, 'hoje' => $h];
}
