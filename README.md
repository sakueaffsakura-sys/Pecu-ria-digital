# Pecuária Digital (PHP + MySQL)

O sistema Pecuária Digital refeito em **PHP + MySQL**, para rodar em **VPS Linux com acesso root**
(ex.: VPS1 da Combinário: 4 vCores, 8 GB, só SSH, sem painel).

- **Mesmo visual do original** ("Brinco e pasto"): menu verde-curral, números no brinco amarelo, fontes
  Big Shoulders e Atkinson Hyperlegible, tema claro e escuro, barra de baixo com "Lançar" no celular.
- **Mesmos nomes de tabelas e campos** do banco original (Supabase), com identificadores UUID: dá para trazer os dados de lá.
- PHP 8.1+ sem framework e sem Composer; MySQL 8 ou MariaDB 10.5+; Nginx + PHP-FPM.

## O que já está pronto

| Etapa | Situação |
| --- | --- |
| 1. Visual, entrada e cadastros: Início, Agenda, Meu rebanho (com ficha), Lotes, Evolução dos bezerros, Vacinação (aplicações e tipos), Inseminação, Nascimentos, Leite e anotações, Lucros e investimentos, Compra e venda, Avisos, Busca (Ctrl K), Dados da propriedade | **Pronto** |
| 2. Relatórios, Indicadores, Calculadoras e clima, Importar planilha, Diagnóstico dos dados | Pronta |
| 3. Meu perfil (foto), logo e banner da propriedade, senha e duas etapas, aparelhos conectados, recuperar senha por e-mail, baixar meus dados, privacidade e termos | Próxima |
| 4. Assinatura (Mercado Pago), Biblioteca de eBooks, Use no celular, Ajuda e suporte | Depois |
| 5. Área do administrador e a assistente Yui | Depois |

As áreas das próximas etapas não aparecem no menu até ficarem prontas.

## Documentação

| Documento | Conteúdo |
| --- | --- |
| [docs/INSTALACAO.md](docs/INSTALACAO.md) | Passo a passo na VPS (SSH, domínio, instalador, primeiro acesso) e instalação manual |
| [docs/CONFIGURACAO.md](docs/CONFIGURACAO.md) | Todas as opções do arquivo **`.env`**: banco, e-mail, Mercado Pago, IA |
| [docs/BANCO-DE-DADOS.md](docs/BANCO-DE-DADOS.md) | Tabelas, criar o banco, consultas úteis, backup, restauração e migração do Supabase |
| [docs/MANUTENCAO.md](docs/MANUTENCAO.md) | Atualizar, ver erros, problemas comuns, HTTPS, segurança |

## Instalar na VPS (resumo)

Peça **Ubuntu 24.04** (ou 22.04, ou Debian 12) à hospedagem e entre por SSH (`ssh root@IP-DA-VPS`;
no Android, use o app Termius). Depois rode:

```bash
apt-get update && apt-get install -y git
git clone https://github.com/sakueaffsakura-sys/Pecu-ria-digital.git /opt/pecuaria-digital
cd /opt/pecuaria-digital
bash deploy/instalar.sh seudominio.com.br seu-email@exemplo.com     # ou: bash deploy/instalar.sh IP-DA-VPS
```

O instalador configura Nginx, PHP-FPM, MariaDB (usuário só do site e senha aleatória), **cria o `.env`**,
cria as tabelas, ativa o firewall, o HTTPS grátis (com domínio), as atualizações de segurança e o
backup diário. No fim, ele confere tudo com `scripts/verificar.php`.

Abra o endereço e clique em **Criar conta**. **A primeira conta criada vira a administradora do site.**

## Configuração (`.env`)

A configuração inteira fica no arquivo **`.env`** na raiz do projeto, fora da pasta pública e fora do GitHub.
O modelo é o [`.env.example`](.env.example). Para instalar sem o script:

```bash
cp .env.example .env && nano .env      # preencha DB_SENHA e APP_URL
php scripts/verificar.php              # confere PHP, extensões, .env, banco, tabelas, pastas e e-mail
```

## Estrutura

```
.env.example          modelo de configuração (copie para .env)
app/                  código PHP: núcleo, acesso, regras, cadastros, gráficos, importação, rotas/
views/                telas (HTML com PHP)
public/               ÚNICA pasta aberta ao navegador: index.php, assets/ (CSS, JS, fontes, ícones)
database/             schema.sql (tabelas) e criar-banco.sql (banco + usuário)
deploy/               instalar.sh, nginx.conf, backup.sh
scripts/              verificar.php, demonstracao.php, schema-sqlite.php
docs/                 documentação
storage/              criado na instalação: fotos, cache, planilhas (fora do Git)
tests/                testes de ponta a ponta
```

## Regras que vieram do original

- Campos de animal, lote e vacina são **digitados** (brinco ou nome). Se não existir, o cadastro é criado sozinho.
- Inseminação sem previsão de parto: data + período de gestação da propriedade (283 dias por padrão).
- Vacina sem próxima dose: data + intervalo do tipo de vacina. "Atrasada" só conta a **última** dose de cada vacina por animal ou lote.
- Compra vira despesa e venda vira receita no financeiro (um lançamento por operação; editar atualiza, excluir apaga).
  O animal vendido sai do rebanho ativo e do lote; se a venda for excluída, ele volta a ser ativo.
- Nascimento liga o bezerro à vaca e ao lote; excluir o nascimento desfaz a ligação.
- Leite: o total em reais (litros × valor por litro) é calculado pelo banco.
- Avisos: vacinas atrasadas, partos em 15 dias, queda de 20% no leite da semana e animais jovens sem pesagem há 60 dias.
  "Marcar como visto" esconde aquela situação até ela mudar.

## Segurança

Senhas com bcrypt; sessão por aparelho (dá para encerrar); cookie `HttpOnly`/`SameSite` e `Secure` no HTTPS;
token contra CSRF em todo formulário; consultas sempre com parâmetros; toda leitura e gravação filtra pela propriedade
e confere se a pessoa é dona ou membro; "Só consulta" não grava; limite de tentativas no banco; cabeçalhos de segurança
(CSP sem script de fora); só o `public/index.php` executa; `.env` e código fora da pasta pública.

## Testar

```bash
php tests/e2e.php    # sobe o site com um banco SQLite temporário e faz o caminho de um usuário (77 verificações)
```

Ícones: [Lucide](https://lucide.dev) (ISC). Fontes: Atkinson Hyperlegible Next e Big Shoulders Display (SIL OFL), servidas pelo próprio site.
