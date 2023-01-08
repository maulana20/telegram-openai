FROM php:8.1-fpm

WORKDIR /app

COPY telegram-openai.php ./

ENTRYPOINT ["/bin/bash", "-c", "php telegram-openai.php"]
