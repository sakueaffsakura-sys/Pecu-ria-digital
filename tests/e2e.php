<?php
// Teste de ponta a ponta: sobe o site no servidor embutido do PHP com um banco SQLite descartável
// e faz o caminho de um usuário de verdade (cadastro, propriedade, animais, lançamentos, permissões).
// Uso: php tests/e2e.php      (não precisa de MySQL; não toca no banco de produção)
declare(strict_types=1);

$raiz = realpath(__DIR__ . '/..');
$tmp = sys_get_temp_dir() . '/pecuaria-e2e-' . getmypid();
@mkdir($tmp);
$dbArq = "$tmp/teste.db";
$pdo = new PDO("sqlite:$dbArq");
$pdo->exec(shell_exec('php ' . escapeshellarg("$raiz/scripts/schema-sqlite.php")));
file_put_contents("$tmp/config.php", '<?php return ' . var_export([
    'nome_site' => 'Pecuária Digital', 'url' => 'http://127.0.0.1:8765',
    'db' => ['dsn' => "sqlite:$dbArq", 'usuario' => null, 'senha' => null],
    'debug' => true, 'fuso' => 'America/Sao_Paulo', 'cadastro_aberto' => true,
], true) . ';');

$log = "$tmp/servidor.log";
$cmd = 'PECUARIA_CONFIG=' . escapeshellarg("$tmp/config.php") . ' php -d display_errors=stderr -d error_reporting=-1 -S 127.0.0.1:8765 -t '
    . escapeshellarg("$raiz/public") . ' ' . escapeshellarg("$raiz/public/index.php") . ' > ' . escapeshellarg($log) . ' 2>&1 & echo $!';
$pid = (int) shell_exec($cmd);
register_shutdown_function(fn() => posix_kill($pid, SIGTERM));
usleep(500_000);

$falhas = 0;
$total = 0;
function ok(bool $cond, string $nome): void
{
    global $falhas, $total;
    $total++;
    if (!$cond) $falhas++;
    echo ($cond ? "  ok   " : "  FALHOU ") . $nome . "\n";
}

final class Navegador
{
    public string $html = '';
    public int $status = 0;
    public string $local = '';
    private string $cookies;

    public function __construct(string $tmp, string $nome) { $this->cookies = "$tmp/cookies-$nome.txt"; }

    public function ir(string $caminho, ?array $post = null, bool $seguir = true): self
    {
        $ch = curl_init('http://127.0.0.1:8765' . $caminho);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $this->cookies, CURLOPT_COOKIEFILE => $this->cookies,
            CURLOPT_FOLLOWLOCATION => $seguir, CURLOPT_HEADER => false,
        ]);
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $this->html = (string) curl_exec($ch);
        $this->status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->local = (string) parse_url((string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL), PHP_URL_PATH);
        return $this;
    }

    public function csrf(): string
    {
        preg_match('/name="_csrf" value="([a-f0-9]+)"/', $this->html, $m);
        return $m[1] ?? '';
    }

    /** Abre a página do formulário e envia com o token dela. */
    public function enviar(string $caminho, array $dados, ?string $acao = null): self
    {
        $this->ir($caminho);
        return $this->ir($acao ?? $caminho, $dados + ['_csrf' => $this->csrf()]);
    }

    public function tem(string $texto): bool { return str_contains($this->html, $texto); }
}

$hoje = date('Y-m-d');
$dias = fn(int $n) => date('Y-m-d', strtotime("$n days"));
$db = fn(string $sql, array $p = []) => (function () use ($pdo, $sql, $p) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); })();

echo "Página inicial e cadastro\n";
$a = new Navegador($tmp, 'a');
$a->ir('/');
ok($a->status === 200 && $a->tem('Criar conta grátis'), 'página inicial abre');
ok($a->ir('/painel')->local === '/entrar', 'painel sem login manda para Entrar');
$a->enviar('/cadastro', ['nome' => 'Ana Produtora', 'email' => 'ana@exemplo.com', 'senha' => '12345678', 'senha2' => '12345678']);
ok($a->tem('fácil demais'), 'recusa senha fraca');
$a->enviar('/cadastro', ['nome' => 'Ana Produtora', 'email' => 'ana@exemplo.com', 'senha' => 'boi-gordo-2026', 'senha2' => 'boi-gordo-2026']);
ok($a->local === '/propriedades', 'cadastro entra e pede a propriedade');
ok((int) $db('SELECT admin FROM usuarios WHERE email = ?', ['ana@exemplo.com'])[0]['admin'] === 1, 'primeira conta vira administradora');

