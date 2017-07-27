FROM centos:7

RUN yum install -y php php-cli php-pdo php-mbstring php-intl php-pdo \
	php-pgsql php-mysqlnd php-xml git && \
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

