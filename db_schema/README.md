DB schema should be updated using the following command:

```
mysqldump --no-data --password=password --skip-add-drop-table --skip-add-locks --skip-comments --skip-set-charset \
        candb | dos2unix | sed 's/ AUTO_INCREMENT=[0-9]*//g' >candb.sql
```

(see also: https://stackoverflow.com/a/15656501)

Adjust for Docker/Podman as needed.
