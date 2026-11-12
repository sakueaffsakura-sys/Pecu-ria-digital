<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Cadastros do sistema. Cada módulo diz a tabela, os campos e o que mostrar na lista;
// o CRUD (app/crud.php) faz o resto. Os nomes de tabela e coluna vêm só daqui, nunca do navegador.
//
// Tipos de campo: texto, area (texto longo), inteiro, decimal, dinheiro, data, opcao, ref (lista de outra
// tabela), animal (digita o brinco). "virtual" = campo do formulário que não é coluna da tabela.
// ---------------------------------------------------------------------------

const SEXOS = ['F' => 'Fêmea', 'M' => 'Macho'];

function modulos(): array
{
    static $m = null;
    if ($m !== null) return $m;

    $m = [
        'animais' => [
            'titulo' => 'Animais', 'singular' => 'animal', 'grupo' => 'Rebanho',
            'ordem' => 't.brinco', 'busca' => ['t.brinco', 't.nome', 't.raca'],
            'campos' => [
                'brinco' => ['rotulo' => 'Brinco / número', 'tipo' => 'texto', 'obrigatorio' => true, 'max' => 40],
                'nome' => ['rotulo' => 'Nome', 'tipo' => 'texto', 'max' => 80],
                'sexo' => ['rotulo' => 'Sexo', 'tipo' => 'opcao', 'obrigatorio' => true, 'opcoes' => SEXOS],
                'categoria' => ['rotulo' => 'Categoria', 'tipo' => 'opcao', 'opcoes' => [
                    'bezerro' => 'Bezerro(a)', 'novilha' => 'Novilha', 'vaca' => 'Vaca', 'garrote' => 'Garrote',
                    'boi' => 'Boi', 'touro' => 'Touro',
                ]],
                'raca' => ['rotulo' => 'Raça', 'tipo' => 'texto', 'max' => 60, 'sugestoes' => ['Nelore', 'Girolando', 'Gir', 'Holandesa', 'Angus', 'Brahman', 'Guzerá', 'Jersey', 'Senepol', 'Tabapuã', 'Mestiça']],
                'data_nascimento' => ['rotulo' => 'Nascimento', 'tipo' => 'data', 'nao_futura' => true],
                'lote_id' => ['rotulo' => 'Lote', 'tipo' => 'ref', 'ref' => 'lotes'],
                'mae_id' => ['rotulo' => 'Mãe (brinco)', 'tipo' => 'animal', 'sexo' => 'F'],
                'pai' => ['rotulo' => 'Pai / sêmen', 'tipo' => 'texto', 'max' => 80],
                'origem' => ['rotulo' => 'Origem', 'tipo' => 'opcao', 'obrigatorio' => true, 'padrao' => 'nascido',
                    'opcoes' => ['nascido' => 'Nascido na fazenda', 'comprado' => 'Comprado']],
                'situacao' => ['rotulo' => 'Situação', 'tipo' => 'opcao', 'obrigatorio' => true, 'padrao' => 'ativo',
                    'opcoes' => ['ativo' => 'Ativo', 'vendido' => 'Vendido', 'morto' => 'Morto', 'descartado' => 'Descartado']],
                'observacoes' => ['rotulo' => 'Observações', 'tipo' => 'area'],
            ],
            'colunas' => ['brinco', 'nome', 'sexo', 'categoria', 'raca', 'lote_id', 'situacao'],
            'filtros' => ['situacao', 'sexo', 'lote_id'],
            'ficha' => true,
            'antes_salvar' => function (array $d, ?int $id): array|string {
                if ($id && ($d['mae_id'] ?? null) === $id) return 'O animal não pode ser mãe dele mesmo.';
                if ($id) $d['atualizado_em'] = date('Y-m-d H:i:s');
                return $d;
            },
        ],

        'lotes' => [
            'titulo' => 'Lotes', 'singular' => 'lote', 'grupo' => 'Rebanho',
            'ordem' => 't.nome', 'busca' => ['t.nome', 't.descricao'],
            'campos' => [
                'nome' => ['rotulo' => 'Nome do lote', 'tipo' => 'texto', 'obrigatorio' => true, 'max' => 80],
                'descricao' => ['rotulo' => 'Descrição (pasto, finalidade…)', 'tipo' => 'texto', 'max' => 255],
            ],
            'colunas' => ['nome', 'descricao'],
            'contagem' => ['rotulo' => 'Animais ativos', 'sql' => "(SELECT COUNT(*) FROM animais a WHERE a.lote_id = t.id AND a.situacao = 'ativo')"],
        ],

        'pesagens' => [
            'titulo' => 'Pesagens', 'singular' => 'pesagem', 'feminino' => true, 'grupo' => 'Rebanho',
            'ordem' => 't.data DESC, t.id DESC', 'busca' => ['r_animal_id.brinco'], 'data' => 'data',
            'campos' => [
                'animal_id' => ['rotulo' => 'Animal (brinco)', 'tipo' => 'animal', 'obrigatorio' => true],
                'data' => ['rotulo' => 'Data', 'tipo' => 'data', 'obrigatorio' => true, 'nao_futura' => true, 'padrao_hoje' => true],
                'peso_kg' => ['rotulo' => 'Peso (kg)', 'tipo' => 'decimal', 'obrigatorio' => true, 'min' => 0.1, 'max' => 3000],
                'observacoes' => ['rotulo' => 'Observações', 'tipo' => 'texto', 'max' => 255],
            ],
            'colunas' => ['data', 'animal_id', 'peso_kg', 'observacoes'],
            'duplicado' => 'Esse animal já tem uma pesagem nesse dia.',
        ],

        'tipos_vacinas' => [
            'titulo' => 'Vacinas e remédios', 'singular' => 'vacina', 'feminino' => true, 'grupo' => 'Sanidade',
            'ordem' => 't.nome', 'busca' => ['t.nome'],
            'campos' => [
                'nome' => ['rotulo' => 'Nome', 'tipo' => 'texto', 'obrigatorio' => true, 'max' => 80,
                    'sugestoes' => ['Febre aftosa', 'Brucelose', 'Raiva', 'Clostridioses', 'Leptospirose', 'IBR/BVD', 'Vermífugo', 'Carrapaticida']],
                'intervalo_dias' => ['rotulo' => 'Repetir a cada (dias)', 'tipo' => 'inteiro', 'min' => 1, 'max' => 3650,
                    'ajuda' => 'Usado para calcular a próxima dose. Deixe vazio se for dose única.'],
                'observacoes' => ['rotulo' => 'Observações', 'tipo' => 'texto', 'max' => 255],
            ],
            'colunas' => ['nome', 'intervalo_dias', 'observacoes'],
            'em_uso' => 'Esta vacina tem aplicações lançadas. Exclua as aplicações antes.',
        ],

        'vacinacoes' => [
            'titulo' => 'Vacinações', 'singular' => 'vacinação', 'feminino' => true, 'grupo' => 'Sanidade',
            'ordem' => 't.data DESC, t.id DESC', 'busca' => ['r_animal_id.brinco', 'r_tipo_vacina_id.nome', 'r_lote_id.nome'], 'data' => 'data',
            'campos' => [
                'tipo_vacina_id' => ['rotulo' => 'Vacina / remédio', 'tipo' => 'ref', 'ref' => 'tipos_vacinas', 'obrigatorio' => true],
                'animal_id' => ['rotulo' => 'Animal (brinco)', 'tipo' => 'animal', 'ajuda' => 'Preencha o animal ou o lote.'],
                'lote_id' => ['rotulo' => 'Lote inteiro', 'tipo' => 'ref', 'ref' => 'lotes'],
                'data' => ['rotulo' => 'Data da aplicação', 'tipo' => 'data', 'obrigatorio' => true, 'nao_futura' => true, 'padrao_hoje' => true],
                'dose' => ['rotulo' => 'Dose', 'tipo' => 'texto', 'max' => 40],
                'proxima_dose' => ['rotulo' => 'Próxima dose', 'tipo' => 'data', 'ajuda' => 'Vazio = calculada pelo intervalo da vacina.'],
                'responsavel' => ['rotulo' => 'Responsável', 'tipo' => 'texto', 'max' => 80],
                'observacoes' => ['rotulo' => 'Observações', 'tipo' => 'texto', 'max' => 255],
            ],
            'colunas' => ['data', 'tipo_vacina_id', 'animal_id', 'lote_id', 'proxima_dose'],
            'filtros' => ['tipo_vacina_id', 'lote_id'],
            'antes_salvar' => function (array $d, ?int $id): array|string {
                if (empty($d['animal_id']) && empty($d['lote_id'])) return 'Informe o animal ou o lote vacinado.';
                if ($d['proxima_dose'] === null) {
                    $intervalo = valor('SELECT intervalo_dias FROM tipos_vacinas WHERE id = ?', [$d['tipo_vacina_id']]);
                    if ($intervalo) $d['proxima_dose'] = somar_dias($d['data'], (int) $intervalo);
                } elseif ($d['proxima_dose'] <= $d['data']) {
                    return 'A próxima dose precisa ser depois da aplicação.';
                }
                return $d;
            },
        ],

        'inseminacoes' => [
            'titulo' => 'Inseminações', 'singular' => 'inseminação', 'feminino' => true, 'grupo' => 'Reprodução',
            'ordem' => 't.data DESC, t.id DESC', 'busca' => ['r_animal_id.brinco', 't.touro_semen'], 'data' => 'data',
            'campos' => [
                'animal_id' => ['rotulo' => 'Fêmea (brinco)', 'tipo' => 'animal', 'sexo' => 'F', 'obrigatorio' => true],
                'data' => ['rotulo' => 'Data', 'tipo' => 'data', 'obrigatorio' => true, 'nao_futura' => true, 'padrao_hoje' => true],
                'tipo' => ['rotulo' => 'Tipo', 'tipo' => 'opcao', 'obrigatorio' => true, 'padrao' => 'IA',
                    'opcoes' => ['IA' => 'Inseminação artificial', 'IATF' => 'IATF', 'monta' => 'Monta natural', 'TE' => 'Transferência de embrião']],
                'touro_semen' => ['rotulo' => 'Touro / sêmen', 'tipo' => 'texto', 'max' => 80],
                'tecnico' => ['rotulo' => 'Técnico', 'tipo' => 'texto', 'max' => 80],
                'resultado' => ['rotulo' => 'Diagnóstico', 'tipo' => 'opcao', 'obrigatorio' => true, 'padrao' => 'pendente',
                    'opcoes' => ['pendente' => 'Aguardando', 'prenhe' => 'Prenhe', 'vazia' => 'Vazia']],
                'previsao_parto' => ['rotulo' => 'Previsão de parto', 'tipo' => 'data', 'ajuda' => 'Vazio = calculada pelo período de gestação da propriedade.'],
                'observacoes' => ['rotulo' => 'Observações', 'tipo' => 'texto', 'max' => 255],
            ],
            'colunas' => ['data', 'animal_id', 'tipo', 'touro_semen', 'resultado', 'previsao_parto'],
            'filtros' => ['resultado'],
            'antes_salvar' => function (array $d, ?int $id): array|string {
                if ($d['resultado'] === 'vazia') {
                    $d['previsao_parto'] = null;
                } elseif ($d['previsao_parto'] === null) {
                    $d['previsao_parto'] = somar_dias($d['data'], (int) propriedade()['periodo_gestacao_dias']);
                }
                return $d;
            },
        ],

        'nascimentos' => [
            'titulo' => 'Nascimentos', 'singular' => 'nascimento', 'grupo' => 'Reprodução',
            'ordem' => 't.data DESC, t.id DESC', 'busca' => ['r_mae_id.brinco', 'r_bezerro_id.brinco'], 'data' => 'data',
            'campos' => [
                'mae_id' => ['rotulo' => 'Mãe (brinco)', 'tipo' => 'animal', 'sexo' => 'F', 'obrigatorio' => true],
                'data' => ['rotulo' => 'Data do parto', 'tipo' => 'data', 'obrigatorio' => true, 'nao_futura' => true, 'padrao_hoje' => true],
                'sexo' => ['rotulo' => 'Sexo da cria', 'tipo' => 'opcao', 'obrigatorio' => true, 'opcoes' => SEXOS],
                'peso_kg' => ['rotulo' => 'Peso ao nascer (kg)', 'tipo' => 'decimal', 'min' => 1, 'max' => 150],
                'brinco_cria' => ['rotulo' => 'Brinco da cria', 'tipo' => 'texto', 'max' => 40, 'virtual' => true, 'so_novo' => true,
                    'ajuda' => 'Se preencher, a cria já entra no cadastro de animais.'],
                'bezerro_id' => ['rotulo' => 'Cria (brinco)', 'tipo' => 'animal', 'so_edicao' => true],
                'observacoes' => ['rotulo' => 'Observações', 'tipo' => 'texto', 'max' => 255],
            ],
            'colunas' => ['data', 'mae_id', 'sexo', 'peso_kg', 'bezerro_id'],
            'antes_salvar' => function (array $d, ?int $id): array|string {
                $brinco = $d['brinco_cria'] ?? null;
                unset($d['brinco_cria']);
                if (!$id && $brinco) {
                    $existe = valor('SELECT id FROM animais WHERE propriedade_id = ? AND brinco = ?', [propriedade()['id'], $brinco]);
                    if ($existe) return "Já existe um animal com o brinco $brinco.";
                    $mae = um('SELECT lote_id, raca FROM animais WHERE id = ?', [$d['mae_id']]);
                    $d['bezerro_id'] = inserir('animais', [
                        'propriedade_id' => propriedade()['id'], 'brinco' => $brinco, 'sexo' => $d['sexo'],
                        'categoria' => 'bezerro', 'data_nascimento' => $d['data'], 'mae_id' => $d['mae_id'],
                        'lote_id' => $mae['lote_id'] ?? null, 'raca' => $mae['raca'] ?? null, 'origem' => 'nascido', 'situacao' => 'ativo',
                    ]);
                    if ($d['peso_kg']) {
                        inserir('pesagens', ['propriedade_id' => propriedade()['id'], 'animal_id' => $d['bezerro_id'], 'data' => $d['data'], 'peso_kg' => $d['peso_kg'], 'observacoes' => 'Peso ao nascer']);
                    }
                }
                return $d;
            },
        ],

        'producao_leite' => [
            'titulo' => 'Produção de leite', 'singular' => 'ordenha', 'feminino' => true, 'grupo' => 'Leite',
            'ordem' => 't.data DESC, t.id DESC', 'busca' => ['r_animal_id.brinco'], 'data' => 'data',
            'campos' => [
                'data' => ['rotulo' => 'Data', 'tipo' => 'data', 'obrigatorio' => true, 'nao_futura' => true, 'padrao_hoje' => true],
                'turno' => ['rotulo' => 'Turno', 'tipo' => 'opcao', 'obrigatorio' => true, 'padrao' => 'dia',
                    'opcoes' => ['manha' => 'Manhã', 'tarde' => 'Tarde', 'noite' => 'Noite', 'dia' => 'Dia todo']],
                'litros' => ['rotulo' => 'Litros', 'tipo' => 'decimal', 'obrigatorio' => true, 'min' => 0.1, 'max' => 100000],
                'animal_id' => ['rotulo' => 'Vaca (brinco)', 'tipo' => 'animal', 'sexo' => 'F', 'ajuda' => 'Deixe vazio para lançar o total do rebanho.'],
                'observacoes' => ['rotulo' => 'Observações', 'tipo' => 'texto', 'max' => 255],
            ],
            'colunas' => ['data', 'turno', 'animal_id', 'litros'],
            'total' => 'litros',
        ],

        'anotacoes' => [
            'titulo' => 'Anotações', 'singular' => 'anotação', 'feminino' => true, 'grupo' => 'Geral',
            'ordem' => 't.data DESC, t.id DESC', 'busca' => ['t.titulo', 't.texto'], 'data' => 'data',
            'campos' => [
                'data' => ['rotulo' => 'Data', 'tipo' => 'data', 'obrigatorio' => true, 'padrao_hoje' => true],
                'titulo' => ['rotulo' => 'Título', 'tipo' => 'texto', 'obrigatorio' => true, 'max' => 120],
                'texto' => ['rotulo' => 'Anotação', 'tipo' => 'area'],
            ],
            'colunas' => ['data', 'titulo', 'texto'],
            'antes_salvar' => function (array $d, ?int $id): array|string {
                if (!$id) $d['autor_id'] = usuario()['id'];
                return $d;
            },
        ],

        'compras' => [
            'titulo' => 'Compras', 'singular' => 'compra', 'feminino' => true, 'grupo' => 'Financeiro',
            'ordem' => 't.data DESC, t.id DESC', 'busca' => ['t.descricao', 't.fornecedor'], 'data' => 'data',
            'campos' => [
                'data' => ['rotulo' => 'Data', 'tipo' => 'data', 'obrigatorio' => true, 'padrao_hoje' => true],
                'descricao' => ['rotulo' => 'O que foi comprado', 'tipo' => 'texto', 'obrigatorio' => true, 'max' => 160],
                'quantidade' => ['rotulo' => 'Quantidade', 'tipo' => 'inteiro', 'obrigatorio' => true, 'min' => 1, 'padrao' => '1'],
                'valor_total' => ['rotulo' => 'Valor total (R$)', 'tipo' => 'dinheiro', 'obrigatorio' => true, 'min' => 0],
                'fornecedor' => ['rotulo' => 'Fornecedor', 'tipo' => 'texto', 'max' => 120],
                'animal_id' => ['rotulo' => 'Animal (brinco, se for um só)', 'tipo' => 'animal'],
                'observacoes' => ['rotulo' => 'Observações', 'tipo' => 'texto', 'max' => 255],
            ],
            'colunas' => ['data', 'descricao', 'quantidade', 'fornecedor', 'valor_total'],
            'total' => 'valor_total',
            'depois_salvar' => fn(int $id, array $d) => sincronizar_lancamento('compra', $id, $d),
            'depois_excluir' => fn(int $id) => q("DELETE FROM movimentacoes WHERE origem = 'compra' AND origem_id = ? AND propriedade_id = ?", [$id, propriedade()['id']]),
        ],

        'vendas' => [
            'titulo' => 'Vendas', 'singular' => 'venda', 'feminino' => true, 'grupo' => 'Financeiro',
            'ordem' => 't.data DESC, t.id DESC', 'busca' => ['t.descricao', 't.comprador'], 'data' => 'data',
            'campos' => [
                'data' => ['rotulo' => 'Data', 'tipo' => 'data', 'obrigatorio' => true, 'padrao_hoje' => true],
                'descricao' => ['rotulo' => 'O que foi vendido', 'tipo' => 'texto', 'obrigatorio' => true, 'max' => 160],
                'quantidade' => ['rotulo' => 'Quantidade', 'tipo' => 'inteiro', 'obrigatorio' => true, 'min' => 1, 'padrao' => '1'],
                'valor_total' => ['rotulo' => 'Valor total (R$)', 'tipo' => 'dinheiro', 'obrigatorio' => true, 'min' => 0],
                'comprador' => ['rotulo' => 'Comprador', 'tipo' => 'texto', 'max' => 120],
                'animal_id' => ['rotulo' => 'Animal (brinco, se for um só)', 'tipo' => 'animal',
                    'ajuda' => 'O animal passa para a situação "vendido".'],
                'observacoes' => ['rotulo' => 'Observações', 'tipo' => 'texto', 'max' => 255],
            ],
            'colunas' => ['data', 'descricao', 'quantidade', 'comprador', 'valor_total'],
            'total' => 'valor_total',
            'depois_salvar' => function (int $id, array $d): void {
                sincronizar_lancamento('venda', $id, $d);
                if (!empty($d['animal_id'])) {
                    q("UPDATE animais SET situacao = 'vendido', atualizado_em = ? WHERE id = ? AND propriedade_id = ?", [date('Y-m-d H:i:s'), $d['animal_id'], propriedade()['id']]);
                }
            },
            'depois_excluir' => fn(int $id) => q("DELETE FROM movimentacoes WHERE origem = 'venda' AND origem_id = ? AND propriedade_id = ?", [$id, propriedade()['id']]),
        ],

        'movimentacoes' => [
            'titulo' => 'Entradas e saídas', 'singular' => 'lançamento', 'grupo' => 'Financeiro',
            'ordem' => 't.data DESC, t.id DESC', 'busca' => ['t.descricao', 't.categoria'], 'data' => 'data',
            'campos' => [
                'data' => ['rotulo' => 'Data', 'tipo' => 'data', 'obrigatorio' => true, 'padrao_hoje' => true],
                'tipo' => ['rotulo' => 'Tipo', 'tipo' => 'opcao', 'obrigatorio' => true, 'opcoes' => ['entrada' => 'Entrada (receita)', 'saida' => 'Saída (despesa)']],
                'categoria' => ['rotulo' => 'Categoria', 'tipo' => 'texto', 'obrigatorio' => true, 'max' => 60,
                    'sugestoes' => ['Venda de leite', 'Venda de animais', 'Ração e sal', 'Medicamentos', 'Mão de obra', 'Combustível', 'Energia', 'Manutenção', 'Arrendamento', 'Impostos', 'Outros']],
                'descricao' => ['rotulo' => 'Descrição', 'tipo' => 'texto', 'max' => 160],
                'valor' => ['rotulo' => 'Valor (R$)', 'tipo' => 'dinheiro', 'obrigatorio' => true, 'min' => 0.01],
            ],
            'colunas' => ['data', 'tipo', 'categoria', 'descricao', 'valor'],
            'filtros' => ['tipo'],
            'saldo' => true,
            'travado' => fn(array $linha): ?string => $linha['origem'] !== 'manual'
                ? 'Este lançamento veio de uma ' . $linha['origem'] . '. Altere ou exclua pela tela de ' . ($linha['origem'] === 'compra' ? 'compras' : 'vendas') . '.'
                : null,
        ],
    ];
    return $m;
}

