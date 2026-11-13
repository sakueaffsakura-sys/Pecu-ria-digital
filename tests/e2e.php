<?php
// Teste de ponta a ponta: sobe o site no servidor embutido do PHP com um banco SQLite descartável
// e faz o caminho de quem usa de verdade. Uso: php tests/e2e.php   (não precisa de MySQL)
declare(strict_types=1);

$raiz = realpath(__DIR__ . '/..');
$tmp = sys_get_temp_dir() . '/pd-e2e-' . getmypid();
@mkdir($tmp);
$dbArq = "$tmp/teste.db";
$pdo = new PDO("sqlite:$dbArq");
$pdo->exec((string) shell_exec('php ' . escapeshellarg("$raiz/scripts/schema-sqlite.php")));
$porta = 8790 + getmypid() % 100;
file_put_contents("$tmp/config.php", '<?php return ' . var_export([
    'nome_site' => 'Pecuária Digital', 'url' => "http://127.0.0.1:$porta",
    'db' => ['dsn' => "sqlite:$dbArq", 'usuario' => null, 'senha' => null], 'debug' => true, 'fuso' => 'America/Sao_Paulo',
], true) . ';');
$log = "$tmp/servidor.log";
$pid = (int) shell_exec('PECUARIA_CONFIG=' . escapeshellarg("$tmp/config.php") . " php -d display_errors=stderr -d error_reporting=-1 -S 127.0.0.1:$porta -t "
    . escapeshellarg("$raiz/public") . ' ' . escapeshellarg("$raiz/public/index.php") . ' > ' . escapeshellarg($log) . ' 2>&1 & echo $!');
register_shutdown_function(fn() => posix_kill($pid, SIGTERM));
usleep(600_000);

$falhas = 0;
$total = 0;
function ok(bool $c, string $nome): void
{
    global $falhas, $total;
    $total++;
    if (!$c) $falhas++;
    echo ($c ? '  ok      ' : '  FALHOU  ') . $nome . "\n";
}

final class Nav
{
    public string $html = '';
    public int $status = 0;
    public string $local = '';
    public function __construct(private string $cookies, private int $porta) {}
    public function ir(string $c, ?array $post = null): self
    {
        $ch = curl_init("http://127.0.0.1:{$this->porta}$c");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $this->cookies, CURLOPT_COOKIEFILE => $this->cookies, CURLOPT_FOLLOWLOCATION => true]);
        if ($post !== null) curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => array_filter($post, fn($v) => $v instanceof CURLFile) ? $post : http_build_query($post)]);
        $this->html = (string) curl_exec($ch);
        $this->status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $u = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $this->local = (string) parse_url($u, PHP_URL_PATH) . (parse_url($u, PHP_URL_QUERY) ? '?' . parse_url($u, PHP_URL_QUERY) : '');
        return $this;
    }
    public function csrf(): string { preg_match('/name="_csrf" value="([a-f0-9]+)"/', $this->html, $m); return $m[1] ?? ''; }
    public function enviar(string $pagina, array $dados, ?string $acao = null): self { $this->ir($pagina); return $this->ir($acao ?? $pagina, $dados + ['_csrf' => $this->csrf()]); }
    public function tem(string $t): bool { return str_contains($this->html, $t); }
}
$sql = function (string $q, array $p = []) use ($pdo) { $s = $pdo->prepare($q); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); };
$um = fn(string $q, array $p = []) => $sql($q, $p)[0] ?? null;
$hoje = date('Y-m-d');
$d = fn(int $n) => date('Y-m-d', strtotime("$n days"));

echo "Entrada e conta\n";
$a = new Nav("$tmp/a.txt", $porta);
$a->ir('/');
ok($a->status === 200 && $a->tem('Sua fazenda, anotada.') && $a->tem('Entre na sua conta'), 'tela de entrada igual à original');
ok($a->ir('/app/rebanho')->local === '/?redirect=%2Fapp%2Frebanho', 'sem login volta para a entrada lembrando a página');
$a->enviar('/?aba=criar', ['acao' => 'criar', 'aba' => 'criar', 'nome' => 'Ana', 'email' => 'ana@ex.com', 'senha' => 'abcdefgh'], '/');
ok($a->tem('Use letras e números na senha.'), 'cadastro recusa senha só com letras');
$a->enviar('/?aba=criar', ['acao' => 'criar', 'aba' => 'criar', 'nome' => 'Ana Produtora', 'email' => 'ana@ex.com', 'senha' => 'boigordo2026'], '/');
ok($a->local === '/app/inicio' && $a->tem('Comece pela sua propriedade'), 'cadastro entra e pede a propriedade');
$uA = $um('SELECT id FROM usuarios WHERE email = ?', ['ana@ex.com'])['id'];
ok($um("SELECT 1 AS x FROM user_roles WHERE user_id = ? AND role = 'administrador'", [$uA]) !== null, 'primeira conta vira administradora do site');

