# Meshlog Docker Stack

A ready-to-use Docker stack for running **Meshlog** with `nginx`, `php-fpm`, and `MariaDB`.

---

## 📦 Overview

This stack provides:

- **nginx** (serving Meshlog on port `80`)
- **php-fpm** (running PHP application backend)
- **MariaDB** (with automatic initialization from `setup.sql` on first start)

Logs from all services are forwarded to container `stdout/stderr`, so you can monitor everything with `docker logs`.

---

## 🚀 Quickstart

### 1. Install Docker

```bash
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh ./get-docker.sh
```

### 2. Clone the repository

```bash
git clone https://github.com/Anrijs/Meshlog.git
cd ./Meshlog/docker
```

### 3. Configure environment variables

Edit the .env file inside the ```docker``` directory.
Example configuration:

```env
DB_NAME=meshcore
DB_USER=meshuser
DB_PASS=meshpass
DB_ROOT_PASS=meshroot
TIMEZONE=Europe/Riga
```

### 4. Build and start the stack

```bash
sudo docker compose up -d --build
```

Stop the stack:

```bash
sudo docker compose down
```

## 🗄️ Database Initialization
- On first start, the stack automatically imports `setup.sql` into MariaDB.
- Database schema will be created.

## ➕ Adding the First Reporter (Logger)

After the stack is running, insert the first reporter into the database (see details in project's ```README.md```).

```bash
set -a; source .env; set +a

sudo docker exec -i mariadb mariadb -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
INSERT INTO reporters (name, public_key, lat, lon, auth, authorized, color)
VALUES ('ANR-Log', 'LOGGER_NODE_PUBLIC_KEY', '56.0', '27.0', 'SomeSecret', '1', 'red');
SELECT * FROM reporters WHERE name='ANR-Log';
"
```

## 🌐 Access

The Meshlog web interface is available at:
```
http://<your-server-ip>:80
```

## ⚠️ Warning

The stack must be reverse-proxied enabling ```https``` support for the logger firmware to access it (logger firmware supports only ```https```).