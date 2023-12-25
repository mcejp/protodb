# Getting started

Assuming you have `podman` and `podman-compose`, you can launch the application in development mode as follows:

```
./protodb-run.sh
```

However, with an empty database there will not be much to see. Once the containers are running, you can seed the database like this:

```
./protodb-import-db.sh <tests/data/DefaultDataset.sql
```
