# Project informations
This project is the seventh project of the online course on OpenClassrooms : [Développeur d'application - PHP / Symfony](https://openclassrooms.com/fr/paths/59-developpeur-dapplication-php-symfony)

## Description of needs
Develop an API for a smartphones shop named BileMo.

List of functions needed :
- get the phone list
- get details of a specific product
- get a customer's user list
- get details of a specific user
- add a new user to a customer
- delete a user

## Installation

### Cloning the project
```
git clone https://github.com/GN4RK/P7
```

### Installing dependencies 
```
composer install
npm install
npm run dev
```

### Configurations

#### Database
Change database connection in .env file : 
```
DATABASE_URL="mysql://root:@localhost/database_name?serverVersion=your_server&charset=utf8"
```

### Database migration
Run database migration on the new environnement
```
php bin/console doctrine:migrations:migrate
```

### Load data fixture
This command will load fresh data into your database
```
php bin/console doctrine:fixtures:load
```

### Running server
```
symfony server:start
```

## Badge Codacy
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/1e4a8bf5f15440f389d660fca7525a44)](https://www.codacy.com/gh/GN4RK/P7/dashboard?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=GN4RK/P7&amp;utm_campaign=Badge_Grade)