# shopware sku mapping
## setup

install composer into the current directory
https://getcomposer.org/download/

install dependencies via composer
```bash
php composer.phar install
```

## configure
copy the existing `.env.example` file to `.env` and configure the database settings

## run
dry run
```bash
php migrate.php
```

non dry run
```bash
DRY_RUN=false php migrate.php
```