echo "Propriedade\n";
$a->enviar('/propriedades', ['nome' => 'Fazenda Boa Vista', 'cidade' => 'Manaus', 'estado' => 'AM', 'area_ha' => '120,5', 'periodo_gestacao_dias' => '283']);
ok($a->local === '/painel' && $a->tem('Fazenda Boa Vista') && $a->tem('Primeiros passos'), 'cria a propriedade e abre o painel');
$prop = (int) $db('SELECT id FROM propriedades')[0]['id'];
ok(count($db('SELECT id FROM tipos_vacinas WHERE propriedade_id = ?', [$prop])) === 5, 'vacinas comuns já cadastradas');

echo "Rebanho\n";
$a->enviar('/m/lotes/novo', ['nome' => 'Lactação', 'descricao' => 'Pasto 1']);
ok($a->local === '/m/lotes' && $a->tem('Lactação'), 'cria lote');
$lote = (int) $db('SELECT id FROM lotes')[0]['id'];
$a->enviar('/m/animais/novo', ['brinco' => '101', 'nome' => 'Mimosa <script>alert(1)</script>', 'sexo' => 'F', 'categoria' => 'vaca', 'raca' => 'Girolando', 'data_nascimento' => '2020-03-15', 'lote_id' => (string) $lote, 'origem' => 'nascido', 'situacao' => 'ativo']);
ok(str_starts_with($a->local, '/animal/'), 'cadastra animal e abre a ficha');
ok(!$a->tem('<script>alert(1)') && $a->tem('&lt;script&gt;'), 'nome com código aparece escapado');
$a101 = (int) $db("SELECT id FROM animais WHERE brinco = '101'")[0]['id'];
$a->enviar('/m/animais/novo', ['brinco' => '201', 'sexo' => 'M', 'origem' => 'comprado', 'situacao' => 'ativo']);
$a->enviar('/m/animais/novo', ['brinco' => '101', 'sexo' => 'F', 'origem' => 'nascido', 'situacao' => 'ativo']);
ok($a->tem('Já existe um registro igual'), 'recusa brinco repetido');
$a->enviar('/m/animais/novo', ['brinco' => '999', 'sexo' => 'F', 'origem' => 'nascido', 'situacao' => 'ativo', 'data_nascimento' => $dias(5)]);
ok($a->tem('não pode ser no futuro'), 'recusa nascimento no futuro');
$a->enviar('/m/animais/novo', ['brinco' => '998', 'sexo' => 'X', 'origem' => 'nascido', 'situacao' => 'ativo']);
ok($a->tem('escolha uma das opções'), 'recusa sexo inválido');

$a->enviar('/m/pesagens/novo', ['animal_id' => '101', 'data' => $dias(-30), 'peso_kg' => '450']);
$a->enviar('/m/pesagens/novo', ['animal_id' => '101', 'data' => $hoje, 'peso_kg' => '471,5']);
$a->enviar('/m/pesagens/novo', ['animal_id' => '101', 'data' => $hoje, 'peso_kg' => '480']);
ok($a->tem('já tem uma pesagem nesse dia'), 'recusa duas pesagens no mesmo dia');
$a->enviar('/m/pesagens/novo', ['animal_id' => 'nao-existe', 'data' => $hoje, 'peso_kg' => '480']);
ok($a->tem('não achei animal'), 'recusa brinco inexistente');
$a->ir("/animal/$a101");
ok($a->tem('471,5 kg') && $a->tem('717 g'), 'ficha mostra peso atual e ganho por dia (21,5 kg / 30 dias)');

