# MeshLog Web
Web side for [MeshCore logger firmware](https://github.com/Anrijs/MeshCore/tree/logger)

## Requirements
- PHP
- MySQL or MariaDB

## Installation
1. Setup MySQL database
2. Open `setup.php`, follow instructions
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
5. Add reporter to database by going to `admin/`
