php-proxy
===
A simple php proxy server

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

test
===
```
telnet 127.0.0.1 8000
curl --proxy 127.0.0.1:8000 -I http://www.example.com/
```