echo "Sanidade e reprodução\n";
$aftosa = (int) $db("SELECT id FROM tipos_vacinas WHERE nome = 'Febre aftosa'")[0]['id'];
$a->enviar('/m/vacinacoes/novo', ['tipo_vacina_id' => (string) $aftosa, 'data' => $hoje]);
ok($a->tem('Informe o animal ou o lote'), 'vacinação exige animal ou lote');
$a->enviar('/m/vacinacoes/novo', ['tipo_vacina_id' => (string) $aftosa, 'animal_id' => '101', 'data' => $dias(-200)]);
ok($db('SELECT proxima_dose FROM vacinacoes')[0]['proxima_dose'] === $dias(-20), 'próxima dose calculada pelo intervalo');
$a->enviar('/m/vacinacoes/novo', ['tipo_vacina_id' => (string) $aftosa, 'lote_id' => (string) $lote, 'data' => $dias(-170)]);
$a->enviar('/m/inseminacoes/novo', ['animal_id' => '201', 'data' => $hoje, 'tipo' => 'IA', 'resultado' => 'pendente']);
ok($a->tem('não é fêmea'), 'inseminação recusa macho');
$a->enviar('/m/inseminacoes/novo', ['animal_id' => '101', 'data' => $dias(-250), 'tipo' => 'IA', 'resultado' => 'prenhe', 'touro_semen' => 'Touro X']);
ok($db('SELECT previsao_parto FROM inseminacoes')[0]['previsao_parto'] === $dias(33), 'previsão de parto = data + 283 dias');
$a->ir('/painel');
ok((bool) preg_match('/<span class="n">1<\/span><span>vacinas vencidas/', $a->html), 'painel conta 1 vacina vencida (a do animal)');
ok((bool) preg_match('/<span class="n">1<\/span><span>partos previstos/', $a->html), 'painel mostra 1 parto previsto');
$a->ir('/agenda');
ok($a->tem('Parto previsto') && $a->tem('atrasado') && $a->tem('lote Lactação'), 'agenda lista parto, vacina atrasada e vacina do lote');
if (!$a->tem('lote Lactação')) file_put_contents("$tmp/agenda.html", $a->html);

$a->enviar('/m/nascimentos/novo', ['mae_id' => '101', 'data' => $hoje, 'sexo' => 'F', 'peso_kg' => '32', 'brinco_cria' => '301']);
$cria = $db("SELECT * FROM animais WHERE brinco = '301'");
ok(count($cria) === 1 && (int) $cria[0]['mae_id'] === $a101 && (int) $cria[0]['lote_id'] === $lote, 'parto cria a bezerra ligada à mãe e ao lote');
ok(count($db('SELECT id FROM pesagens WHERE animal_id = ?', [$cria[0]['id']])) === 1, 'peso ao nascer vira pesagem');
$a->ir('/painel');
ok((bool) preg_match('/<span class="n">0<\/span><span>partos previstos/', $a->html), 'depois do parto, a previsão sai do painel');

