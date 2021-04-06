```shell
pg_stats_reporter-10.0/bin/pg_stats_reporter.php --onlybody --host localhost --port 15432 --dbname postgres --username postgres --resprefix '/static/report/' --password postgres -O tmp --inline

# onlybody
#   resource copy를 하지 않음.
# host localhost 
# port 15432 
# dbname postgres 
# username postgres 
# resprefix '/static/report/'
#   Resource의 Prefix URL
# password postgres
# -O tmp 
# inline
#   HTML을 쉘로 출력함.
```