/** "o" ou "a", conforme o nome do registro: novo animal, nova vacinação. */
function g(array $m, string $o = 'o', string $a = 'a'): string
{
    return empty($m['feminino']) ? $o : $a;
}

function modulo(string $slug): ?array
{
    $m = modulos()[$slug] ?? null;
    if ($m) $m['slug'] = $slug;
    return $m;
}

/** Tabelas que podem ser escolhidas em campos "ref", e a coluna mostrada. */
function rotulo_ref(string $tabela): string
{
    return match ($tabela) {
        'lotes', 'tipos_vacinas' => 'nome',
        'animais' => 'brinco',
        default => throw new LogicException("Tabela sem rótulo: $tabela"),
    };
}

/** Compra e venda geram um lançamento no financeiro (e o mantêm igual quando são editadas). */
function sincronizar_lancamento(string $origem, int $id, array $d): void
{
    $prop = propriedade()['id'];
    $dados = [
        'data' => $d['data'],
        'tipo' => $origem === 'venda' ? 'entrada' : 'saida',
        'categoria' => $origem === 'venda' ? 'Venda' : 'Compra',
        'descricao' => mb_substr($d['descricao'], 0, 160),
        'valor' => $d['valor_total'],
    ];
    $existente = valor('SELECT id FROM movimentacoes WHERE origem = ? AND origem_id = ? AND propriedade_id = ?', [$origem, $id, $prop]);
    if ($existente) {
        atualizar('movimentacoes', $dados, 'id = ?', [$existente]);
    } else {
        inserir('movimentacoes', $dados + ['propriedade_id' => $prop, 'origem' => $origem, 'origem_id' => $id]);
    }
}