echo "Leite e financeiro\n";
$a->enviar('/m/producao_leite/novo', ['data' => $hoje, 'turno' => 'manha', 'litros' => '18,5', 'animal_id' => '101']);
$a->enviar('/m/producao_leite/novo', ['data' => $dias(-1), 'turno' => 'dia', 'litros' => '120']);
$a->ir('/m/producao_leite');
ok($a->tem('138,5 L'), 'lista de leite soma os litros');
$a->enviar('/m/vendas/novo', ['data' => $hoje, 'descricao' => 'Garrote 201', 'quantidade' => '1', 'valor_total' => '2.350,00', 'comprador' => 'José', 'animal_id' => '201']);
$mov = $db("SELECT * FROM movimentacoes WHERE origem = 'venda'");
ok(count($mov) === 1 && (float) $mov[0]['valor'] === 2350.0 && $mov[0]['tipo'] === 'entrada', 'venda gera entrada no caixa');
ok($db("SELECT situacao FROM animais WHERE brinco = '201'")[0]['situacao'] === 'vendido', 'animal vendido muda de situação');
$venda = (int) $db('SELECT id FROM vendas')[0]['id'];
$a->enviar("/m/vendas/$venda", ['data' => $hoje, 'descricao' => 'Garrote 201', 'quantidade' => '1', 'valor_total' => '2500', 'comprador' => 'José', 'animal_id' => '201']);
ok((float) $db("SELECT valor FROM movimentacoes WHERE origem = 'venda'")[0]['valor'] === 2500.0, 'editar a venda atualiza o caixa');
$a->ir('/m/movimentacoes/' . $mov[0]['id']);
ok($a->tem('veio de uma venda'), 'lançamento da venda fica travado');
$a->ir('/m/movimentacoes/' . $mov[0]['id'] . '/excluir', ['_csrf' => $a->csrf()]);
ok(count($db("SELECT id FROM movimentacoes WHERE origem = 'venda'")) === 1, 'não exclui o lançamento travado');
$a->enviar('/m/movimentacoes/novo', ['data' => $hoje, 'tipo' => 'saida', 'categoria' => 'Ração e sal', 'valor' => '300,50']);
$a->enviar('/m/compras/novo', ['data' => $hoje, 'descricao' => 'Sal mineral', 'quantidade' => '10', 'valor_total' => '890']);
$a->ir('/m/movimentacoes');
ok($a->tem('Saldo <strong>R$ 1.309,50'), 'lista do financeiro mostra o saldo (2.500 - 300,50 - 890)');
$a->ir('/painel');
ok($a->tem('R$ 1.309,50'), 'saldo do mês no painel (2.500 - 300,50 - 890)');
$compra = (int) $db('SELECT id FROM compras')[0]['id'];
$a->ir("/m/compras/$compra");
$a->ir("/m/compras/$compra/excluir", ['_csrf' => $a->csrf()]);
ok(count($db("SELECT id FROM movimentacoes WHERE origem = 'compra'")) === 0, 'excluir a compra tira a saída do caixa');

echo "Relatórios, planilhas e importação\n";
$a->ir('/relatorios?de=' . $dias(-365) . '&ate=' . $hoje);
ok($a->status === 200 && $a->tem('Taxa de prenhez') && $a->tem('100%'), 'relatório abre com taxa de prenhez');
$a->ir('/m/animais/csv');
ok(str_contains($a->html, "Brinco / número") && str_contains($a->html, '101;'), 'baixa a planilha de animais');
$a->ir('/m/animais/csv?situacao=vendido');
ok(str_contains($a->html, '201;') && !str_contains($a->html, "\n101;"), 'planilha respeita o filtro');
$a->enviar('/importar', ['texto' => "brinco;nome;sexo;raca;categoria;nascimento;lote\n101;Repetida;F;;;;\n401;Nova;F;Gir;novilha;10/02/2024;Recria\n402;;M;;;;Recria\n403;;Z;;;;"]);
ok($a->tem('2 animal(is) cadastrado(s)') && $a->tem('1 já existiam') && $a->tem('sexo deve ser F ou M'), 'importação cria, pula repetidos e aponta erros');
ok(count($db("SELECT id FROM lotes WHERE nome = 'Recria'")) === 1, 'importação cria o lote novo uma vez só');
$a->ir('/m/animais?busca=40&lote_id=' . $lote);
ok($a->tem('0 registro(s)'), 'filtros combinados na lista');
$a->ir('/m/tipos_vacinas/' . $aftosa);
$a->ir('/m/tipos_vacinas/' . $aftosa . '/excluir', ['_csrf' => $a->csrf()]);
ok($a->tem('tem aplicações lançadas'), 'não exclui vacina em uso');

echo "Segurança e permissões\n";
$a->ir('/m/anotacoes/novo', ['titulo' => 'sem token', 'data' => $hoje]);
ok($a->status === 419, 'formulário sem token é recusado');
$b = new Navegador($tmp, 'b');
$b->enviar('/cadastro', ['nome' => 'Beto Vaqueiro', 'email' => 'beto@exemplo.com', 'senha' => 'cavalo-baio-77', 'senha2' => 'cavalo-baio-77']);
ok((int) $db("SELECT admin FROM usuarios WHERE email = 'beto@exemplo.com'")[0]['admin'] === 0, 'segunda conta não é administradora');
$b->ir("/animal/$a101");
ok($b->local === '/propriedades', 'sem propriedade, não vê animais de ninguém');
$b->enviar('/propriedades', ['nome' => 'Sítio do Beto', 'periodo_gestacao_dias' => '283']);
$b->ir("/animal/$a101");
ok($b->status === 404, 'não abre animal de outra fazenda');
$b->ir('/m/pesagens/' . $db('SELECT id FROM pesagens LIMIT 1')[0]['id']);
ok($b->status === 404, 'não abre lançamento de outra fazenda');
$b->ir('/m/animais/csv');
ok(!str_contains($b->html, '101;'), 'planilha só tem a própria fazenda');
$b->ir('/admin');
ok($b->status === 403, 'administração fechada para quem não é admin');

