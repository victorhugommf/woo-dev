# Diretrizes para Testes em Ambiente Docker

## Mapeamento do Plugin no Container

O plugin `woocommerce-cloudxm-nfse` está mapeado no docker-compose da seguinte forma:

```yaml
volumes:
  - ./wp-content/plugins/woocommerce-cloudxm-nfse:/var/www/html/wp-content/plugins/woocommerce-cloudxm-nfse
```

**Caminhos importantes:**
- **Host:** `./wp-content/plugins/woocommerce-cloudxm-nfse/`
- **Container:** `/var/www/html/wp-content/plugins/woocommerce-cloudxm-nfse/`
- **Diretório de testes:** `./wp-content/plugins/woocommerce-cloudxm-nfse/tests/`

## Execução de Testes

### Containers Disponíveis

1. **wordpress** (`wp_wordpress`): Container principal do WordPress
2. **wpcli** (`wp_cli`): Container com WP-CLI para automação
3. **mysql** (`wp_mysql`): Banco de dados

### Comandos para Executar Testes

#### Executar testes PHP dentro do container WordPress:
```bash
# Entrar no container WordPress
docker exec -it wp_wordpress bash

# Navegar para o diretório de testes
cd /var/www/html/wp-content/plugins/woocommerce-cloudxm-nfse/tests/

# Executar testes PHP
php nome-do-teste.php
```

#### Executar testes usando WP-CLI:
```bash
# Entrar no container WP-CLI
docker exec -it wp_cli bash

# Navegar para o diretório de testes
cd /var/www/html/wp-content/plugins/woocommerce-cloudxm-nfse/tests/

# Executar testes com WP-CLI (se necessário)
wp eval-file nome-do-teste.php
```

#### Executar testes diretamente do host:
```bash
# Executar comando no container sem entrar nele
docker exec wp_wordpress php /var/www/html/wp-content/plugins/woocommerce-cloudxm-nfse/tests/nome-do-teste.php

# Ou usando WP-CLI
docker exec wp_cli wp eval-file /var/www/html/wp-content/plugins/woocommerce-cloudxm-nfse/tests/nome-do-teste.php
```

## Estrutura de Testes

### Localização dos Testes
Todos os testes devem ser criados em: `./wp-content/plugins/woocommerce-cloudxm-nfse/tests/`

### Tipos de Testes Suportados

1. **Testes Standalone**: Testes que não dependem do WordPress
2. **Testes de Integração**: Testes que precisam do ambiente WordPress completo
3. **Testes Unitários**: Testes de classes e métodos específicos

### Configuração de Ambiente para Testes

#### Variáveis de Ambiente Disponíveis:
- `WORDPRESS_DB_HOST`: Host do banco de dados
- `WORDPRESS_DB_NAME`: Nome do banco
- `WORDPRESS_DB_USER`: Usuário do banco
- `WORDPRESS_DB_PASSWORD`: Senha do banco

#### Acesso ao Banco de Dados nos Testes:
```php
// Configuração de conexão com o banco dentro dos testes
$db_config = [
    'host' => 'mysql', // Nome do serviço no docker-compose
    'database' => getenv('WORDPRESS_DB_NAME') ?: 'wordpress',
    'username' => getenv('WORDPRESS_DB_USER') ?: 'wordpress_user',
    'password' => getenv('WORDPRESS_DB_PASSWORD') ?: 'wordpress_password'
];
```

## Boas Práticas para Testes

### 1. Isolamento de Testes
- Cada teste deve ser independente
- Limpar dados de teste após execução
- Usar transações de banco quando possível

### 2. Nomenclatura de Arquivos
- Testes unitários: `*Test.php`
- Testes de integração: `integration-*.php`
- Testes standalone: `standalone-*.php`

### 3. Estrutura de Teste
```php
<?php
// Cabeçalho padrão para testes
if (!defined('ABSPATH')) {
    // Para testes standalone, definir constantes necessárias
    define('ABSPATH', '/var/www/html/');
}

// Incluir arquivos necessários do plugin
require_once __DIR__ . '/../src/Services/NomeDoServico.php';

// Classe de teste
class NomeDoTeste {
    public function run() {
        echo "Iniciando teste...\n";
        
        // Implementar testes aqui
        
        echo "Teste concluído.\n";
    }
}

// Executar teste se chamado diretamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $teste = new NomeDoTeste();
    $teste->run();
}
```

### 4. Debugging
- Usar `error_log()` para logs de debug
- Logs ficam em `/var/www/html/wp-content/debug.log`
- Verificar logs: `docker exec wp_wordpress tail -f /var/www/html/wp-content/debug.log`

## Arquivos de Apoio Criados

### 1. Script de Execução de Testes (`run-tests.sh`)
Script bash para facilitar a execução de testes:

```bash
# Executar todos os testes
./wp-content/plugins/woocommerce-cloudxm-nfse/tests/run-tests.sh --all

# Executar teste específico
./wp-content/plugins/woocommerce-cloudxm-nfse/tests/run-tests.sh --test IssGroupTest.php

# Listar testes disponíveis
./wp-content/plugins/woocommerce-cloudxm-nfse/tests/run-tests.sh --list

# Usar container específico
./wp-content/plugins/woocommerce-cloudxm-nfse/tests/run-tests.sh --all --container wp_cli
```

### 2. Configuração de Testes (`test-config.php`)
Arquivo centralizado com configurações para testes:

```php
// Incluir no início dos seus testes
require_once __DIR__ . '/test-config.php';

// Usar configurações
$db = TestConfig::createDatabaseConnection();
$paths = TestConfig::getPluginPaths();
$mockData = TestConfig::setupMockData();
```

### 3. Exemplo de Teste (`example-test.php`)
Template para criar novos testes seguindo as boas práticas.

## Comandos Úteis

### Verificar status dos containers:
```bash
docker-compose ps
```

### Ver logs do WordPress:
```bash
docker-compose logs wordpress
```

### Reiniciar containers:
```bash
docker-compose restart
```

### Executar testes usando o script:
```bash
# Dar permissão de execução (apenas uma vez)
chmod +x wp-content/plugins/woocommerce-cloudxm-nfse/tests/run-tests.sh

# Executar todos os testes
./wp-content/plugins/woocommerce-cloudxm-nfse/tests/run-tests.sh --all
```

### Executar testes manualmente:
```bash
# Executar teste específico
docker exec wp_wordpress php /var/www/html/wp-content/plugins/woocommerce-cloudxm-nfse/tests/example-test.php

# Executar todos os testes PHP
docker exec wp_wordpress find /var/www/html/wp-content/plugins/woocommerce-cloudxm-nfse/tests/ -name "*.php" -not -name "test-config.php" -exec php {} \;
```