echo "Propriedade\n";
$a->ir('/app/inicio', null);
$a->ir('/propriedade/nova', ['_csrf' => $a->csrf(), 'nome' => 'Fazenda Boa Vista', 'cidade' => 'Manaus', 'estado' => 'am']);
ok($a->local === '/app/inicio' && $a->tem('Fazenda Boa Vista') && $a->tem('animais ativos'), 'cria a propriedade e abre o Início');
$prop = $um('SELECT * FROM propriedades')['id'];
ok($um('SELECT estado FROM propriedades')['estado'] === 'AM', 'estado em maiúsculas');

echo "Rebanho e referências digitadas\n";
$a->enviar('/app/rebanho?novo=1', ['acao' => 'salvar', 'numero_brinco' => '1100', 'nome' => 'Mimosa <b>x</b>', 'sexo' => 'F', 'raca' => 'Girolando', 'data_nascimento' => '2021-03-10', 'lote_id' => 'Lactação', 'situacao' => 'ativo'], '/app/rebanho');
ok($a->tem('Animal cadastrado.') && $a->tem('Cadastro criado automaticamente: Lote &quot;Lactação&quot;.'), 'cadastra animal e cria o lote digitado');
ok(!$a->tem('<b>x</b>') && $a->tem('&lt;b&gt;x&lt;/b&gt;'), 'nome com código aparece escapado');
$mimosa = $um("SELECT * FROM animais WHERE numero_brinco = '1100'");
$lote = $um("SELECT id FROM lotes WHERE nome = 'Lactação'")['id'];
ok($mimosa['lote_id'] === $lote, 'animal ligado ao lote');
$a->enviar('/app/rebanho?novo=1', ['acao' => 'salvar', 'numero_brinco' => '1100', 'sexo' => 'F', 'situacao' => 'ativo'], '/app/rebanho');
ok($a->status === 422 && $a->tem('Já existe um animal com esse número de brinco.'), 'recusa brinco repetido');
$a->enviar('/app/rebanho?novo=1', ['acao' => 'salvar', 'numero_brinco' => '1355', 'nome' => 'Trovão', 'sexo' => 'Macho', 'situacao' => 'Ativo'], '/app/rebanho');
ok($um("SELECT sexo, situacao FROM animais WHERE numero_brinco = '1355'") === ['sexo' => 'M', 'situacao' => 'ativo'], 'opções aceitam o nome escrito (Macho, Ativo)');
$a->enviar('/app/rebanho?novo=1', ['acao' => 'salvar', 'numero_brinco' => '1200', 'sexo' => 'F', 'situacao' => 'ativo', 'pai_id' => 'Mimosa <b>x</b>'], '/app/rebanho');
ok($a->tem('deve ser macho neste campo'), 'pai precisa ser macho');
$a->ir('/app/rebanho?animal=' . $mimosa['id']);
ok($a->tem('Ficha do animal') && $a->tem('Linha do tempo') && $a->tem('Lactação'), 'ficha do animal abre por cima da lista');
$a->ir('/app/rebanho?busca=1355');
ok($a->tem('Trovão') && !$a->tem('>Mimosa'), 'busca na lista');

