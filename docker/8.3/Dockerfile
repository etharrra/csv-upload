FROM ubuntu:24.04

LABEL maintainer="Taylor Otwell"

ARG WWWGROUP
ARG NODE_VERSION=18

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND noninteractive
ENV TZ=UTC

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt_update() { \
        for attempt in 1 2 3 4 5; do \
            apt-get update -o Acquire::Retries=5 -o APT::Update::Error-Mode=any && return 0; \
            sleep $((attempt * 5)); \
        done; \
        apt-get update -o Acquire::Retries=5 -o APT::Update::Error-Mode=any; \
    } \
    && apt_install() { \
        apt-get install -y --no-install-recommends "$@"; \
    } \
    && apt_update \
    && mkdir -p /etc/apt/keyrings \
    && apt_install gnupg gosu curl ca-certificates zip unzip git supervisor libcap2-bin \
    && apt_install php8.3-cli \
       php8.3-curl \
       php8.3-mysql php8.3-mbstring \
       php8.3-xml php8.3-zip php8.3-bcmath \
       php8.3-intl php8.3-readline \
       php8.3-redis \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt_update \
    && apt_install nodejs \
    && npm install -g npm \
    && apt_install mysql-client \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN setcap "cap_net_bind_service=+ep" /usr/bin/php8.3

RUN groupadd --force -g $WWWGROUP sail
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail

COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /etc/php/8.3/cli/conf.d/99-sail.ini
RUN chmod +x /usr/local/bin/start-container

EXPOSE 8000 6001

ENTRYPOINT ["start-container"]
