<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Cadastros, iguais aos do sistema original (mesmos títulos, textos, colunas e campos).
// Tipos de campo: text, textarea, number, date, select, animal (brinco ou nome), lote, vacina.
// animal/lote/vacina: a pessoa digita; se não existir, o cadastro é criado sozinho (como no original).
// ---------------------------------------------------------------------------

const SEXOS = ['F' => 'Fêmea', 'M' => 'Macho'];
const SITUACOES_ANIMAL = ['ativo' => 'Ativo', 'vendido' => 'Vendido', 'perdido' => 'Perdido', 'falecido' => 'Morto', 'transferido' => 'Transferido'];
const SITUACOES_INSEMINACAO = ['em_andamento' => 'Em andamento', 'confirmada' => 'Confirmada', 'falhou' => 'Falhou', 'parto_realizado' => 'Parto realizado', 'cancelada' => 'Cancelada'];
const TIPOS_MOVIMENTO = ['receita' => 'Receita', 'despesa' => 'Despesa', 'investimento' => 'Investimento'];

function modulos(): array
{
    static $m = null;
    if ($m !== null) return $m;
    $m = [
        'rebanho' => [
            'titulo' => 'Meu rebanho', 'singular' => 'animal', 'novo' => 'Novo animal', 'primeiro' => 'Cadastrar o primeiro animal',
            'tabela' => 'animais', 'icone' => 'rebanho',
            'descricao' => 'Todos os animais, com brinco, lote e situação. Toque num animal para ver a ficha.',
            'busca' => ['numero_brinco', 'nome', 'raca'], 'dica_busca' => 'Brinco, nome ou raça',
            'colunas' => ['numero_brinco' => 'Brinco', 'nome' => 'Nome', 'sexo' => 'Sexo', 'raca' => 'Raça', 'situacao' => 'Situação'],
            'campos' => [
                'numero_brinco' => ['rotulo' => 'Número do brinco', 'obrigatorio' => true, 'max' => 60],
                'nome' => ['rotulo' => 'Nome', 'max' => 120],
                'sexo' => ['rotulo' => 'Sexo', 'tipo' => 'select', 'obrigatorio' => true, 'opcoes' => SEXOS],
                'raca' => ['rotulo' => 'Raça', 'max' => 80],
                'data_nascimento' => ['rotulo' => 'Data de nascimento', 'tipo' => 'date', 'sem_padrao' => true],
                'lote_id' => ['rotulo' => 'Lote', 'tipo' => 'lote'],
                'mae_id' => ['rotulo' => 'Mãe', 'tipo' => 'animal', 'sexo' => 'F'],
                'pai_id' => ['rotulo' => 'Pai', 'tipo' => 'animal', 'sexo' => 'M'],
                'situacao' => ['rotulo' => 'Situação', 'tipo' => 'select', 'obrigatorio' => true, 'opcoes' => SITUACOES_ANIMAL],
                'observacoes' => ['rotulo' => 'Observações', 'tipo' => 'textarea'],
            ],
            'ordem' => 't.created_at DESC, t.id DESC',
        ],
        'bezerros' => [
            'titulo' => 'Evolução dos bezerros', 'singular' => 'pesagem', 'novo' => 'Nova pesagem', 'primeiro' => 'Registrar a primeira pesagem',
            'tabela' => 'pesagens', 'icone' => 'bezerro',
            'descricao' => 'Pesagens dos animais jovens, para ver se estão ganhando peso.',
            'busca' => ['data'], 'dica_busca' => 'Buscar em evolução dos bezerros',
            'colunas' => ['animal_id' => 'Animal', 'data' => 'Data', 'peso_kg' => 'Peso (kg)', 'observacoes' => 'Observações'],
            'campos' => [
                'animal_id' => ['rotulo' => 'Bezerro', 'tipo' => 'animal', 'obrigatorio' => true, 'so_ativos' => true],
                'data' => ['rotulo' => 'Data', 'tipo' => 'date', 'obrigatorio' => true],
                'peso_kg' => ['rotulo' => 'Peso (kg)', 'tipo' => 'number', 'obrigatorio' => true],
                'observacoes' => ['rotulo' => 'Observações', 'tipo' => 'textarea'],
            ],
        ],
        'vacinacao' => [
            'titulo' => 'Vacinação', 'singular' => 'aplicação', 'novo' => 'Nova aplicação', 'primeiro' => 'Registrar a primeira vacina',
            'tabela' => 'vacinacoes', 'icone' => 'syringe',
            'descricao' => 'Cada aplicação, a próxima dose e o que está atrasado.',
            'busca' => ['responsavel'], 'dica_busca' => 'Buscar em vacinação',
            'colunas' => ['tipo_vacina_id' => 'Vacina', 'animal_id' => 'Animal', 'data_aplicacao' => 'Aplicação', 'proxima_aplicacao' => 'Próxima', 'responsavel' => 'Responsável'],
            'campos' => [
                'tipo_vacina_id' => ['rotulo' => 'Tipo de vacina', 'tipo' => 'vacina', 'obrigatorio' => true],
                'animal_id' => ['rotulo' => 'Animal', 'tipo' => 'animal', 'so_ativos' => true],
                'lote_id' => ['rotulo' => 'Lote', 'tipo' => 'lote'],
                'data_aplicacao' => ['rotulo' => 'Data da aplicação', 'tipo' => 'date', 'obrigatorio' => true],
                'proxima_aplicacao' => ['rotulo' => 'Próxima aplicação', 'tipo' => 'date', 'sem_padrao' => true, 'dica' => 'Vazio: calculada pelo intervalo da vacina'],
                'responsavel' => ['rotulo' => 'Responsável', 'max' => 120],
                'observacao' => ['rotulo' => 'Observação', 'tipo' => 'textarea'],
            ],
            'abas' => ['vacinacao' => 'Aplicações', 'tipos_vacinas' => 'Tipos de vacina'],
        ],
        'tipos_vacinas' => [
            'titulo' => 'Tipos de vacina', 'singular' => 'tipo de vacina', 'novo' => 'Novo tipo de vacina', 'primeiro' => 'Cadastrar a primeira vacina',
            'tabela' => 'tipos_vacinas', 'icone' => 'shield-check', 'secao' => 'vacinacao',
            'descricao' => 'As vacinas que você usa e de quantos em quantos dias repetir.',
            'busca' => ['nome'], 'dica_busca' => 'Buscar em tipos de vacina',
            'colunas' => ['nome' => 'Nome', 'intervalo_dias' => 'Intervalo (dias)', 'descricao' => 'Descrição', 'ativo' => 'Status'],
            'campos' => [
                'nome' => ['rotulo' => 'Nome', 'obrigatorio' => true, 'max' => 120],
                'intervalo_dias' => ['rotulo' => 'Intervalo em dias', 'tipo' => 'number', 'inteiro' => true],
                'descricao' => ['rotulo' => 'Descrição', 'tipo' => 'textarea'],
            ],
            'ordem' => 't.nome',
            'abas' => ['vacinacao' => 'Aplicações', 'tipos_vacinas' => 'Tipos de vacina'],
            'em_uso' => 'Esta vacina tem aplicações registradas. Exclua as aplicações antes.',
        ],
        'inseminacao' => [
            'titulo' => 'Inseminação', 'singular' => 'inseminação', 'novo' => 'Nova inseminação', 'primeiro' => 'Registrar a primeira inseminação',
            'tabela' => 'inseminacoes', 'icone' => 'flask-conical',
            'descricao' => 'Inseminações, situação e a previsão de parto de cada vaca.',
            'busca' => ['reprodutor_semen'], 'dica_busca' => 'Buscar em inseminação',
            'colunas' => ['animal_id' => 'Animal', 'data_inseminacao' => 'Data', 'previsao_parto' => 'Previsão de parto', 'situacao' => 'Situação'],
            'campos' => [
                'animal_id' => ['rotulo' => 'Animal', 'tipo' => 'animal', 'obrigatorio' => true, 'sexo' => 'F', 'so_ativos' => true],
                'data_inseminacao' => ['rotulo' => 'Data da inseminação', 'tipo' => 'date', 'obrigatorio' => true],
                'reprodutor_semen' => ['rotulo' => 'Reprodutor / sêmen', 'obrigatorio' => true, 'max' => 160],
                'previsao_parto' => ['rotulo' => 'Previsão de parto', 'tipo' => 'date', 'sem_padrao' => true, 'dica' => 'Vazio: data + gestação da propriedade'],
                'situacao' => ['rotulo' => 'Situação', 'tipo' => 'select', 'obrigatorio' => true, 'opcoes' => SITUACOES_INSEMINACAO],
                'observacao' => ['rotulo' => 'Observação', 'tipo' => 'textarea'],
            ],
        ],
        'nascimentos' => [
            'titulo' => 'Nascimentos', 'singular' => 'nascimento', 'novo' => 'Novo nascimento', 'primeiro' => 'Registrar o primeiro nascimento',
            'tabela' => 'nascimentos', 'icone' => 'heart-pulse',
            'descricao' => 'Quem nasceu, de qual vaca e quando.',
            'busca' => [], 'dica_busca' => 'Buscar em nascimentos',
            'colunas' => ['vaca_id' => 'Vaca', 'bezerro_id' => 'Bezerro', 'data_nascimento' => 'Nascimento', 'sexo' => 'Sexo'],
            'campos' => [
                'vaca_id' => ['rotulo' => 'Vaca', 'tipo' => 'animal', 'obrigatorio' => true, 'sexo' => 'F', 'so_ativos' => true],
                'bezerro_id' => ['rotulo' => 'Bezerro', 'tipo' => 'animal', 'obrigatorio' => true, 'so_ativos' => true, 'sexo_do_campo' => 'sexo'],
                'data_nascimento' => ['rotulo' => 'Data', 'tipo' => 'date', 'obrigatorio' => true],
                'sexo' => ['rotulo' => 'Sexo', 'tipo' => 'select', 'obrigatorio' => true, 'opcoes' => SEXOS],
                'lote_id' => ['rotulo' => 'Lote', 'tipo' => 'lote'],
                'observacoes' => ['rotulo' => 'Observações', 'tipo' => 'textarea'],
            ],
            'duplicado' => 'Esse bezerro já possui um nascimento registrado.',
        ],
        'lotes' => [
            'titulo' => 'Lotes', 'singular' => 'lote', 'novo' => 'Novo lote', 'primeiro' => 'Criar o primeiro lote',
            'tabela' => 'lotes', 'icone' => 'warehouse',
            'descricao' => 'Grupos de animais por pasto ou finalidade.',
            'busca' => ['nome', 'finalidade', 'descricao'], 'dica_busca' => 'Buscar em lotes',
            'colunas' => ['nome' => 'Nome', 'finalidade' => 'Finalidade', 'descricao' => 'Descrição', 'ativo' => 'Status'],
            'campos' => [
                'nome' => ['rotulo' => 'Nome', 'obrigatorio' => true, 'max' => 120],
                'finalidade' => ['rotulo' => 'Finalidade', 'max' => 120],
                'descricao' => ['rotulo' => 'Descrição', 'tipo' => 'textarea'],
            ],
            'ordem' => 't.nome',
        ],
        'producao' => [
            'titulo' => 'Leite', 'singular' => 'registro de leite', 'novo' => 'Registrar leite', 'primeiro' => 'Registrar o leite de hoje',
            'tabela' => 'producao_leite', 'icone' => 'milk',
            'descricao' => 'Litros de cada vaca por dia. O total em reais é calculado sozinho.',
            'busca' => [], 'dica_busca' => 'Buscar em leite',
            'colunas' => ['animal_id' => 'Animal', 'data' => 'Data', 'litros' => 'Litros', 'valor_litro' => 'R$ / litro', 'valor_total' => 'Total'],
            'campos' => [
                'animal_id' => ['rotulo' => 'Animal', 'tipo' => 'animal', 'obrigatorio' => true, 'sexo' => 'F', 'so_ativos' => true],
                'lote_id' => ['rotulo' => 'Lote', 'tipo' => 'lote'],
                'data' => ['rotulo' => 'Data', 'tipo' => 'date', 'obrigatorio' => true],
                'litros' => ['rotulo' => 'Litros', 'tipo' => 'number', 'obrigatorio' => true],
                'valor_litro' => ['rotulo' => 'Valor por litro', 'tipo' => 'number', 'obrigatorio' => true],
                'observacao' => ['rotulo' => 'Observação', 'tipo' => 'textarea'],
            ],
            'abas' => ['producao' => 'Registros de leite', 'anotacoes' => 'Anotações'],
            'somente_leitura' => ['valor_total'],
        ],
        'anotacoes' => [
            'titulo' => 'Anotações', 'singular' => 'anotação', 'novo' => 'Nova anotação', 'primeiro' => 'Escrever a primeira anotação',
            'tabela' => 'anotacoes_producao', 'icone' => 'notebook-pen', 'secao' => 'producao',
            'descricao' => 'O caderno da fazenda: o que aconteceu, com qual animal e em que dia.',
            'busca' => ['observacao', 'comportamento', 'alimentacao', 'informacoes_adicionais'], 'dica_busca' => 'Texto, brinco ou nome do animal',
            'colunas' => ['data' => 'Data', 'animal_id' => 'Animal', 'lote_id' => 'Lote', 'producao' => 'Produção (L)', 'observacao' => 'Observação'],
            'campos' => [
                'observacao' => ['rotulo' => 'O que aconteceu', 'tipo' => 'textarea', 'obrigatorio' => true, 'dica' => 'Ex.: Cio observado, produção caiu, vacina aplicada…'],
                'data' => ['rotulo' => 'Data', 'tipo' => 'date', 'obrigatorio' => true],
                'animal_id' => ['rotulo' => 'Animal', 'tipo' => 'animal', 'so_ativos' => true, 'dica' => 'Nenhum animal em especial'],
                'lote_id' => ['rotulo' => 'Lote', 'tipo' => 'lote', 'dica' => 'Nenhum lote em especial'],
                'comportamento' => ['rotulo' => 'Comportamento', 'avancado' => true],
                'alimentacao' => ['rotulo' => 'Alimentação', 'avancado' => true],
                'producao' => ['rotulo' => 'Produção do dia (litros)', 'tipo' => 'number', 'avancado' => true],
                'informacoes_adicionais' => ['rotulo' => 'Informações adicionais', 'tipo' => 'textarea', 'avancado' => true],
            ],
            'abas' => ['producao' => 'Registros de leite', 'anotacoes' => 'Anotações'],
            'ordem' => 't.data DESC, t.created_at DESC',
        ],
        'financeiro' => [
            'titulo' => 'Lucros e investimentos', 'singular' => 'lançamento', 'novo' => 'Novo lançamento', 'primeiro' => 'Fazer o primeiro lançamento',
            'tabela' => 'movimentacoes_financeiras', 'icone' => 'circle-dollar-sign',
            'descricao' => 'Receitas, despesas e investimentos lançados à mão. Compras e vendas entram sozinhas por Compra e venda.',
            'busca' => ['descricao', 'categoria'], 'dica_busca' => 'Buscar em lucros e investimentos',
            'filtro_fixo' => 't.origem_tipo IS NULL',
            'colunas' => ['tipo' => 'Tipo', 'descricao' => 'Descrição', 'categoria' => 'Categoria', 'data' => 'Data', 'valor' => 'Valor'],
            'campos' => [
                'tipo' => ['rotulo' => 'Tipo', 'tipo' => 'select', 'obrigatorio' => true, 'opcoes' => TIPOS_MOVIMENTO],
                'descricao' => ['rotulo' => 'Descrição', 'obrigatorio' => true, 'max' => 255],
                'categoria' => ['rotulo' => 'Categoria', 'obrigatorio' => true, 'max' => 120],
                'valor' => ['rotulo' => 'Valor', 'tipo' => 'number', 'obrigatorio' => true],
                'data' => ['rotulo' => 'Data', 'tipo' => 'date', 'obrigatorio' => true],
                'observacao' => ['rotulo' => 'Observação', 'tipo' => 'textarea'],
            ],
        ],
        'compras' => [
            'titulo' => 'Compras', 'singular' => 'compra', 'novo' => 'Nova compra', 'primeiro' => 'Registrar a primeira compra',
            'tabela' => 'compras', 'icone' => 'shopping-cart', 'secao' => 'comercial',
            'descricao' => 'O que entrou na fazenda. O valor vai sozinho para o financeiro.',
            'busca' => ['produto_animal', 'fornecedor'], 'dica_busca' => 'Buscar em compras',
            'colunas' => ['data' => 'Data', 'produto_animal' => 'Produto ou animal', 'quantidade' => 'Quantidade', 'fornecedor' => 'Fornecedor', 'valor' => 'Valor'],
            'campos' => [
                'data' => ['rotulo' => 'Data', 'tipo' => 'date', 'obrigatorio' => true],
                'produto_animal' => ['rotulo' => 'Produto ou animal', 'obrigatorio' => true, 'max' => 255],
                'quantidade' => ['rotulo' => 'Quantidade', 'tipo' => 'number', 'obrigatorio' => true],
                'valor' => ['rotulo' => 'Valor total', 'tipo' => 'number', 'obrigatorio' => true],
                'fornecedor' => ['rotulo' => 'Fornecedor', 'max' => 160],
                'categoria' => ['rotulo' => 'Categoria', 'obrigatorio' => true, 'max' => 120],
                'observacao' => ['rotulo' => 'Observação', 'tipo' => 'textarea'],
            ],
            'abas' => ['compras' => 'Compras', 'vendas' => 'Vendas'],
        ],
        'vendas' => [
            'titulo' => 'Vendas', 'singular' => 'venda', 'novo' => 'Nova venda', 'primeiro' => 'Registrar a primeira venda',
            'tabela' => 'vendas', 'icone' => 'trending-up', 'secao' => 'comercial',
            'descricao' => 'O que saiu da fazenda. O valor vai sozinho para o financeiro; o animal vendido sai do rebanho ativo.',
            'busca' => ['item', 'comprador'], 'dica_busca' => 'Buscar em vendas',
            'colunas' => ['data' => 'Data', 'item' => 'Item', 'quantidade' => 'Quantidade', 'comprador' => 'Comprador', 'valor' => 'Valor'],
            'campos' => [
                'data' => ['rotulo' => 'Data', 'tipo' => 'date', 'obrigatorio' => true],
                'animal_id' => ['rotulo' => 'Animal', 'tipo' => 'animal', 'so_ativos' => true],
                'item' => ['rotulo' => 'Item', 'obrigatorio' => true, 'max' => 255],
                'quantidade' => ['rotulo' => 'Quantidade', 'tipo' => 'number', 'obrigatorio' => true],
                'valor' => ['rotulo' => 'Valor total', 'tipo' => 'number', 'obrigatorio' => true],
                'comprador' => ['rotulo' => 'Comprador', 'max' => 160],
                'categoria' => ['rotulo' => 'Categoria', 'obrigatorio' => true, 'max' => 120],
                'observacao' => ['rotulo' => 'Observação', 'tipo' => 'textarea'],
            ],
            'abas' => ['compras' => 'Compras', 'vendas' => 'Vendas'],
        ],
    ];
    foreach ($m as $chave => &$cfg) {
        $cfg['chave'] = $chave;
        $cfg['secao'] ??= $chave;
        $cfg['ordem'] ??= 't.' . (campo_data($cfg) ?? 'created_at') . ' DESC, t.created_at DESC';
    }
    return $m;
}