echo "Pesagens, vacinas e reprodução\n";
$a->enviar('/app/bezerros?novo=1', ['acao' => 'salvar', 'animal_id' => '1100', 'data' => $d(-30), 'peso_kg' => '450'], '/app/bezerros');
$a->enviar('/app/bezerros?novo=1', ['acao' => 'salvar', 'animal_id' => '1100', 'data' => $hoje, 'peso_kg' => '471,5'], '/app/bezerros');
$a->enviar('/app/bezerros?novo=1', ['acao' => 'salvar', 'animal_id' => '1100', 'data' => $hoje, 'peso_kg' => '480'], '/app/bezerros');
ok($a->tem('Já existe um registro desse animal nessa data.'), 'uma pesagem por animal por dia');
$a->enviar('/app/bezerros?novo=1', ['acao' => 'salvar', 'animal_id' => '1100', 'data' => $d(5), 'peso_kg' => '480'], '/app/bezerros');
ok($a->tem('A data não pode ser no futuro.'), 'recusa data no futuro');
$a->enviar('/app/bezerros?novo=1', ['acao' => 'salvar', 'animal_id' => '1100', 'data' => $d(-2), 'peso_kg' => '0'], '/app/bezerros');
ok($a->tem('Peso (kg) deve ser maior que zero.'), 'peso maior que zero');
$a->ir('/app/rebanho?animal=' . $mimosa['id']);
ok($a->tem('Pesagem: 471,50 kg') && $a->tem('0,72 kg por dia desde a pesagem anterior'), 'ficha mostra ganho de peso por dia');

$a->enviar('/app/vacinacao?novo=1', ['acao' => 'salvar', 'tipo_vacina_id' => 'Febre aftosa', 'data_aplicacao' => $hoje], '/app/vacinacao');
ok($a->tem('Informe o brinco ou nome de um animal, ou o nome de um lote.'), 'vacina pede animal ou lote');
ok($um("SELECT COUNT(*) AS n FROM tipos_vacinas")['n'] == 0, 'nada fica gravado quando o formulário falha');
$a->enviar('/app/vacinacao?novo=1', ['acao' => 'salvar', 'tipo_vacina_id' => 'Febre aftosa', 'animal_id' => '1100', 'lote_id' => 'Lactação', 'data_aplicacao' => $d(-200), 'proxima_aplicacao' => $d(-20)], '/app/vacinacao');
$v1 = $um('SELECT * FROM vacinacoes');
ok($v1 && $v1['lote_id'] === null && $v1['animal_id'] === $mimosa['id'], 'com animal, o lote não entra (como no original)');
$a->enviar('/app/vacinacao?aba=tipos&editar=' . $um('SELECT id FROM tipos_vacinas')['id'], ['acao' => 'salvar', 'aba' => 'tipos', 'id' => $um('SELECT id FROM tipos_vacinas')['id'], 'nome' => 'Febre aftosa', 'intervalo_dias' => '180'], '/app/vacinacao');
$a->enviar('/app/vacinacao?novo=1', ['acao' => 'salvar', 'tipo_vacina_id' => 'Febre aftosa', 'lote_id' => 'Lactação', 'data_aplicacao' => $d(-170)], '/app/vacinacao');
ok($um('SELECT proxima_aplicacao FROM vacinacoes WHERE lote_id IS NOT NULL')['proxima_aplicacao'] === $d(10), 'próxima dose pelo intervalo da vacina');

$a->enviar('/app/inseminacao?novo=1', ['acao' => 'salvar', 'animal_id' => '1355', 'data_inseminacao' => $hoje, 'reprodutor_semen' => 'X', 'situacao' => 'em_andamento'], '/app/inseminacao');
ok($a->tem('deve ser fêmea neste campo'), 'inseminação só em fêmea');
$a->enviar('/app/inseminacao?novo=1', ['acao' => 'salvar', 'animal_id' => '1100', 'data_inseminacao' => $d(-270), 'reprodutor_semen' => 'Trovão', 'situacao' => 'confirmada'], '/app/inseminacao');
ok($um('SELECT previsao_parto FROM inseminacoes')['previsao_parto'] === $d(13), 'previsão de parto = data + 283 dias');
$a->enviar('/app/inseminacao?novo=1', ['acao' => 'salvar', 'animal_id' => 'Estrela', 'data_inseminacao' => $d(-18), 'reprodutor_semen' => 'Sêmen Gir', 'situacao' => 'em_andamento'], '/app/inseminacao');
ok($um("SELECT sexo FROM animais WHERE numero_brinco = 'Estrela'")['sexo'] === 'F', 'animal digitado e inexistente é criado como fêmea');

