# Setup Database

```sh
docker volume create mariadb
docker network create database-network
docker compose -f docker-compose.db.staging.yml up -d
```

```sh
docker exec -it mariadb mariadb -u root -p123
SHOW DATABASES;
CREATE DATABASE IF NOT EXISTS idp_staging;
SHOW DATABASES;
CREATE USER 'idp_user'@'%' IDENTIFIED BY '<password>';
GRANT ALL PRIVILEGES ON idp_staging.* TO 'idp_user'@'%';
FLUSH PRIVILEGES;
SHOW GRANTS FOR 'idp_user'@'%';
```
