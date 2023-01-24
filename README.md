# telegram-openai
telegram bot dengan mengunakan chat GPT pada openAI

### install
#### dengan dockerfile
1. `$ docker pull maulana20/telegram-openai:latest`
2. `$ docker run --env-file .env -it --name telegram-opeanai -d maulana20/telegram-openai:latest sh`
#### dengan docker-compose
```yml
services:
  telegram-opeanai:
    image: maulana20/telegram-openai:latest
    environment:
      - TELEGRAM_TOKEN=
      - OPENAI_KEY=
```
### openai manager
`$ php -S 0.0.0.0:80 openai-manager.php`
### referensi
- https://core.telegram.org/bots/api
- https://openai.com/api