echo "Início, avisos e agenda\n";
$a->ir('/app/inicio');
ok((bool) preg_match('/aria-label="2 animais ativos/', $a->html) || (bool) preg_match('/aria-label="3 animais ativos/', $a->html), 'brinco de animais ativos');
ok($a->tem('1 vacina atrasada') && $a->tem('1 parto nos próximos 15 dias'), 'precisa de atenção: vacina atrasada e parto');
ok($a->tem('Observar retorno de cio'), 'esta semana mostra retorno de cio (21 dias)');
preg_match('/name="chave" value="(vacinas_atrasadas:[^"]+)"/', $a->html, $m);
$a->ir('/avisos/visto', ['_csrf' => $a->csrf(), 'chave' => $m[1] ?? '', 'volta' => '/app/inicio']);
ok(!$a->tem('1 vacina atrasada') && $a->tem('1 parto nos próximos 15 dias'), 'marcar como visto esconde só aquele aviso');
$a->ir('/app/agenda?ref=' . $d(3));
ok($a->tem('Observar retorno de cio') && $a->tem('Vacinas atrasadas'), 'agenda da semana');
$a->ir('/app/agenda?modo=mes&ocultar[]=cio');
ok(!$a->tem('Observar retorno de cio') && $a->tem('Parto previsto'), 'agenda do mês sem retorno de cio');
$a->ir('/agenda.ics?modo=mes');
ok(str_starts_with($a->html, 'BEGIN:VCALENDAR') && str_contains($a->html, 'SUMMARY:Parto: Parto previsto'), 'baixa o calendário (.ics)');

echo "Nascimento, leite e anotações\n";
$a->enviar('/app/nascimentos?novo=1', ['acao' => 'salvar', 'vaca_id' => '1100', 'bezerro_id' => '1400', 'data_nascimento' => $hoje, 'sexo' => 'M', 'lote_id' => 'Maternidade'], '/app/nascimentos');
$bz = $um("SELECT * FROM animais WHERE numero_brinco = '1400'");
ok($bz && $bz['sexo'] === 'M' && $bz['mae_id'] === $mimosa['id'] && $bz['lote_id'] === $um("SELECT id FROM lotes WHERE nome = 'Maternidade'")['id'], 'nascimento cria o bezerro com sexo, mãe e lote');
$a->enviar('/app/nascimentos?novo=1', ['acao' => 'salvar', 'vaca_id' => '1100', 'bezerro_id' => '1400', 'data_nascimento' => $hoje, 'sexo' => 'M'], '/app/nascimentos');
ok($a->tem('Esse bezerro já possui um nascimento registrado.'), 'um nascimento por bezerro');
$a->enviar('/app/producao?novo=1', ['acao' => 'salvar', 'animal_id' => '1100', 'data' => $hoje, 'litros' => '18,5', 'valor_litro' => '2,60'], '/app/producao');
ok((float) $um('SELECT valor_total FROM producao_leite')['valor_total'] === 48.1, 'total em reais calculado sozinho');
$a->ir('/app/producao');
ok($a->tem('R$ 48,10') && $a->tem('R$ 2,60'), 'lista do leite em reais');
$a->enviar('/app/producao?aba=anotacoes&novo=1', ['acao' => 'salvar', 'aba' => 'anotacoes', 'observacao' => 'Cio observado de manhã', 'data' => $hoje, 'animal_id' => '1100', 'comportamento' => 'Inquieta', 'producao' => '12,5'], '/app/producao');
$a->enviar('/app/producao?aba=anotacoes&novo=1', ['acao' => 'salvar', 'aba' => 'anotacoes', 'observacao' => 'Trocar o sal dos cochos', 'data' => $hoje], '/app/producao');
ok($a->tem('Anotação criada com sucesso.') && $a->tem('Hoje') && $a->tem('Anotação geral') && $a->tem('Comportamento: </strong>Inquieta'), 'mural de anotações por dia');
$nota = $um("SELECT id FROM anotacoes_producao WHERE animal_id IS NULL")['id'];
$a->ir('/app/producao', ['_csrf' => $a->csrf(), 'acao' => 'marcar', 'id' => $nota, 'aba' => 'anotacoes']);
$a->ir('/app/producao?aba=anotacoes&aba_notas=marcadas');
ok($a->tem('Trocar o sal') && !$a->tem('Cio observado'), 'marcar com estrela e filtrar marcadas');
$a->ir('/app/producao?aba=anotacoes&busca=1100');
ok($a->tem('Cio observado') && !$a->tem('Trocar o sal'), 'busca de anotação pelo brinco');