function modulo(string $chave): ?array
{
    return modulos()[$chave] ?? null;
}

/** Primeiro campo de data do cadastro (usado no filtro de período e na ordem). */
function campo_data(array $m): ?string
{
    foreach ($m['campos'] as $k => $c) if (($c['tipo'] ?? 'text') === 'date') return $k;
    return null;
}

/** Selo de situação: sempre ícone + texto. */
function selos(): array
{
    return [
        'vencida' => ['Atrasada', 'alerta', 'triangle-exclamation-point'], 'proxima' => ['Vence logo', 'aviso', 'clock-3'],
        'em_dia' => ['Em dia', 'pasto', 'check'], 'substituida' => ['Reforço aplicado', 'cinza', 'repeat-2'],
        'ativo' => ['Ativo', 'pasto', 'check'], 'vendido' => ['Vendido', 'cinza', 'tag'], 'perdido' => ['Perdido', 'alerta', 'circle-x'],
        'falecido' => ['Morto', 'alerta', 'circle-x'], 'transferido' => ['Transferido', 'cinza', 'arrow-right-left'],
        'em_andamento' => ['Em andamento', 'aviso', 'clock-3'], 'confirmada' => ['Confirmada', 'pasto', 'check'], 'falhou' => ['Falhou', 'alerta', 'circle-x'],
        'parto_realizado' => ['Parto realizado', 'pasto', 'check'], 'cancelada' => ['Cancelada', 'cinza', 'circle-x'],
        'receita' => ['Receita', 'pasto', 'arrow-down-left'], 'despesa' => ['Despesa', 'alerta', 'arrow-up-right'], 'investimento' => ['Investimento', 'cinza', 'trending-up'],
        'aprovado' => ['Aprovado', 'pasto', 'check'], 'pendente' => ['Em análise', 'aviso', 'clock-3'], 'recusado' => ['Recusado', 'alerta', 'circle-x'],
        'estornado' => ['Estornado', 'cinza', 'repeat-2'], 'expirado' => ['Vencido', 'aviso', 'clock-3'],
        'aberto' => ['Aguardando resposta', 'aviso', 'clock-3'], 'respondido' => ['Respondido', 'pasto', 'check'], 'fechado' => ['Encerrado', 'cinza', 'check'],
        'inativo' => ['Inativo', 'cinza', 'circle-x'],
    ];
}
