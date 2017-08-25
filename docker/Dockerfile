FROM centos:7

RUN rpm -Uvh https://dl.fedoraproject.org/pub/epel/epel-release-latest-7.noarch.rpm && \
    rpm -Uvh https://mirror.webtatic.com/yum/el7/webtatic-release.rpm && \
    yum install -y php70w php70w-cli php70w-pdo php70w-mbstring php70w-intl php70w-pdo \
    php70w-pgsql php70w-mysqlnd php70w-xml git && \
    curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

COPY docker/entrypoint.bash /usr/local/bin/entrypoint.bash
COPY composer.json /vendor_preinstalled/

RUN cd /vendor_preinstalled/ && \
    composer update --no-interaction --prefer-source --prefer-stable \
        --prefer-lowest

WORKDIR /app/
ENTRYPOINT ["/bin/bash", "/usr/local/bin/entrypoint.bash"];
CMD ["phpunit"]

