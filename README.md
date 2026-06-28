# SCHF SDK

Contratos e conectores oficiais do ecossistema SCHF.

## Estrutura

| Caminho | Propósito |
|---------|-----------|
| `src/Connector/ConnectorInterface.php` | Contrato oficial de conectores de banco |
| `src/Connector/Drivers/` | Implementações de conectores (FirebirdDriver, etc.) |
| `src/Normalization/` | DTOs de normalização (11 classes) |
| `schemas/` | Schemas JSON de bundle e normalização |
| `tests/` | Testes unitários |
| `VERSION` | `0.1.0` |

## Conectores Disponíveis

| Driver | Status |
|--------|--------|
| Firebird | ✅ Implementado |
| MySQL | 🔜 Planejado |
| PostgreSQL | 🔜 Planejado |
| SQL Server | 🔜 Planejado |
| Oracle | 🔜 Planejado |
| SQLite | 🔜 Planejado |

## Uso

```php
$driver = new SCHF\SDK\Connector\Drivers\FirebirdDriver();
$driver->connect([
    'host' => 'localhost',
    'port' => 3050,
    'dbname' => '/path/to/database.fdb',
    'username' => 'SYSDBA',
    'password' => 'masterkey',
]);
$schema = $driver->getSchema();
```

## Testes

```bash
vendor/bin/phpunit
```

14 testes (8 interface + 6 driver).

## Licença

MIT