echo "Dinheiro\n";
$a->enviar('/app/comercial?aba=vendas&novo=1', ['acao' => 'salvar', 'aba' => 'vendas', 'data' => $hoje, 'animal_id' => '1355', 'item' => 'Touro Trovão', 'quantidade' => '1', 'valor' => '5.000,00', 'categoria' => 'Animais'], '/app/comercial');
$venda = $um('SELECT * FROM vendas');
$mov = $um("SELECT * FROM movimentacoes_financeiras WHERE origem_tipo = 'venda'");
ok($mov && $mov['tipo'] === 'receita' && (float) $mov['valor'] === 5000.0 && $mov['descricao'] === 'Venda: Touro Trovão' && $venda['movimentacao_id'] === $mov['id'], 'venda vira receita no financeiro');
ok($um("SELECT situacao FROM animais WHERE numero_brinco = '1355'")['situacao'] === 'vendido', 'animal vendido sai do rebanho ativo');
$a->enviar('/app/comercial?aba=vendas&editar=' . $venda['id'], ['acao' => 'salvar', 'aba' => 'vendas', 'id' => $venda['id'], 'data' => $hoje, 'animal_id' => '1355', 'item' => 'Touro Trovão', 'quantidade' => '1', 'valor' => '5500', 'categoria' => 'Animais'], '/app/comercial');
ok((float) $um("SELECT valor FROM movimentacoes_financeiras WHERE origem_tipo = 'venda'")['valor'] === 5500.0 && $um("SELECT COUNT(*) AS n FROM movimentacoes_financeiras")['n'] == 1, 'editar a venda atualiza o mesmo lançamento');
$a->enviar('/app/comercial?novo=1', ['acao' => 'salvar', 'data' => $hoje, 'produto_animal' => 'Sal mineral', 'quantidade' => '10', 'valor' => '890', 'categoria' => 'Insumos'], '/app/comercial');
ok($um("SELECT tipo FROM movimentacoes_financeiras WHERE origem_tipo = 'compra'")['tipo'] === 'despesa', 'compra vira despesa');
$a->enviar('/app/financeiro?novo=1', ['acao' => 'salvar', 'tipo' => 'investimento', 'descricao' => 'Reforma do curral', 'categoria' => 'Estrutura', 'valor' => '1.000', 'data' => $hoje], '/app/financeiro');
$a->ir('/app/financeiro');
ok($a->tem('Reforma do curral') && !$a->tem('Venda: Touro') && !$a->tem('Compra: Sal'), 'lucros e investimentos mostra só os lançamentos à mão');
$a->ir('/app/financeiro?editar=' . $mov['id']);
ok($a->tem('Esse registro não foi encontrado nesta propriedade.'), 'lançamento automático não se edita por ali');
$a->ir('/app/inicio');
preg_match("/Este mês: (R\\$ [^<]+)/", $a->html, $mm); ok($a->tem("Este mês: R\$ 3.610,00"), "resultado do mês: 5.500 - 890 - 1.000 (" . ($mm[1] ?? "?") . ")");
$a->ir('/app/comercial?aba=vendas');
$a->ir('/app/comercial', ['_csrf' => $a->csrf(), 'acao' => 'excluir', 'id' => $venda['id'], 'aba' => 'vendas']);
ok($um("SELECT situacao FROM animais WHERE numero_brinco = '1355'")['situacao'] === 'ativo' && !$um("SELECT 1 AS x FROM movimentacoes_financeiras WHERE origem_tipo = 'venda'"), 'excluir a venda devolve o animal e tira a receita');
ok($um("SELECT COUNT(*) AS n FROM registros_excluidos WHERE tabela = 'vendas'")['n'] == 1, 'exclusão registrada para o aplicativo');

