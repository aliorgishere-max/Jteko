FROM php:8.2-apache

# نصب افزونه‌های مورد نیاز cURL (اگر کدت نیاز داشت)
RUN apt-get update && apt-get install -y libcurl4-openssl-dev pkg-config libssl-dev

# کپی کردن کدهای تو به پوشه اصلی سرور اباچی
COPY . /var/www/html/

# تغییر پورت سرور به پورتی که رندر می‌خواد
EXPOSE 80

# روشن کردن سرور
CMD ["apache2-foreground"]
