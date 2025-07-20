# WordPress/WooCommerce Docker Development Environment

Ambiente completo de desenvolvimento WordPress/WooCommerce com Docker, otimizado para desenvolvimento local de plugins e temas.

## 📋 Índice

- [Características](#características)
- [Pré-requisitos](#pré-requisitos)
- [Instalação](#instalação)
- [Uso Rápido](#uso-rápido)
- [Estrutura do Projeto](#estrutura-do-projeto)
- [Comandos Disponíveis](#comandos-disponíveis)
- [Desenvolvimento de Plugins](#desenvolvimento-de-plugins)
- [Configurações](#configurações)
- [Troubleshooting](#troubleshooting)
- [Contribuição](#contribuição)

## ✨ Características

- **WordPress 6.4** com PHP 8.2 e Apache
- **WooCommerce** pré-instalado e configurado
- **MySQL 8.0** com configurações otimizadas para desenvolvimento
- **phpMyAdmin** para gerenciamento do banco de dados
- **WP-CLI** para automação e gerenciamento
- **Template de plugin** completo com exemplos
- **Hot-reload** para desenvolvimento em tempo real
- **Scripts de automação** para configuração inicial
- **Debugging habilitado** com Query Monitor e Debug Bar
- **Volumes persistentes** para dados e desenvolvimento
- **Configurações de desenvolvimento** otimizadas

## 🔧 Pré-requisitos

- [Docker](https://docs.docker.com/get-docker/) (versão 20.10 ou superior)
- [Docker Compose](https://docs.docker.com/compose/install/) (versão 2.0 ou superior)
- [Make](https://www.gnu.org/software/make/) (opcional, para comandos simplificados)

### Verificar instalação

```bash
docker --version
docker compose --version
make --version  # opcional
```

## 🚀 Instalação

### 1. Clonar ou baixar o projeto

```bash
# Se usando Git
git clone <url-do-repositorio> wordpress-docker-dev
cd wordpress-docker-dev

# Ou extrair o arquivo ZIP baixado
unzip wordpress-docker-dev.zip
cd wordpress-docker-dev
```

### 2. Configurar variáveis de ambiente (opcional)

```bash
# Copiar e editar o arquivo .env se necessário
cp .env .env.local
nano .env.local  # ou seu editor preferido
```

### 3. Iniciar o ambiente

```bash
# Usando Make (recomendado)
make dev-quick-start

# Ou usando scripts diretamente
./scripts/manage.sh start
sleep 10
./scripts/manage.sh setup

# Ou usando Docker Compose diretamente
docker compose up -d
sleep 10
docker compose exec wpcli bash /scripts/setup-wordpress.sh
```

## ⚡ Uso Rápido

### Comandos essenciais com Make

```bash
# Iniciar ambiente completo
make dev-quick-start

# Parar todos os serviços
make stop

# Ver logs em tempo real
make logs

# Abrir shell no WordPress
make shell

# Executar comandos WP-CLI
make wp-list-plugins
make wp-flush-cache

# Reset completo do ambiente
make dev-reset
```

### Acessos

Após a inicialização, você pode acessar:

- **WordPress**: http://localhost:8080
- **Admin WordPress**: http://localhost:8080/wp-admin
  - Usuário: `admin`
  - Senha: `admin123`
- **phpMyAdmin**: http://localhost:8081
  - Usuário: `wordpress`
  - Senha: `wordpress_password`
- **Usuário de teste**: 
  - Usuário: `testuser`
  - Senha: `test123`




## 📁 Estrutura do Projeto

```
wordpress-docker-dev/
├── docker compose.yml          # Configuração principal do Docker
├── .env                        # Variáveis de ambiente
├── .gitignore                 # Arquivos ignorados pelo Git
├── Makefile                   # Comandos simplificados
├── README.md                  # Esta documentação
├── todo.md                    # Lista de tarefas do projeto
│
├── config/                    # Configurações dos serviços
│   ├── mysql.cnf             # Configuração do MySQL
│   ├── php.ini               # Configuração do PHP
│   └── apache.conf           # Configuração do Apache
│
├── scripts/                   # Scripts de automação
│   ├── setup-wordpress.sh    # Configuração inicial do WordPress
│   └── manage.sh             # Script principal de gerenciamento
│
├── wp-content/               # Conteúdo do WordPress (persistente)
│   ├── plugins/              # Plugins (incluindo template)
│   │   └── plugin-template/  # Template de plugin de exemplo
│   ├── themes/               # Temas personalizados
│   └── uploads/              # Uploads (ignorado pelo Git)
│
├── mysql-data/               # Dados do MySQL (ignorado pelo Git)
└── backups/                  # Backups do banco de dados
```

### Volumes Docker

- `wordpress_data`: Dados principais do WordPress
- `mysql_data`: Dados do banco MySQL (mapeado para ./mysql-data)
- `./wp-content/plugins`: Plugins para desenvolvimento
- `./wp-content/themes`: Temas para desenvolvimento
- `./wp-content/uploads`: Uploads do WordPress

## 🛠️ Comandos Disponíveis

### Usando Make (Recomendado)

| Comando | Descrição |
|---------|-----------|
| `make help` | Mostrar todos os comandos disponíveis |
| `make start` | Iniciar todos os serviços |
| `make stop` | Parar todos os serviços |
| `make restart` | Reiniciar todos os serviços |
| `make setup` | Configurar WordPress inicial |
| `make logs` | Mostrar logs dos serviços |
| `make status` | Mostrar status dos containers |
| `make shell` | Abrir shell no container WordPress |
| `make mysql` | Abrir shell MySQL |
| `make backup` | Fazer backup do banco de dados |
| `make restore` | Restaurar backup do banco de dados |
| `make clean` | Limpar containers e volumes |
| `make dev-quick-start` | Início rápido (start + setup) |
| `make dev-reset` | Reset completo do ambiente |

### Comandos WP-CLI via Make

| Comando | Descrição |
|---------|-----------|
| `make wp-list-plugins` | Listar plugins instalados |
| `make wp-list-themes` | Listar temas instalados |
| `make wp-flush-cache` | Limpar cache do WordPress |
| `make wp-update-core` | Atualizar WordPress core |

### Usando Scripts Diretamente

```bash
# Script principal de gerenciamento
./scripts/manage.sh [comando]

# Comandos disponíveis:
./scripts/manage.sh start          # Iniciar serviços
./scripts/manage.sh stop           # Parar serviços
./scripts/manage.sh restart        # Reiniciar serviços
./scripts/manage.sh setup          # Configurar WordPress
./scripts/manage.sh logs           # Mostrar logs
./scripts/manage.sh status         # Status dos containers
./scripts/manage.sh clean          # Limpar ambiente
./scripts/manage.sh backup         # Backup do banco
./scripts/manage.sh restore        # Restaurar backup
./scripts/manage.sh wp [comando]   # Executar WP-CLI
./scripts/manage.sh shell          # Shell WordPress
./scripts/manage.sh mysql          # Shell MySQL
./scripts/manage.sh help           # Ajuda completa
```

### Usando Docker Compose Diretamente

```bash
# Gerenciamento básico
docker compose up -d              # Iniciar em background
docker compose down               # Parar e remover containers
docker compose logs -f            # Ver logs em tempo real
docker compose ps                 # Status dos containers

# Executar comandos
docker compose exec wordpress bash                    # Shell WordPress
docker compose exec mysql mysql -u wordpress -p      # Shell MySQL
docker compose exec wpcli wp plugin list --allow-root # WP-CLI
```

## 🔌 Desenvolvimento de Plugins

### Template de Plugin Incluído

O projeto inclui um template completo de plugin localizado em `wp-content/plugins/plugin-template/` com:

- **Estrutura MVC** organizada
- **Integração WooCommerce** completa
- **Sistema de configurações** com interface admin
- **AJAX** para frontend e backend
- **Shortcodes** personalizados
- **Hooks e filtros** do WordPress
- **Internacionalização** preparada
- **Assets** (CSS/JS) organizados
- **Debugging** habilitado

### Criando um Novo Plugin

1. **Copiar o template:**
```bash
cp -r wp-content/plugins/plugin-template wp-content/plugins/meu-plugin
```

2. **Renomear arquivos e classes:**
```bash
cd wp-content/plugins/meu-plugin
mv plugin-template.php meu-plugin.php
```

3. **Editar o arquivo principal:**
```php
// Alterar informações do cabeçalho
/**
 * Plugin Name: Meu Plugin
 * Description: Descrição do meu plugin
 * Version: 1.0.0
 * Author: Seu Nome
 */

// Alterar constantes
define('MEU_PLUGIN_VERSION', '1.0.0');
define('MEU_PLUGIN_PLUGIN_FILE', __FILE__);
// ... outras constantes

// Renomear classe principal
class MeuPlugin {
    // ... código da classe
}
```

4. **Atualizar classes e arquivos:**
- Renomear todas as classes de `PluginTemplate_*` para `MeuPlugin_*`
- Atualizar text domain de `plugin-template` para `meu-plugin`
- Modificar prefixos de funções e variáveis
- Atualizar caminhos de assets

### Hot-Reload para Desenvolvimento

O ambiente está configurado para hot-reload automático:

- **Plugins**: Alterações em `wp-content/plugins/` são refletidas imediatamente
- **Temas**: Alterações em `wp-content/themes/` são refletidas imediatamente
- **Assets**: CSS/JS são carregados sem cache em modo de desenvolvimento
- **PHP**: Alterações em código PHP são aplicadas automaticamente

### Debugging

O ambiente inclui ferramentas de debugging pré-configuradas:

- **WP_DEBUG**: Habilitado
- **Query Monitor**: Plugin instalado para análise de queries
- **Debug Bar**: Plugin instalado para debugging geral
- **Error Logs**: Disponíveis nos logs do Docker
- **MySQL Logs**: Queries lentas são logadas

Para ver logs em tempo real:
```bash
make logs                    # Todos os logs
make watch-wp-logs          # Apenas WordPress
make watch-mysql-logs       # Apenas MySQL
```


## ⚙️ Configurações

### Variáveis de Ambiente (.env)

As principais configurações podem ser alteradas no arquivo `.env`:

```bash
# Configurações do WordPress
WORDPRESS_DB_HOST=mysql:3306
WORDPRESS_DB_NAME=wordpress
WORDPRESS_DB_USER=wordpress
WORDPRESS_DB_PASSWORD=wordpress_password
WORDPRESS_TABLE_PREFIX=wp_

# Configurações do MySQL
MYSQL_ROOT_PASSWORD=root_password
MYSQL_DATABASE=wordpress
MYSQL_USER=wordpress
MYSQL_PASSWORD=wordpress_password

# Portas dos serviços
WORDPRESS_PORT=8080
PHPMYADMIN_PORT=8081
MYSQL_PORT=3306

# Configurações do site
WORDPRESS_SITE_URL=http://localhost:8080
WORDPRESS_ADMIN_USER=admin
WORDPRESS_ADMIN_PASSWORD=admin123
WORDPRESS_ADMIN_EMAIL=admin@localhost.com

# Configurações de desenvolvimento
WP_ENVIRONMENT_TYPE=development
WORDPRESS_DEBUG=true
WORDPRESS_DEBUG_LOG=true
WORDPRESS_DEBUG_DISPLAY=false
```

### Personalizando Portas

Para alterar as portas dos serviços, edite o arquivo `.env`:

```bash
# Exemplo: usar porta 8090 para WordPress
WORDPRESS_PORT=8090
PHPMYADMIN_PORT=8091
```

Depois reinicie os serviços:
```bash
make restart
```

### Configurações do PHP

Edite `config/php.ini` para personalizar configurações do PHP:

```ini
memory_limit = 512M
max_execution_time = 300
upload_max_filesize = 64M
post_max_size = 64M
```

### Configurações do MySQL

Edite `config/mysql.cnf` para personalizar configurações do MySQL:

```ini
[mysqld]
max_allowed_packet = 64M
innodb_buffer_pool_size = 256M
```

### Configurações do Apache

Edite `config/apache.conf` para personalizar configurações do Apache.

## 🔧 Troubleshooting

### Problemas Comuns

#### 1. Porta já está em uso

**Erro**: `Port 8080 is already in use`

**Solução**:
```bash
# Verificar qual processo está usando a porta
sudo lsof -i :8080

# Alterar porta no arquivo .env
WORDPRESS_PORT=8090

# Reiniciar serviços
make restart
```

#### 2. Permissões de arquivo

**Erro**: Problemas de permissão ao salvar arquivos

**Solução**:
```bash
# Corrigir permissões
sudo chown -R $USER:$USER wp-content/
chmod -R 755 wp-content/
```

#### 3. Banco de dados não conecta

**Erro**: `Error establishing a database connection`

**Solução**:
```bash
# Verificar se MySQL está rodando
docker compose ps

# Reiniciar apenas o MySQL
docker compose restart mysql

# Verificar logs do MySQL
docker compose logs mysql
```

#### 4. WordPress não carrega

**Erro**: Página em branco ou erro 500

**Solução**:
```bash
# Verificar logs do WordPress
make watch-wp-logs

# Verificar se todos os containers estão rodando
make status

# Reiniciar WordPress
docker compose restart wordpress
```

#### 5. WP-CLI não funciona

**Erro**: Comandos WP-CLI falham

**Solução**:
```bash
# Verificar se container wpcli está rodando
docker compose ps wpcli

# Executar comando diretamente
docker compose exec wpcli wp --info --allow-root
```

### Comandos de Diagnóstico

```bash
# Verificar status de todos os containers
make status

# Ver logs de todos os serviços
make logs

# Verificar conectividade do banco
make mysql
# Dentro do MySQL: SHOW DATABASES;

# Testar WP-CLI
make wp core version

# Verificar configuração do WordPress
make shell
# Dentro do container: cat wp-config.php
```

### Reset Completo

Se tudo mais falhar, você pode fazer um reset completo:

```bash
# ATENÇÃO: Isso irá apagar todos os dados!
make clean

# Recriar ambiente do zero
make dev-quick-start
```

### Backup e Restauração

#### Fazer Backup

```bash
# Backup automático do banco
make backup

# Backup manual
docker compose exec mysql mysqldump -u wordpress -pwordpress_password wordpress > backup_$(date +%Y%m%d_%H%M%S).sql
```

#### Restaurar Backup

```bash
# Restaurar usando script
make restore

# Restaurar manualmente
docker compose exec -T mysql mysql -u wordpress -pwordpress_password wordpress < backup_file.sql
```

## 🚀 Produção e Deploy

### Preparando para Produção

Este ambiente é otimizado para desenvolvimento. Para produção:

1. **Alterar senhas** no arquivo `.env`
2. **Desabilitar debugging**:
   ```bash
   WORDPRESS_DEBUG=false
   WORDPRESS_DEBUG_LOG=false
   WP_ENVIRONMENT_TYPE=production
   ```
3. **Configurar SSL/HTTPS**
4. **Otimizar configurações** do MySQL e PHP
5. **Implementar backup automático**
6. **Configurar monitoramento**

### Exportar Plugin Desenvolvido

```bash
# Criar arquivo ZIP do plugin
cd wp-content/plugins/
zip -r meu-plugin.zip meu-plugin/ -x "*.git*" "node_modules/*" "*.log"
```

## 🤝 Contribuição

### Estrutura para Contribuições

1. **Fork** o projeto
2. **Crie uma branch** para sua feature (`git checkout -b feature/nova-feature`)
3. **Commit** suas mudanças (`git commit -am 'Adiciona nova feature'`)
4. **Push** para a branch (`git push origin feature/nova-feature`)
5. **Abra um Pull Request**

### Diretrizes

- Mantenha o código limpo e documentado
- Teste todas as alterações
- Atualize a documentação quando necessário
- Siga as convenções de código do WordPress

### Reportar Problemas

Ao reportar problemas, inclua:

- Versão do Docker e Docker Compose
- Sistema operacional
- Logs relevantes
- Passos para reproduzir o problema

## 📝 Licença

Este projeto está licenciado sob a [MIT License](LICENSE).

## 🙏 Agradecimentos

- [WordPress](https://wordpress.org/) - CMS base
- [WooCommerce](https://woocommerce.com/) - Plugin de e-commerce
- [Docker](https://docker.com/) - Containerização
- Comunidade WordPress/WooCommerce

## 📞 Suporte

- **Documentação**: Este README
- **Issues**: Use o sistema de issues do repositório
- **Discussões**: Use as discussões do repositório

---

**Desenvolvido com ❤️ para a comunidade WordPress/WooCommerce**