echo "Busca, isolamento e permissões\n";
$a->ir('/api/busca?q=Mim');
$j = json_decode($a->html, true);
ok(($j['animais'][0]['brinco'] ?? '') === '1100', 'busca do topo acha o animal');
$tipo = $um('SELECT id FROM tipos_vacinas')['id'];
$a->ir('/app/vacinacao?aba=tipos');
$a->ir('/app/vacinacao', ['_csrf' => $a->csrf(), 'acao' => 'excluir', 'id' => $tipo, 'aba' => 'tipos']);
ok($a->tem('tem aplicações registradas'), 'não exclui vacina em uso');
$a->ir('/app/rebanho', ['acao' => 'salvar', 'numero_brinco' => '999', 'sexo' => 'F', 'situacao' => 'ativo']);
ok($a->status === 419, 'formulário sem token é recusado');

$b = new Nav("$tmp/b.txt", $porta);
$b->enviar('/?aba=criar', ['acao' => 'criar', 'aba' => 'criar', 'nome' => 'Beto', 'email' => 'beto@ex.com', 'senha' => 'cavalo77baio'], '/');
$b->ir('/app/inicio');
$b->ir('/propriedade/nova', ['_csrf' => $b->csrf(), 'nome' => 'Sítio do Beto']);
$b->ir('/app/rebanho?animal=' . $mimosa['id']);
ok($b->tem('Esse registro não foi encontrado nesta propriedade.') && !$b->tem('Mimosa'), 'não abre animal de outra fazenda');
$b->ir('/api/busca?q=Mim');
ok(json_decode($b->html, true)['animais'] === [], 'busca não mostra animais de outra fazenda');
$b->ir('/app/rebanho');
$b->ir('/app/rebanho', ['_csrf' => $b->csrf(), 'acao' => 'excluir', 'id' => $mimosa['id']]);
ok($b->status === 404 && $um('SELECT 1 AS x FROM animais WHERE id = ?', [$mimosa['id']]), 'não exclui animal de outra fazenda');
$uB = $um('SELECT id FROM usuarios WHERE email = ?', ['beto@ex.com'])['id'];
$sql("INSERT INTO propriedade_membros (id, propriedade_id, user_id, role) VALUES ('11111111-1111-4111-8111-111111111111', ?, ?, 'visualizador')", [$prop, $uB]);
$b->ir('/app/inicio');
$b->ir('/propriedade/trocar', ['_csrf' => $b->csrf(), 'id' => $prop, 'volta' => '/app/rebanho']);
ok($b->tem('Mimosa') && $b->tem('acesso só de consulta'), 'quem só consulta vê a fazenda');
$b->ir('/app/rebanho', ['_csrf' => $b->csrf(), 'acao' => 'salvar', 'numero_brinco' => '777', 'sexo' => 'F', 'situacao' => 'ativo']);
ok($b->status === 403 && !$um("SELECT 1 AS x FROM animais WHERE numero_brinco = '777'"), 'quem só consulta não grava');

// ---------- Etapa 2: relatórios, indicadores, diagnóstico, ferramentas, importação ----------
foreach (['relatorios' => 'Relatórios', 'indicadores' => 'Indicadores', 'diagnostico' => 'Diagnóstico', 'ferramentas' => 'Calculadoras'] as $sec => $txt) {
    $a->ir("/app/$sec");
    ok($a->status === 200 && $a->tem($txt), "abre $sec");
}
$a->ir('/app/relatorios?tipo=ano&ano=' . date('Y'));
ok($a->status === 200 && $a->tem(date('Y')), 'relatório anual');
$a->ir('/app/relatorios?exportar=csv');
ok($a->status === 200 && str_contains($a->html, ';') && !str_contains($a->html, '<html'), 'exporta relatório em CSV');
$a->ir('/api/clima/lugares?nome=ab');
ok($a->status === 400 && (json_decode($a->html, true)['ok'] ?? null) === false, 'clima pede nome com 3 letras');
$a->ir('/api/clima/agora?lat=200&lon=0');
ok($a->status === 400, 'clima recusa lugar inválido');