$a->enviar("/propriedades/$prop", ['acao' => 'adicionar_membro', 'email' => 'beto@exemplo.com', 'papel' => 'leitor']);
ok($a->tem('agora tem acesso'), 'dono dá acesso de consulta');
$b->ir('/propriedades');
$b->ir('/propriedades/ativar', ['_csrf' => $b->csrf(), 'id' => (string) $prop]);
$b->ir("/animal/$a101");
ok($b->status === 200 && $b->tem('modo de consulta'), 'leitor vê a fazenda em modo consulta');
$b->ir('/m/anotacoes/novo');
$b->ir('/m/anotacoes/novo', ['_csrf' => $b->csrf(), 'titulo' => 'tentativa', 'data' => $hoje]);
ok($b->status === 403 && count($db("SELECT id FROM anotacoes WHERE titulo = 'tentativa'")) === 0, 'leitor não consegue gravar');
$b->ir("/propriedades/$prop", ['_csrf' => $b->csrf(), 'acao' => 'adicionar_membro', 'email' => 'x@y.com', 'papel' => 'editor']);
ok($b->status === 403, 'leitor não mexe nos acessos');
$uB = (int) $db("SELECT id FROM usuarios WHERE email = 'beto@exemplo.com'")[0]['id'];
$a->enviar("/propriedades/$prop", ['acao' => 'mudar_papel', 'usuario_id' => (string) $uB, 'papel' => 'editor']);
$b->enviar('/m/anotacoes/novo', ['titulo' => 'Cerca do pasto 2 arrumada', 'data' => $hoje, 'texto' => 'ok']);
ok($b->local === '/m/anotacoes' && $b->tem('Cerca do pasto 2'), 'editor grava anotação');

$c = new Navegador($tmp, 'c');
for ($i = 0; $i < 8; $i++) $c->enviar('/entrar', ['email' => 'ana@exemplo.com', 'senha' => 'errada' . $i]);
$c->enviar('/entrar', ['email' => 'ana@exemplo.com', 'senha' => 'boi-gordo-2026']);
ok($c->tem('Muitas tentativas'), 'limita tentativas de senha por e-mail');

$a->enviar('/admin', ['acao' => 'nova_senha', 'usuario_id' => (string) $uB]);
preg_match('/<code class="destaque">([a-z0-9]{12})<\/code>/', $a->html, $m);
ok(isset($m[1]), 'admin gera senha provisória');
$d = new Navegador($tmp, 'd');
$d->enviar('/entrar', ['email' => 'beto@exemplo.com', 'senha' => $m[1] ?? '']);
ok($d->local === '/painel', 'entra com a senha provisória');
$a->enviar('/admin', ['acao' => 'bloquear', 'usuario_id' => (string) $uB]);
$d->ir('/painel');
ok($d->local === '/entrar', 'conta bloqueada perde a sessão na hora');

$a->ir('/painel');
$a->ir('/sair', ['_csrf' => $a->csrf()]);
ok($a->ir('/painel')->local === '/entrar', 'sair encerra a sessão');
$a->ir('/nao-existe');
ok($a->status === 404, 'página inexistente dá 404');

$logTxt = (string) file_get_contents($log);
$avisos = preg_grep('/(Warning|Notice|Deprecated|Fatal|Uncaught)/', explode("\n", $logTxt));
ok(!$avisos, 'nenhum aviso ou erro do PHP no servidor');
if ($avisos) echo implode("\n", array_slice($avisos, 0, 10)) . "\n";

echo "\n$total verificações, " . ($total - $falhas) . " passaram, $falhas falharam.\n";
exit($falhas ? 1 : 0);
