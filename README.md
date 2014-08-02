php-proxy
===
A simple php proxy server, used to download web pages, web images, game resources, ...
Also can used to filter ads, modify data, ...

apache config
===
```
<VirtualHost *:8000>
    ServerAdmin xianlinli@gmail.com
    DocumentRoot "/data/www/php-proxy"
    ServerName 127.0.0.1
    <Directory "/data/www/php-proxy">
        Options FollowSymLinks
        AllowOverride All
        Order allow,deny
        Allow from all
    </Directory>
    ErrorLog "logs/proxy-error.log"
    CustomLog "logs/proxy-access.log" common
</VirtualHost>
```

nginx config
===
```
server {
    listen 8000;

    location / {
        root            /data/www/php-proxy;
        index           index.php;
        rewrite         ^(.*)$ /index.php last;
    }

    location = /index.php {
        root            /data/www/php-proxy;
        fastcgi_pass    127.0.0.1:9000;
        fastcgi_index   index.php;
        fastcgi_param   SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include         fastcgi_params;
    }
}
```

browser config
===
```
set proxy to 127.0.0.1:8000
```

test
===
```
telnet 127.0.0.1 8000
curl --proxy 127.0.0.1:8000 -I http://www.example.com/
```