$csv = "$tmp/animais.csv";
file_put_contents($csv, "Brinco;Nome;Sexo;Situação;Nascimento\n3001;Estrela;Fêmea;ativo;10/02/2022\n3002;Lua;F;;2023-05-01\n1100;Repetida;F;ativo;\n3003;Errada;X;ativo;\n3004;Sem lote;F;ativo;\n");
$a->ir('/app/rebanho');
$a->ir('/app/rebanho', ['_csrf' => $a->csrf(), 'acao' => 'importar_analisar', 'arquivo' => new CURLFile($csv, 'text/csv', 'animais.csv')]);
ok($a->tem('Total de linhas') && $a->tem('Importar 3 registros') && $a->tem('Linha 4'), 'importação mostra prévia (' . (preg_match('/Importar (\d+) registros/', $a->html, $mm) ? $mm[1] : '?') . ' válidas)');
preg_match('/name="importacao" value="([^"]+)"/', $a->html, $mi);
$antes = $um('SELECT COUNT(*) AS n FROM animais WHERE propriedade_id = ?', [$prop])['n'];
$a->ir('/app/rebanho', ['_csrf' => $a->csrf(), 'acao' => 'importar_iniciar', 'importacao' => $mi[1] ?? '']);
$depois = $um('SELECT COUNT(*) AS n FROM animais WHERE propriedade_id = ?', [$prop])['n'];
ok($a->tem('Adicionados') && $depois - $antes === 3, 'importa só as linhas novas e válidas (+' . ($depois - $antes) . ')');
ok($a->tem('Duplicados ignorados') && !$um("SELECT 1 AS x FROM animais WHERE nome = 'Repetida'"), 'não duplica brinco existente');
ok(($um("SELECT data_nascimento FROM animais WHERE numero_brinco = '3001'")['data_nascimento'] ?? '') === '2022-02-10', 'importação entende data brasileira');
$a->ir('/app/rebanho', ['_csrf' => $a->csrf(), 'acao' => 'importar_iniciar', 'importacao' => $mi[1] ?? '']);
ok($a->tem('erro-forte') || $a->tem('não encontrad') || $a->tem('expir'), 'não importa o mesmo arquivo duas vezes');
file_put_contents("$tmp/ruim.csv", "a;b\n");
$a->ir('/app/rebanho', ['_csrf' => $a->csrf(), 'acao' => 'importar_analisar', 'arquivo' => new CURLFile("$tmp/ruim.csv", 'text/csv', 'ruim.csv')]);
ok($a->tem('erro-forte'), 'arquivo sem colunas reconhecidas dá erro claro');
file_put_contents("$tmp/vac.csv", "animal;vacina;data\nNinguém;Aftosa;2026-01-10\n");
$a->ir('/app/vacinacao');
$a->ir('/app/vacinacao', ['_csrf' => $a->csrf(), 'acao' => 'importar_analisar', 'arquivo' => new CURLFile("$tmp/vac.csv", 'text/csv', 'vac.csv')]);
ok($a->tem('Cadastro não encontrado') && !$um("SELECT 1 AS x FROM animais WHERE nome = 'Ninguém' OR numero_brinco = 'Ninguém'"), 'importação não cria animal inexistente');

$c = new Nav("$tmp/c.txt", $porta);
for ($i = 0; $i < 10; $i++) $c->enviar('/', ['acao' => 'entrar', 'email' => 'ana@ex.com', 'senha' => 'errada' . $i]);
$c->enviar('/', ['acao' => 'entrar', 'email' => 'ana@ex.com', 'senha' => 'boigordo2026']);
ok($c->tem('Muitas tentativas'), 'limita tentativas de senha');

$a->ir('/app/inicio');
$a->ir('/sair', ['_csrf' => $a->csrf()]);
ok($a->ir('/app/inicio')->local !== '/app/inicio', 'sair encerra a sessão');
ok($um("SELECT COUNT(*) AS n FROM sessoes WHERE user_id = ? AND encerrada_em IS NOT NULL", [$uA])['n'] >= 1, 'sessão marcada como encerrada');
$a->ir('/nada');
ok($a->status === 404, 'endereço inexistente dá 404');

$avisos = preg_grep('/(Warning|Notice|Deprecated|Fatal|Uncaught)/', explode("\n", (string) file_get_contents($log)));
ok(!$avisos, 'nenhum aviso ou erro do PHP');
if ($avisos) echo implode("\n", array_slice($avisos, 0, 10)) . "\n";
echo "\n$total verificações, " . ($total - $falhas) . " passaram, $falhas falharam.\n";
exit($falhas ? 1 : 0);
