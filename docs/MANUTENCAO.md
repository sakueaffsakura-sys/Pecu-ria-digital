# Manutenção e solução de problemas

Todos os comandos rodam **na VPS, como root** (`ssh root@IP-DA-VPS`).

| Onde fica | Caminho |
| --- | --- |
| Código baixado do GitHub | `/opt/pecuaria-digital` |
| Site em produção | `/var/www/pecuaria` |
| Configuração | `/var/www/pecuaria/.env` |
| Fotos, logos, banners, planilhas enviadas | `/var/www/pecuaria/storage` |
| Backups | `/var/backups/pecuaria` |
| Nginx do site | `/etc/nginx/sites-available/pecuaria` |
| Ajustes do PHP | `/etc/php/8.x/fpm/conf.d/90-pecuaria.ini` |

## Conferir se está tudo certo

```bash
php /var/www/pecuaria/scripts/verificar.php
```

## Atualizar para a versão nova

```bash
cd /opt/pecuaria-digital
git pull
bash deploy/instalar.sh seudominio.com.br seu-email@exemplo.com
```

O instalador preserva o `.env`, o banco e a pasta `storage`. Ele só troca o código e cria as tabelas
novas. Faça um backup antes por garantia: `/usr/local/bin/pecuaria-backup`.

## Backup

Automático todo dia às 03:17, com 14 dias guardados. Comandos de backup e restauração estão em
[BANCO-DE-DADOS.md](BANCO-DE-DADOS.md#backup-e-restauração).

## Ver erros

```bash
tail -n 50 /var/log/nginx/error.log          # erros do Nginx e do PHP
tail -n 50 /var/log/php*-fpm.log             # PHP-FPM parando ou reiniciando
journalctl -u mariadb -n 50                  # banco
```

Para ver o erro na tela enquanto investiga, mude por alguns minutos no `.env` `APP_DEBUG=true`.
**Volte para `false` depois.**

## Reiniciar os serviços

```bash
systemctl restart php*-fpm nginx mariadb
systemctl status nginx --no-pager            # deve mostrar "active (running)"
```

## Problemas comuns

| Sintoma | Causa provável | O que fazer |
| --- | --- | --- |
| "Falta o arquivo .env" | O `.env` não existe na pasta do site | `cp .env.example .env` e preencha; ou rode o `instalar.sh` |
| Página "Algo deu errado" / erro 500 | Banco fora do ar ou senha errada no `.env` | `php scripts/verificar.php`: ele mostra a causa |
| **502 Bad Gateway** | PHP-FPM parado | `systemctl restart php*-fpm` |
| Site não abre de jeito nenhum | Nginx parado ou firewall | `systemctl restart nginx` e `ufw status` (precisa liberar 80 e 443) |
| Endereço com domínio não abre | DNS ainda não aponta para a VPS | `ping seudominio.com.br` precisa mostrar o IP da VPS |
| Cadeado de HTTPS não aparece | Certificado não emitido | Veja [HTTPS](#https) abaixo |
| Entro e volto para a tela de entrada | `APP_URL` com `https://`, mas o site está sem HTTPS (o cookie seguro não grava) | Emita o certificado ou troque `APP_URL` para `http://` |
| Foto não envia | Arquivo grande demais ou `storage` sem permissão | Até 3 MB; `chown -R root:www-data /var/www/pecuaria/storage && chmod -R 770 /var/www/pecuaria/storage` |
| "Esqueci a senha" não chega | E-mail não configurado ou indo para o spam | Preencha `MAIL_*` no `.env` ([CONFIGURACAO.md](CONFIGURACAO.md#e-mail)) e rode o `verificar.php` |
| Clima não aparece | A VPS não alcança a Open-Meteo | `curl -I https://api.open-meteo.com` deve responder 200 |
| "Muitas tentativas" ao entrar | Proteção contra senha errada repetida | Espere 15 minutos |

## HTTPS

O certificado da Let's Encrypt é grátis, vale por 90 dias e **renova sozinho**. Para emitir depois
(por exemplo, quando o domínio passou a apontar para a VPS):

```bash
apt-get install -y certbot python3-certbot-nginx
certbot --nginx -d seudominio.com.br -m seu-email@exemplo.com --agree-tos --redirect
sed -i 's#^APP_URL=http://#APP_URL=https://#' /var/www/pecuaria/.env
certbot renew --dry-run          # testa a renovação automática
```

## Esqueci a senha do administrador (sem e-mail configurado)

```bash
cd /var/www/pecuaria
php -r 'echo password_hash("NovaSenha2026", PASSWORD_DEFAULT), "\n";'
# copie o resultado (começa com $2y$) e:
mysql -u root pecuaria -e "UPDATE usuarios SET senha_hash = 'COLE-AQUI' WHERE email = 'voce@exemplo.com';"
```

Entre com `NovaSenha2026` e troque a senha em **Senha e segurança**.

## Segurança: o que já vem feito e o que conferir

Já vem pronto: firewall só com SSH, HTTP e HTTPS; banco aceitando conexão **só de dentro da VPS**;
usuário de banco sem poder apagar tabelas; `.env` e código fora da pasta pública; atualizações
automáticas de segurança do sistema; senhas com bcrypt; limite de tentativas; proteção contra CSRF;
verificação em duas etapas opcional.

Recomendado fazer uma vez:

1. Trocar a senha root da VPS: `passwd`.
2. Entrar por **chave SSH** em vez de senha (no seu computador: `ssh-keygen` e depois
   `ssh-copy-id root@IP-DA-VPS`). Depois de testar, desligue a senha no SSH: em
   `/etc/ssh/sshd_config` coloque `PasswordAuthentication no` e rode `systemctl restart ssh`.
3. Instalar o fail2ban, que bloqueia quem erra a senha do SSH muitas vezes: `apt-get install -y fail2ban`.
4. Ligar a **verificação em duas etapas** na sua conta de administrador do site.
5. Copiar os backups para fora da VPS de tempos em tempos.

## Desempenho

A VPS1 (4 vCores, 8 GB) aguenta com folga centenas de fazendas. Se um dia ficar lento:

```bash
htop                                        # uso de CPU e memória (apt install htop)
mysql -u root -e "SHOW PROCESSLIST;"        # consultas rodando agora
```

Em `/etc/php/8.x/fpm/pool.d/www.conf` dá para aumentar `pm.max_children` (por exemplo para 30)
e depois rodar `systemctl restart php*-fpm`.
