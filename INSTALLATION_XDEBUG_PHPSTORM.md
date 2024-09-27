###### [Источник](https://habr.com/ru/articles/712670/?code=46ec49e7865a774bc73c89adf8d87e36&state=cMo7LE9IhMYpAyPOy6TjmnhK&hl=ru "Источник")

# PHPStorm + XDebug + Docker

## 1. Настраиваем интеграцию PHPStorm с Docker

Идём в **Settings > Build, Execution, Deployment > Docker** и создаём максимально простую интеграцию через локальное приложение Docker:

> Мой вариант.

![мой](https://img001.prntscr.com/file/img001/jVZ-qznfRTaptw8ex67NKA.png)

> Источник.

![источник](https://habrastorage.org/r/w1560/getpro/habr/upload_files/9cd/13b/4d6/9cd13b4d62203956ee0ef960bb13601c.png)

------------

## 2. Настраиваем выполнение скриптов через удалённый (в контейнере) интерпретатор

Идём в **Settings > PHP > CLI Interpreter > 3 точки справа от него** и добавляем такую конфигурацию:

> Мой вариант.

![мой](https://img001.prntscr.com/file/img001/lBodp8OqTheiBvDpgjr8JQ.png)

> Источник. *не проходит валидация установленного пхп, потому что версия 5.6, но это ни на что не влияет

![источник](https://habrastorage.org/r/w1560/getpro/habr/upload_files/7d7/e35/566/7d7e35566e49e36ef58a78420d8dce57.jpg)

**Name** может быть любым

**Server** выбираем тот, который создали шагом ранее

**Configuration files: **путь до docker-compose.yml

**Service**: контейнер с PHP

Остальное на ваш вкус, но в графе Lifecycle лучше оставить **connect to existing container**

Теперь, в графе CLI Interpreter вы увидите выбранным только что созданный конфиг:

> Мой вариант.

![мой](https://img001.prntscr.com/file/img001/KaK4MXvkQkaiL3adPjMXBg.png)

> Источник.

![источник](https://habrastorage.org/r/w1560/getpro/habr/upload_files/4c7/955/2a0/4c79552a0a9c29ca3c1047f04fce5bf5.jpg)

------------

## 3. Даём PHPStorm знать о том, как мы обращаемся к серверу

Идём в **Settings > PHP > Servers** и добавляем новую конфигурацию сервера:

> Мой вариант.

![мой](https://img001.prntscr.com/file/img001/VNFOai1wQICHzFC-f31w1A.png)

> Источник.

![источник](https://habrastorage.org/r/w1560/getpro/habr/upload_files/10e/93d/445/10e93d445aea4058bf39d3878382b793.jpg)

Порт берём из своего конфига nginx. В моём случае он поднят в отдельном контейнере и смотрит наружу через **80**, у автора в **8001**

Тут важно запомнить **Name**, это пригодится чуть позже

------------

## 4. Чуть-чуть донастроим интеграцию PHPStorm с XDebug
Идём в **Settings > PHP > Debug > XDebug** и добавляем порт **9001**:

> Мой вариант.

![мой](https://img001.prntscr.com/file/img001/GxH3gSJdR6SxvbNzTVFa5w.png)

> Источник.

![источник](https://habrastorage.org/r/w1560/getpro/habr/upload_files/10f/01e/988/10f01e98893a11ad51049a3811292dae.png)

------------

## 5. Настроим конфигурацию запуска дебаггера

Идём в **Run > Edit configurations** и создаём новую конфигурацию на основе **PHP Remote Debug**:

> Мой вариант.

![мой](https://img001.prntscr.com/file/img001/piJHCzYUSpmEH56bPYWQWw.png)

> Источник.

![источник](https://habrastorage.org/r/w1560/getpro/habr/upload_files/ab8/08e/b14/ab808eb14a805e7b057a1146850724db.jpg)

Здесь **Server** это как раз **Name** из пункта **#3**

В этом окне **Name** может быть любым, надо его запомнить, он, опять же, пригодятся чуть позже

**IDE key** может быть любым, НО! Если у вас **XDebug 3**, то обязательно запоминаем значение

------------

## 6. Донастраиваем docker-compose.yml

В контейнере с PHP для правильной интеграции с XDebug мы должны иметь доступ к локальной машине, то есть хосту:

```yaml
app:
	  container_name: app
	  build:
			context: .
			dockerfile: ./.docker/php/Dockerfile
	  volumes:
			- ./:/app
	  restart: unless-stopped
	  # Добавляет в /etc/hosts - доступ наружу - к твоей машине* - в моем случае Windows
	  extra_hosts:
			- "host.docker.internal:host-gateway"
```

Здесь важна только директива **extra_hosts**, она обязательна

------------

## 7. Донастраиваем Dockerfile контейнера с PHP

Добавляем кусок конфига куда-нибудь в конец перед **WORKDIR /app:**

XDebug 3

```
# Xdebug - *инструкция в файле "INSTALLATION_XDEBUG_PHPSTORM.md"
# Копирует скрипт установки PHP-расширений из образа mlocati/php-extension-installer в контейнер. Этот скрипт упрощает установку дополнительных PHP-расширений.
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/

# Использует ранее скопированный скрипт для установки PHP-расширения Xdebug, которое нужно для отладки кода.
RUN install-php-extensions xdebug

# Устанавливает переменную окружения PHP_IDE_CONFIG для настройки сервера отладки в IDE(PHPSTORM). Это нужно, чтобы IDE(PHPSTORM) могла соотносить запросы отладки с сервером внутри контейнера.
ENV PHP_IDE_CONFIG 'serverName=docker-app'

# Добавляет в конфигурационный файл Xdebug строчку, которая включает режим debug. Этот режим нужен для работы с точками останова и другими возможностями отладки.
RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Указывает Xdebug начинать отладку автоматически при каждом HTTP-запросе к приложению. Это удобно, когда нужно сразу подключаться к процессу отладки.
RUN echo "xdebug.start_with_request = yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Указывает, что Xdebug должен подключаться к хосту (твоему компьютеру) по адресу host.docker.internal. Этот адрес позволяет контейнеру обращаться к хост-системе, что важно для взаимодействия с IDE.
RUN echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Задает порт 9001 для взаимодействия между Xdebug и отладочной средой (IDE). По умолчанию Xdebug использует порт 9000, но здесь используется 9001, чтобы избежать конфликтов.
RUN echo "xdebug.client_port=9001" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Устанавливает путь к файлу /var/log/xdebug.log, куда Xdebug будет записывать свои логи. Это помогает отслеживать возможные проблемы в работе расширения.
RUN echo "xdebug.log=/var/log/xdebug.log" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Настраивает идентификатор (idekey) сессий отладки для связи с PHPStorm. Это ключевое слово, которое Xdebug использует для идентификации конкретной сессии отладки в IDE.
RUN echo "xdebug.idekey = PHPSTORM" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
# END Xdebug - *инструкция в файле "INSTALLATION_XDEBUG_PHPSTORM.md"
```
> последней строке указываем IDE key из шага **#5**

Этот кусок ставит XDebug через через pecl и закидывает нужные параметры XDebug в конфиг

Из самого важного здесь:

```
ENV PHP_IDE_CONFIG - в serverName надо прописать Name из пункта #5
xdebug.*_port - порт из пункта #4
xdebug.*_host - в моём случае указан хост для мака, если у вас линукс или винда, то поищите альтернативы, они точно есть
```

------------

## 8. Ставим брейкпоинты, заходим на сайт и радуемся жизни

Больше тут сказать нечего :)

