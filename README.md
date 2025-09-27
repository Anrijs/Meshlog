# MeshLog Web
Web side for [MeshCore logger firmware](https://github.com/Anrijs/MeshCore/tree/logger)

## Requirements
- PHP
- MySQL or MariaDB

## Installation
1. Setup MySQL database and add tables form `migrations/000_initial_setup.sql`
2. Rename `config.example.php` to `config.php` and fill database login details.
3. Flash logger node [MeshCore logger firmware](https://github.com/Anrijs/MeshCore/tree/logger) (Xiao S3 and T3S3 are currently supported)
4. Conenct to logger node via serial and set configuration:
 - `log url https://<your_site>/meshlog/log.php` Where data is sent (should point to `log.php` file)
 - `log report 1800` Self-report interval, can be 0 to disable
 - `log auth SomeSecret` Secret used for web authorization
 - `wifi ssid YourWifiSSID`
 - `wifi password YourWifiPassword`
 - `set name  Node Name`
 - `set lat xx.xxxxx`
 - `set lon xx.xxxxx`
 - `reboot` Apply changes
5. Add reporter to database:
```sql
INSERT INTO `reporters` (`name`, `public_key`, `lat`, `lon`, `auth`, `authorized`, `color`)
VALUES ('ANR-Log', 'LOGGER_NODE_PUBLIC_KEY', '56.0', '27.0', 'SomeSecret', '1', 'red')
```

## TODO
Maybe some day I will create admin interface for easier setup and logger node management...
